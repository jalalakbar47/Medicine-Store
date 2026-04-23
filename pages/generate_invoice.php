<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/libs/fpdf/fpdf.php';

if (!isset($_GET['sale_id'])) {
    die("Error: Sale ID not provided.");
}

$sale_id = $_GET['sale_id'];

// 1. Fetch Sale Data
$stmt = $pdo->prepare("SELECT s.*, p.name as patient_name, p.age, p.gender, p.contact as patient_phone, p.address as patient_address 
                       FROM sales s 
                       LEFT JOIN patients p ON s.patient_id = p.id 
                       WHERE s.id = ?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();

if (!$sale) {
    die("Error: Sale record not found.");
}

// 2. Fetch sale items
$stmtItems = $pdo->prepare("SELECT si.*, m.name as med_name, m.batch_number 
                           FROM sale_items si 
                           JOIN medicines m ON si.medicine_id = m.id 
                           WHERE si.sale_id = ?");
$stmtItems->execute([$sale_id]);
$items = $stmtItems->fetchAll();

// 3. Generate PDF
class InvoicePDF extends FPDF {
    function Header() {
        // Store Name
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(0, 10, 'PharmaCare', 0, 1, 'C');
        
        // Tagline
        $this->SetFont('Arial', 'I', 10);
        $this->Cell(0, 5, 'Your Trusted Medicine Store', 0, 1, 'C');
        
        $this->Ln(5);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-30);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'Thank you for choosing PharmaCare!', 0, 1, 'C');
        $this->Cell(0, 5, 'This is a computer-generated invoice', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }

    function FancyTable($header, $data) {
        // Colors, line width and bold font
        $this->SetFillColor(0, 102, 204); // Blue theme
        $this->SetTextColor(255);
        $this->SetDrawColor(0, 80, 180);
        $this->SetLineWidth(.3);
        $this->SetFont('', 'B');
        
        // Header
        $w = array(10, 80, 30, 20, 25, 25);
        for($i=0; $i<count($header); $i++)
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C', true);
        $this->Ln();
        
        // Color and font restoration
        $this->SetFillColor(240, 248, 255);
        $this->SetTextColor(0);
        $this->SetFont('');
        
        // Data
        $fill = false;
        $i = 1;
        foreach($data as $row) {
            $this->Cell($w[0], 6, $i++, 'LR', 0, 'C', $fill);
            $this->Cell($w[1], 6, $row['med_name'], 'LR', 0, 'L', $fill);
            $this->Cell($w[2], 6, $row['batch_number'], 'LR', 0, 'C', $fill);
            $this->Cell($w[3], 6, $row['quantity'], 'LR', 0, 'C', $fill);
            $this->Cell($w[4], 6, '$'.number_format($row['unit_price'], 2), 'LR', 0, 'R', $fill);
            $this->Cell($w[5], 6, '$'.number_format($row['subtotal'], 2), 'LR', 0, 'R', $fill);
            $this->Ln();
            $fill = !$fill;
        }
        // Closing line
        $this->Cell(array_sum($w), 0, '', 'T');
        $this->Ln(2);
    }
}

$pdf = new InvoicePDF();
$pdf->AliasNbPages();
$pdf->AddPage();

// Invoice Info Section
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(100, 7, 'INVOICE TO:', 0, 0);
$pdf->Cell(90, 7, 'SALE DETAILS:', 0, 1);

$pdf->SetFont('Arial', '', 10);
$patient_name = $sale['patient_name'] ?: 'Walk-in Customer';
$invoice_no = 'INV-' . str_pad($sale['id'], 5, '0', STR_PAD_LEFT);

// Left Side: Patient Info
$pdf->Cell(100, 5, $patient_name, 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'Invoice #:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 5, $invoice_no, 0, 1);

$pdf->Cell(100, 5, $sale['gender'] . ($sale['age'] ? ', ' . $sale['age'] . ' YRS' : ''), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'Date:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 5, date('d-m-Y', strtotime($sale['sale_date'])), 0, 1);

$pdf->Cell(100, 5, 'Contact: ' . ($sale['patient_phone'] ?: 'N/A'), 0, 0);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(40, 5, 'Payment Method:', 0, 0);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(50, 5, 'Cash', 0, 1);

$pdf->Ln(10);

// Table Header
$header = array('#', 'Medicine Name', 'Batch No', 'Qty', 'Price', 'Total');
$pdf->FancyTable($header, $items);

// Summary Section
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 11);
$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(35, 7, 'Subtotal:', 0, 0);
$pdf->Cell(25, 7, '$' . number_format($sale['total_amount'], 2), 0, 1, 'R');

$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(35, 7, 'Discount:', 0, 0);
$pdf->Cell(25, 7, '-$' . number_format($sale['discount'], 2), 0, 1, 'R');

$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor(0, 102, 204);
$pdf->Cell(130, 10, '', 0, 0);
$pdf->Cell(35, 10, 'GRAND TOTAL:', 0, 0);
$pdf->Cell(25, 10, '$' . number_format($sale['final_amount'], 2), 0, 1, 'R');

// Set headers for inline browser view
header('Content-type: application/pdf');
header('Content-Disposition: inline; filename="' . $invoice_no . '.pdf"');
$pdf->Output('I', $invoice_no . '.pdf');
?>

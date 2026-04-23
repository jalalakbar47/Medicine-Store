<?php
require_once __DIR__ . '/../../includes/auth.php';
requireLogin();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $patient_id = !empty($_POST['patient_id']) ? (int)$_POST['patient_id'] : null;
    $invoice_no = $_POST['invoice_no'];
    $total_amount = (float)$_POST['total_amount'];
    $discount = (float)$_POST['discount'];
    $tax_percent = (float)$_POST['tax_percent'];
    $final_amount = (float)$_POST['final_amount'];
    
    $cart_data = json_decode($_POST['cart_data'], true);

    if (empty($cart_data)) {
        setFlash('danger', 'Error: Cart is empty.');
        header("Location: ../../pages/pos.php");
        exit();
    }

    try {
        $pdo->beginTransaction();

        // 1. Insert into Sales Table
        $stmt = $pdo->prepare("INSERT INTO sales (patient_id, invoice_no, total_amount, discount, tax_amount, final_amount) 
                               VALUES (?, ?, ?, ?, ?, ?)");
        $tax_amount = ($total_amount - $discount) * ($tax_percent / 100);
        $stmt->execute([$patient_id, $invoice_no, $total_amount, $discount, $tax_amount, $final_amount]);
        $sale_id = $pdo->lastInsertId();

        // 2. Insert Sale Items & Update Stock
        $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, subtotal) 
                                  VALUES (?, ?, ?, ?, ?)");
        $stmtStock = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");

        foreach ($cart_data as $item) {
            $subtotal = $item['price'] * $item['qty'];
            $stmtItem->execute([$sale_id, $item['id'], $item['qty'], $item['price'], $subtotal]);
            
            // Deduct from Stock
            $stmtStock->execute([$item['qty'], $item['id']]);
        }

        $pdo->commit();
        
        setFlash('success', "Sale #{$invoice_no} completed successfully!");
        header("Location: ../../pages/invoice.php?id=" . $sale_id);
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('danger', "Error processing sale: " . $e->getMessage());
        header("Location: ../../pages/pos.php");
    }
} else {
    header("Location: ../../pages/pos.php");
}
exit();
?>

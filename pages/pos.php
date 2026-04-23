<?php
require_once __DIR__ . '/../includes/db.php';

// Handle AJAX Medicine Search
if (isset($_GET['action']) && $_GET['action'] === 'search_medicine') {
    $term = $_GET['term'] . '%';
    $stmt = $pdo->prepare("SELECT * FROM medicines WHERE (name LIKE ? OR batch_number LIKE ?) AND quantity > 0 LIMIT 10");
    $stmt->execute([$term, $term]);
    echo json_encode($stmt->fetchAll());
    exit;
}

// Handle Sale Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_sale') {
    try {
        $pdo->beginTransaction();

        $patient_id = !empty($_POST['patient_id']) ? $_POST['patient_id'] : null;
        $total_amount = (float)$_POST['total_amount'];
        $discount = (float)$_POST['discount'];
        $final_amount = $total_amount - $discount;
        $invoice_no = 'INV-' . strtoupper(substr(uniqid(), 7));
        
        $cart = json_decode($_POST['cart_data'], true);

        if (empty($cart)) {
            throw new Exception("Cart is empty.");
        }

        // 1. Insert into sales
        $stmtSale = $pdo->prepare("INSERT INTO sales (patient_id, invoice_no, total_amount, discount, final_amount) VALUES (?, ?, ?, ?, ?)");
        $stmtSale->execute([$patient_id, $invoice_no, $total_amount, $discount, $final_amount]);
        $sale_id = $pdo->lastInsertId();

        // 2. Process each item
        foreach ($cart as $item) {
            $medicine_id = $item['id'];
            $qty = $item['qty'];
            $price = $item['price'];
            $subtotal = $qty * $price;

            // Check stock again
            $st = $pdo->prepare("SELECT quantity FROM medicines WHERE id = ?");
            $st->execute([$medicine_id]);
            $current_qty = $st->fetchColumn();

            if ($current_qty < $qty) {
                throw new Exception("Insufficient stock for one or more items.");
            }

            // Insert into sale_items
            $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, medicine_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)");
            $stmtItem->execute([$sale_id, $medicine_id, $qty, $price, $subtotal]);

            // Deduct stock
            $stmtDeduct = $pdo->prepare("UPDATE medicines SET quantity = quantity - ? WHERE id = ?");
            $stmtDeduct->execute([$qty, $medicine_id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'invoice_no' => $invoice_no, 'sale_id' => $sale_id]);
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$pageTitle = 'Point of Sale (POS)';
require_once __DIR__ . '/../includes/header.php';

// Fetch Patients for selection
$patients = $pdo->query("SELECT * FROM patients ORDER BY name ASC")->fetchAll();
?>

<div class="row">
    <!-- Left Side: POS Interface -->
    <div class="col-lg-8">
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-primary text-white">
                <h6 class="m-0 font-weight-bold"><i class="fas fa-cash-register me-2"></i> Billing Terminal</h6>
            </div>
            <div class="card-body">
                <!-- Medicine Search & Select -->
                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label small fw-bold">Search Product</label>
                        <div class="position-relative">
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted"></i></span>
                                <input type="text" id="medSearch" class="form-control border-start-0" placeholder="Type name or batch..." autocomplete="off">
                            </div>
                            <div id="searchResults" class="list-group position-absolute w-100 mt-1 shadow-lg" style="z-index: 1000; max-height: 300px; overflow-y: auto; display: none;"></div>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label small fw-bold">Quick Select Dropdown</label>
                        <?php
                        $allMedicines = $pdo->query("SELECT * FROM medicines WHERE quantity > 0 ORDER BY name ASC")->fetchAll();
                        ?>
                        <select id="medSelect" class="form-select border-primary bg-light">
                            <option value="">-- Choose Medicine --</option>
                            <?php foreach ($allMedicines as $med): ?>
                                <option value="<?= $med['id'] ?>" 
                                        data-id="<?= $med['id'] ?>" 
                                        data-name="<?= htmlspecialchars($med['name']) ?>" 
                                        data-batch="<?= $med['batch_number'] ?>" 
                                        data-price="<?= $med['selling_price'] ?>" 
                                        data-stock="<?= $med['quantity'] ?>">
                                    <?= $med['name'] ?> (<?= $med['quantity'] ?> left)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="cartTable">
                        <thead class="table-light">
                            <tr>
                                <th>Medicine</th>
                                <th>Batch</th>
                                <th width="150">Price</th>
                                <th width="120">Qty</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="cartItems">
                            <!-- Items will be added here via JS -->
                            <tr id="emptyCartRow">
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                                    Cart is empty. Search and add medicines to start.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Side: Order Summary -->
    <div class="col-lg-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3 bg-dark text-white">
                <h6 class="m-0 font-weight-bold">Order Summary</h6>
            </div>
            <div class="card-body">
                <div class="mb-4">
                    <label class="form-label fw-bold">Select Patient (Optional)</label>
                    <select id="patientSelect" class="form-select border-primary">
                        <option value="">Walk-in Customer</option>
                        <?php foreach ($patients as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= $p['name'] ?> (<?= $p['contact'] ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <hr>

                <div class="d-flex justify-content-between mb-2">
                    <span>Subtotal:</span>
                    <span class="fw-bold" id="summarySubtotal">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-2 align-items-center">
                    <span>Discount:</span>
                    <div class="input-group input-group-sm w-50">
                        <span class="input-group-text">$</span>
                        <input type="number" id="discountInput" class="form-control text-end" value="0.00" step="0.01">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-4 p-3 bg-light rounded border border-primary">
                    <span class="h4 mb-0">Total:</span>
                    <span class="h4 mb-0 text-primary fw-bold" id="summaryTotal">$0.00</span>
                </div>

                <div class="mt-4">
                    <button class="btn btn-primary btn-lg w-100 mb-2 py-3" id="completeSaleBtn" disabled>
                        <i class="fas fa-check-circle me-2"></i> COMPLETE SALE
                    </button>
                    <button class="btn btn-outline-danger w-100" id="clearCartBtn">
                        <i class="fas fa-trash-alt me-2"></i> Clear Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];

$(document).ready(function() {
    // Search Medicine Logic
    $('#medSearch').on('input', function() {
        const term = $(this).val();
        if (term.length < 2) {
            $('#searchResults').hide();
            return;
        }

        $.get('pos.php', { action: 'search_medicine', term: term }, function(data) {
            const results = JSON.parse(data);
            let html = '';
            if (results.length > 0) {
                results.forEach(med => {
                    html += `
                        <button type="button" class="list-group-item list-group-item-action add-to-cart" 
                            data-id="${med.id}" 
                            data-name="${med.name}" 
                            data-batch="${med.batch_number}" 
                            data-price="${med.selling_price}" 
                            data-stock="${med.quantity}">
                            <div class="d-flex justify-content-between">
                                <span><strong>${med.name}</strong> <small class="text-muted">(Batch: ${med.batch_number})</small></span>
                                <span class="badge bg-primary">$${med.selling_price}</span>
                            </div>
                            <small class="text-success">Available Stock: ${med.quantity}</small>
                        </button>`;
                });
            } else {
                html = '<div class="list-group-item text-muted">No medicines found.</div>';
            }
            $('#searchResults').html(html).show();
        });
    });

    // Close search on click outside
    $(document).click(function(e) {
        if (!$(e.target).closest('#medSearch, #searchResults').length) {
            $('#searchResults').hide();
        }
    });

    // Dropdown Selection
    $('#medSelect').on('change', function() {
        const selected = $(this).find('option:selected');
        if (selected.val() === "") return;
        
        const med = selected.data();
        const existing = cart.find(item => item.id === med.id);

        if (existing) {
            if (existing.qty < med.stock) {
                existing.qty++;
            } else {
                alert('Insufficient stock!');
            }
        } else {
            cart.push({
                id: med.id,
                name: med.name,
                batch: med.batch,
                price: parseFloat(med.price),
                qty: 1,
                stock: med.stock
            });
        }

        $(this).val(''); // Reset dropdown
        renderCart();
    });

    // Add to Cart (from search results)
    $(document).on('click', '.add-to-cart', function() {
        const med = $(this).data();
        const existing = cart.find(item => item.id === med.id);

        if (existing) {
            if (existing.qty < med.stock) {
                existing.qty++;
            } else {
                alert('Insufficient stock!');
            }
        } else {
            cart.push({
                id: med.id,
                name: med.name,
                batch: med.batch,
                price: parseFloat(med.price),
                qty: 1,
                stock: med.stock
            });
        }

        $('#medSearch').val('');
        $('#searchResults').hide();
        renderCart();
    });

    // Remove from Cart
    $(document).on('click', '.remove-item', function() {
        const id = $(this).data('id');
        cart = cart.filter(item => item.id !== id);
        renderCart();
    });

    // Update Quantity
    $(document).on('change', '.qty-input', function() {
        const id = $(this).data('id');
        const qty = parseInt($(this).val());
        const item = cart.find(i => i.id === id);

        if (qty > item.stock) {
            alert('Insufficient stock!');
            $(this).val(item.stock);
            item.qty = item.stock;
        } else if (qty < 1) {
            item.qty = 1;
            $(this).val(1);
        } else {
            item.qty = qty;
        }
        renderCart();
    });

    // Complete Sale
    $('#completeSaleBtn').on('click', function() {
        const patientId = $('#patientSelect').val();
        const discount = parseFloat($('#discountInput').val()) || 0;
        const subtotal = calculateSubtotal();

        if (confirm('Complete this transaction?')) {
            $(this).prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Processing...');

            $.post('pos.php', {
                action: 'complete_sale',
                patient_id: patientId,
                total_amount: subtotal,
                discount: discount,
                cart_data: JSON.stringify(cart)
            }, function(response) {
                const res = JSON.parse(response);
                if (res.success) {
                    alert('Sale completed successfully!');
                    // Ask if they want to download PDF or view invoice
                    if (confirm('Sale completed! Would you like to download the PDF Invoice now?')) {
                        window.open('generate_invoice.php?sale_id=' + res.sale_id, '_blank');
                    }
                    window.location.href = 'invoice.php?id=' + res.sale_id;
                } else {
                    alert('Error: ' + res.message);
                    $('#completeSaleBtn').prop('disabled', false).text('COMPLETE SALE');
                }
            });
        }
    });

    // Clear Cart
    $('#clearCartBtn').on('click', function() {
        if (confirm('Clear all items from cart?')) {
            cart = [];
            renderCart();
        }
    });

    // Recalculate on discount change
    $('#discountInput').on('input', renderCart);

    function calculateSubtotal() {
        return cart.reduce((sum, item) => sum + (item.price * item.qty), 0);
    }

    function renderCart() {
        const $tbody = $('#cartItems');
        if (cart.length === 0) {
            $tbody.html(`
                <tr id="emptyCartRow">
                    <td colspan="6" class="text-center py-5 text-muted">
                        <i class="fas fa-shopping-cart fa-3x mb-3 d-block"></i>
                        Cart is empty. Search and add medicines to start.
                    </td>
                </tr>`);
            $('#completeSaleBtn').prop('disabled', true);
            $('#summarySubtotal, #summaryTotal').text('$0.00');
            return;
        }

        let html = '';
        cart.forEach(item => {
            const sub = (item.price * item.qty).toFixed(2);
            html += `
                <tr>
                    <td><strong>${item.name}</strong></td>
                    <td><small class="text-muted">${item.batch}</small></td>
                    <td>$${item.price.toFixed(2)}</td>
                    <td>
                        <input type="number" class="form-control form-control-sm qty-input" data-id="${item.id}" value="${item.qty}" min="1" max="${item.stock}">
                    </td>
                    <td class="fw-bold">$${sub}</td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-danger remove-item" data-id="${item.id}"><i class="fas fa-times"></i></button>
                    </td>
                </tr>`;
        });
        $tbody.html(html);

        const subtotal = calculateSubtotal();
        const discount = parseFloat($('#discountInput').val()) || 0;
        const total = subtotal - discount;

        $('#summarySubtotal').text('$' + subtotal.toFixed(2));
        $('#summaryTotal').text('$' + total.toFixed(2));
        $('#completeSaleBtn').prop('disabled', false);
    }
});
</script>

<style>
.add-to-cart:hover {
    background-color: #f8f9fc;
}
.qty-input { width: 80px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

/**
 * POS Frontend Logic
 */

let cart = [];

$(document).ready(function() {
    const medicineSelect = $('#medicine-select');
    const cartItemsContainer = $('#cart-items');
    const emptyCartMsg = $('#empty-cart-msg');
    const subtotalEl = $('#summary-subtotal');
    const totalEl = $('#summary-total');
    const discountInput = $('#pos-discount');
    const taxInput = $('#pos-tax');
    const btnCheckout = $('#btn-checkout');
    
    // Hidden inputs
    const cartDataInput = $('#cart-data-input');
    const totalAmountInput = $('#total-amount-input');
    const finalAmountInput = $('#final-amount-input');

    // Add Medicine to Cart
    medicineSelect.on('change', function() {
        const selectedOption = $(this).find(':selected');
        const id = $(this).val();
        
        if (!id) return;

        const name = selectedOption.data('name');
        const price = parseFloat(selectedOption.data('price'));
        const stock = parseInt(selectedOption.data('stock'));

        // Check if already in cart
        const existingItem = cart.find(item => item.id === id);
        if (existingItem) {
            if (existingItem.qty < stock) {
                existingItem.qty++;
                updateCartUI();
            } else {
                alert('No more stock available!');
            }
        } else {
            cart.push({
                id: id,
                name: name,
                price: price,
                qty: 1,
                stock: stock
            });
            updateCartUI();
        }

        // Reset select
        $(this).val('').trigger('change.select2'); // In case of select2
    });

    // Handle Quantity Change
    $(document).on('change', '.item-qty', function() {
        const id = $(this).data('id');
        const qty = parseInt($(this).val());
        const stock = parseInt($(this).attr('max'));

        if (qty > stock) {
            alert('Insufficient stock!');
            $(this).val(stock);
            updateItemQty(id, stock);
        } else if (qty < 1) {
            updateItemQty(id, 1);
        } else {
            updateItemQty(id, qty);
        }
    });

    // Remove Item
    $(document).on('click', '.remove-item', function() {
        const id = $(this).data('id');
        cart = cart.filter(item => item.id !== id);
        updateCartUI();
    });

    // Recalculate on Discount/Tax change
    discountInput.on('input', calculateTotals);
    taxInput.on('input', calculateTotals);

    function updateItemQty(id, qty) {
        const item = cart.find(item => item.id == id);
        if (item) {
            item.qty = qty;
            updateCartUI();
        }
    }

    function updateCartUI() {
        if (cart.length === 0) {
            cartItemsContainer.html('<tr id="empty-cart-msg"><td colspan="5" class="text-center py-5 text-muted"><i class="fas fa-shopping-cart fa-3x mb-3"></i><p>Cart is empty. Select a medicine to begin.</p></td></tr>');
            btnCheckout.prop('disabled', true);
        } else {
            let html = '';
            cart.forEach(item => {
                const subtotal = (item.price * item.qty).toFixed(2);
                html += `
                    <tr>
                        <td>
                            <div class="fw-bold">${item.name}</div>
                            <small class="text-muted">Stock: ${item.stock}</small>
                        </td>
                        <td>$${item.price.toFixed(2)}</td>
                        <td>
                            <input type="number" class="form-control form-control-sm item-qty" 
                                   data-id="${item.id}" value="${item.qty}" min="1" max="${item.stock}">
                        </td>
                        <td>$${subtotal}</td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-link text-danger remove-item" data-id="${item.id}">
                                <i class="fas fa-times"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
            cartItemsContainer.html(html);
            btnCheckout.prop('disabled', false);
        }
        calculateTotals();
    }

    function calculateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.price * item.qty;
        });

        const discount = parseFloat(discountInput.val()) || 0;
        const taxRate = parseFloat(taxInput.val()) || 0;
        
        const taxAmount = (subtotal - discount) * (taxRate / 100);
        const finalTotal = subtotal - discount + taxAmount;

        subtotalEl.text(`$${subtotal.toFixed(2)}`);
        totalEl.text(`$${finalTotal.toFixed(2)}`);

        // Update hidden inputs
        cartDataInput.val(JSON.stringify(cart));
        totalAmountInput.val(subtotal.toFixed(2));
        finalAmountInput.val(finalTotal.toFixed(2));
    }
});

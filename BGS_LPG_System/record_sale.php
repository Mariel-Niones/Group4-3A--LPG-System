
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$page_title = 'Record Sale';
$isOwner = is_owner();

$branchId = null;
$branchName = '';
$branchError = '';
$products = [];
$successMessage = $_SESSION['sale_success'] ?? '';
$errorMessage = $_SESSION['sale_error'] ?? '';

unset($_SESSION['sale_success'], $_SESSION['sale_error']);

// Generate CSRF token.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrf_token = $_SESSION['csrf_token'];

// Resolve the user's assigned branch.
try {
    if ($isOwner) {
        $branchId = current_branch();

        if ($branchId !== null && $branchId !== '') {
            $stmt = $pdo->prepare(
                "SELECT id, branch_name
                 FROM branches
                 WHERE CAST(id AS CHAR) = ?
                    OR branch_name = ?
                 LIMIT 1"
            );
            $stmt->execute([(string)$branchId, (string)$branchId]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($branch) {
                $branchId = $branch['id'];
                $branchName = $branch['branch_name'];
            } else {
                $branchId = null;
            }
        }
    } else {
        $stmt = $pdo->prepare(
            "SELECT branch_name
             FROM users
             WHERE id = ?
             LIMIT 1"
        );
        $stmt->execute([$_SESSION['user_id']]);
        $staffBranch = $stmt->fetchColumn();

        if ($staffBranch !== false && $staffBranch !== null && $staffBranch !== '') {
            $stmt = $pdo->prepare(
                "SELECT id, branch_name
                 FROM branches
                 WHERE CAST(id AS CHAR) = ?
                    OR branch_name = ?
                 LIMIT 1"
            );
            $stmt->execute([(string)$staffBranch, (string)$staffBranch]);
            $branch = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($branch) {
                $branchId = $branch['id'];
                $branchName = $branch['branch_name'];
            }
        }
    }

    if ($branchId === null || $branchId === '') {
        $branchError = $isOwner
            ? 'Please select or assign a valid branch before recording a sale.'
            : 'Your account does not have a valid assigned branch. Please contact the owner.';
    }
} catch (PDOException $e) {
    error_log('Record Sale branch error: ' . $e->getMessage());
    $branchError = 'Unable to identify your assigned branch. Please contact the owner.';
    $branchId = null;
}

// Load products and stock for the assigned branch.
if ($branchId !== null) {
    try {
        $stmt = $pdo->prepare(
            "SELECT
                p.id,
                p.product_name,
                p.category,
                p.price,
                COALESCE(bi.quantity, 0) AS stock_quantity
             FROM products p
             LEFT JOIN branch_inventory bi
                ON bi.product_id = p.id
               AND bi.branch_id = ?
             ORDER BY p.product_name ASC"
        );
        $stmt->execute([$branchId]);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('Record Sale product error: ' . $e->getMessage());
        $errorMessage = 'Unable to load products. Please check your database tables.';
    }
}

// Process sale.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? '';

    if (!hash_equals($csrf_token, $postedToken)) {
        $errorMessage = 'Invalid or expired form submission. Please try again.';
    } elseif ($branchId === null) {
        $errorMessage = $branchError !== ''
            ? $branchError
            : 'Your assigned branch could not be found.';
    } else {
        $productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
        $quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $customerName = trim($_POST['customer_name'] ?? '');
        $paymentMethod = trim($_POST['payment_method'] ?? '');

        $allowedPayments = ['Cash', 'GCash'];

        if (!$productId || !$quantity || $quantity < 1) {
            $errorMessage = 'Please select a product and enter a valid quantity.';
        } elseif (!in_array($paymentMethod, $allowedPayments, true)) {
            $errorMessage = 'Please select a valid payment method.';
        } elseif (mb_strlen($customerName) > 150) {
            $errorMessage = 'Customer name must not exceed 150 characters.';
        } else {
            try {
                $pdo->beginTransaction();

                // Confirm the product exists and lock its branch stock.
                $stmt = $pdo->prepare(
                    "SELECT
                        p.id,
                        p.product_name,
                        p.price,
                        COALESCE(bi.quantity, 0) AS stock_quantity
                     FROM products p
                     LEFT JOIN branch_inventory bi
                        ON bi.product_id = p.id
                       AND bi.branch_id = ?
                     WHERE p.id = ?
                     LIMIT 1
                     FOR UPDATE"
                );
                $stmt->execute([$branchId, $productId]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new RuntimeException('The selected product could not be found.');
                }

                $stock = (int)$product['stock_quantity'];

                if ($quantity > $stock) {
                    throw new RuntimeException(
                        'Insufficient stock. Available quantity: ' . $stock . '.'
                    );
                }

                if ($stock < 1) {
                    throw new RuntimeException('This product is out of stock at your branch.');
                }

                $totalAmount = (float)$product['price'] * $quantity;

                // Reduce stock only for the assigned branch.
                $stmt = $pdo->prepare(
                    "UPDATE branch_inventory
                     SET quantity = quantity - ?
                     WHERE branch_id = ?
                       AND product_id = ?
                       AND quantity >= ?"
                );
                $stmt->execute([
                    $quantity,
                    $branchId,
                    $productId,
                    $quantity
                ]);

                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException(
                        'Stock could not be updated. Please refresh and try again.'
                    );
                }

                // Record the sale in the confirmed branch_sales table.
                $stmt = $pdo->prepare(
                    "INSERT INTO branch_sales
                        (
                            branch_id,
                            user_id,
                            customer_name,
                            payment_method,
                            total_amount,
                            product_id,
                            quantity
                        )
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );

                $stmt->execute([
                    (string)$branchId,
                    $_SESSION['user_id'],
                    $customerName !== '' ? $customerName : null,
                    $paymentMethod,
                    $totalAmount,
                    $productId,
                    $quantity
                ]);

                $pdo->commit();

                $_SESSION['sale_success'] =
                    'Sale recorded successfully! Total: ₱' .
                    number_format($totalAmount, 2);

                header('Location: record_sale.php');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                if ($e instanceof PDOException) {
                    error_log('Record Sale database error: ' . $e->getMessage());
                    $errorMessage = 'Unable to record the sale. Please check the database and try again.';
                } else {
                    $errorMessage = $e->getMessage();
                }
            }
        }
    }
}


require_once 'includes/header.php';
?>

<style>
/* SIDEBAR */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
    width: 220px;
    padding: 20px 12px;
    box-shadow: 1px 0 5px #0001;
    flex-shrink: 0;
}

.layout > .sidebar a {
    display: block;
    padding: 11px 14px;
    margin: 3px 0;
    border-radius: 8px;
    text-decoration: none;
    color: #FFFFFF !important;
}

.layout > .sidebar a:hover,
.layout > .sidebar a.active {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    border-left: none !important;
    font-weight: bold;
}

.layout > .sidebar a[href="logout.php"],
.layout a[href="logout.php"] {
    background-color: #DC2626 !important;
    color: #FFFFFF !important;
    margin-top: 15px;
    text-align: center;
    font-weight: bold;
    border-radius: 8px;
}

.layout > .content {
    font-size: 16px;
    font-family: inherit;
}

/* PAGE HEADER */
.sale-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.sale-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* SALE FORM PANEL */
.sale-panel {
    background-color: #FFFFFF;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0000000D;
    margin-bottom: 24px;
}

.sale-panel h2 {
    margin: 0 0 20px;
    color: #20253B;
    font-size: 20px;
}

/* FORM FIELDS */
.sale-form {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px 20px;
}

.sale-field {
    min-width: 0;
}

.sale-field.full {
    grid-column: 1 / -1;
}

.sale-field label {
    display: block;
    margin-bottom: 7px;
    color: #20253B;
    font-weight: 600;
}


.sale-field input,
.sale-field select {
    display: block;
    position: relative;
    z-index: 2;
    width: 100%;
    min-height: 44px;
    box-sizing: border-box;
    padding: 11px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background-color: #FFFFFF;
    color: #20253B;
    font-family: inherit;
    font-size: 16px;
    pointer-events: auto;
    cursor: pointer;
}

.sale-field select:disabled {
    background-color: #F1F5F9;
    cursor: not-allowed;
    opacity: .7;
}


.sale-field input[readonly] {
    background-color: #F1F5F9;
    color: #475569;
}

.sale-field input:focus,
.sale-field select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.sale-field input:disabled,
.sale-field select:disabled {
    background-color: #F1F5F9;
    cursor: not-allowed;
    opacity: .7;
}

.field-hint {
    display: block;
    margin-top: 6px;
    color: #64748B;
    font-size: 13px;
}

/* TOTAL DISPLAY */
.total-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    padding: 16px;
    background-color: #EFF6FF;
    border: 1px solid #BFDBFE;
    border-radius: 8px;
}

.total-box span {
    color: #20253B;
    font-weight: 600;
}

.total-box strong {
    color: #1D4ED8;
    font-size: 24px;
}

/* BUTTON */
.sale-btn {
    cursor: pointer;
    padding: 11px 18px;
    border: none;
    border-radius: 6px;
    font-family: inherit;
    font-size: 15px;
    font-weight: 600;
    transition: background-color .2s ease;
    background-color: #1D4ED8;
    color: #FFFFFF;
}

.sale-btn:hover {
    background-color: #1E40AF;
}

.sale-btn:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.sale-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* NOTIFICATIONS */
.alert {
    padding: 12px 14px;
    border-radius: 6px;
    margin-bottom: 15px;
    background-color: #DCFCE7;
    color: #166534;
    border: 1px solid #BBF7D0;
    font-size: 16px;
    opacity: 1;
    transition: opacity .5s ease;
}

.alert.error {
    background-color: #FEE2E2;
    color: #991B1B;
    border-color: #FECACA;
}

.alert.warning {
    background-color: #FEF3C7;
    color: #92400E;
    border-color: #FDE68A;
}

.alert.fade-out {
    opacity: 0;
}

/* RESPONSIVE */
@media (max-width: 800px) {
    .sale-form {
        grid-template-columns: 1fr;
    }

    .sale-field.full {
        grid-column: 1;
    }
}

@media (max-width: 700px) {
    .sale-panel {
        padding: 16px;
    }
}

@media (max-width: 600px) {
    .sale-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .total-box {
        align-items: flex-start;
        flex-direction: column;
    }

    .total-box strong {
        font-size: 22px;
    }
}
</style>

<div class="sale-header">
    <div>
        <h1>Record Sale</h1>
        <p class="muted">
            Record customer purchases and update your branch inventory.
        </p>
    </div>
</div>

<!-- SUCCESS NOTIFICATION -->
<div id="saleNotification"
     class="alert"
     role="status"
     aria-live="polite"
     style="<?= $successMessage === '' ? 'display:none' : '' ?>">
    <?= h($successMessage) ?>
</div>

<!-- ERROR NOTIFICATION -->
<?php if ($errorMessage !== ''): ?>
    <div class="alert error page-alert" role="alert">
        <?= h($errorMessage) ?>
    </div>
<?php endif; ?>

<!-- ASSIGNED BRANCH WARNING -->
<?php if ($branchError !== ''): ?>
    <div class="alert warning page-alert" role="alert">
        <?= h($branchError) ?>
    </div>
<?php endif; ?>

<!-- SALE INFORMATION FORM -->
<section class="sale-panel">
    <h2>Sale Information</h2>

    <form method="POST"
          action="record_sale.php"
          id="recordSaleForm"
          class="sale-form">

        <input type="hidden"
               name="csrf_token"
               value="<?= h($csrf_token) ?>">

        <!-- Assigned Branch -->
        <div class="sale-field">
            <label for="branch_name">Assigned Branch</label>
            <input type="text"
                   id="branch_name"
                   value="<?= h($branchName !== '' ? $branchName : 'No branch assigned') ?>"
                   readonly>
        </div>

        <!-- Payment Method -->
        <div class="sale-field">
            <label for="payment_method">Payment Method *</label>
            <select name="payment_method"
                    id="payment_method"
                    required
                    <?= $branchId === null ? 'disabled' : '' ?>>
                <option value="">Select payment method</option>
                <option value="Cash"
                    <?= (($_POST['payment_method'] ?? '') === 'Cash') ? 'selected' : '' ?>>
                    Cash
                </option>
                <option value="GCash"
                    <?= (($_POST['payment_method'] ?? '') === 'GCash') ? 'selected' : '' ?>>
                    GCash
                </option>
            </select>
        </div>

    
<!-- Product -->
<div class="sale-field full">
    <label for="product_id">Product *</label>

    <select name="product_id"
            id="product_id"
            required
            <?= ($branchId === null || count($products) === 0) ? 'disabled' : '' ?>>

        <option value="">Select product</option>

        <?php foreach ($products as $product): ?>
            <option
                value="<?= (int) $product['id'] ?>"
                data-price="<?= h((string) $product['price']) ?>"
                data-stock="<?= (int) $product['stock_quantity'] ?>"
                <?= ((string)($_POST['product_id'] ?? '') === (string)$product['id']) ? 'selected' : '' ?>
                <?= (int) $product['stock_quantity'] < 1 ? 'disabled' : '' ?>>

                <?= h($product['product_name']) ?>
                - <?= h($product['category']) ?>
                | Stock: <?= (int) $product['stock_quantity'] ?>
                | ₱<?= number_format((float) $product['price'], 2) ?>
            </option>
        <?php endforeach; ?>
    </select>

    <?php if ($branchId === null): ?>
        <small class="field-hint">
            No valid branch was found for this account. Check the account's assigned branch.
        </small>
    <?php elseif (count($products) === 0): ?>
        <small class="field-hint">
            No products were loaded. Check the products table and the PHP product query.
        </small>
    <?php elseif (count($products) > 0): ?>
        <small class="field-hint">
            <?= count($products) ?> product(s) loaded.
            Products with zero stock cannot be selected.
        </small>
    <?php endif; ?>
</div>


        <!-- Quantity -->
        <div class="sale-field">
            <label for="quantity">Quantity *</label>
            <input type="number"
                   name="quantity"
                   id="quantity"
                   min="1"
                   step="1"
                   value="<?= h((string)($_POST['quantity'] ?? '1')) ?>"
                   required
                   <?= $branchId === null ? 'disabled' : '' ?>>

            <small class="field-hint" id="stockHint">
                Select a product to view available stock.
            </small>
        </div>

        <!-- Customer Name -->
        <div class="sale-field">
            <label for="customer_name">Customer Name</label>
            <input type="text"
                   name="customer_name"
                   id="customer_name"
                   maxlength="150"
                   value="<?= h($_POST['customer_name'] ?? '') ?>"
                   placeholder="Enter customer name (optional)"
                   <?= $branchId === null ? 'disabled' : '' ?>>
        </div>

        <!-- Estimated Total -->
        <div class="sale-field full">
            <div class="total-box">
                <span>Estimated Total Amount</span>
                <strong id="totalAmount">₱0.00</strong>
            </div>

            <small class="field-hint">
                The final amount is calculated using the saved product price.
            </small>
        </div>

        <!-- Submit Button -->
        <div class="sale-field full sale-actions">
            <button type="submit"
                    class="sale-btn"
                    id="submitSale"
                    <?= ($branchId === null || count($products) === 0) ? 'disabled' : '' ?>>
                Record Sale
            </button>
        </div>
    </form>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const productSelect = document.getElementById('product_id');
    const quantityInput = document.getElementById('quantity');
    const totalAmount = document.getElementById('totalAmount');
    const stockHint = document.getElementById('stockHint');
    const form = document.getElementById('recordSaleForm');
    const submitButton = document.getElementById('submitSale');

    function updateTotal() {
        const option = productSelect.options[productSelect.selectedIndex];

        if (!option || !option.value) {
            totalAmount.textContent = '₱0.00';
            quantityInput.removeAttribute('max');
            stockHint.textContent =
                'Select a product to view available stock.';
            return;
        }

        const price = Number(option.dataset.price || 0);
        const stock = Number(option.dataset.stock || 0);
        const quantity = Number(quantityInput.value || 0);

        quantityInput.max = stock;

        stockHint.textContent = 'Available stock: ' + stock;

        if (quantity > stock) {
            stockHint.textContent +=
                ' — entered quantity exceeds available stock.';
        }

        totalAmount.textContent = (price * Math.max(quantity, 0))
            .toLocaleString('en-PH', {
                style: 'currency',
                currency: 'PHP'
            });
    }

    productSelect.addEventListener('change', updateTotal);
    quantityInput.addEventListener('input', updateTotal);

    form.addEventListener('submit', function (event) {
        const option = productSelect.options[productSelect.selectedIndex];
        const quantity = Number(quantityInput.value || 0);
        const stock = option ? Number(option.dataset.stock || 0) : 0;

        if (
            !option ||
            !option.value ||
            !Number.isInteger(quantity) ||
            quantity < 1 ||
            quantity > stock
        ) {
            event.preventDefault();

            stockHint.textContent =
                'Select a product and enter a whole-number quantity within available stock.';

            quantityInput.focus();
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Recording Sale...';
    });

    function fadeNotification(element) {
        if (
            !element ||
            element.style.display === 'none' ||
            !element.textContent.trim()
        ) {
            return;
        }

        setTimeout(function () {
            element.classList.add('fade-out');

            setTimeout(function () {
                element.style.display = 'none';
                element.classList.remove('fade-out');
            }, 500);
        }, 2500);
    }

    fadeNotification(document.getElementById('saleNotification'));

    document.querySelectorAll('.page-alert').forEach(fadeNotification);

    updateTotal();
});
</script>

<?php require_once 'includes/footer.php'; ?>



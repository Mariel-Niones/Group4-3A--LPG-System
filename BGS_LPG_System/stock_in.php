
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Staff only.
if (is_owner()) {
    header('Location: owner_db.php');
    exit;
}

$isPopup = isset($_GET['popup']) && $_GET['popup'] === '1';

$page_title = 'Stock In';
$userId = (int) $_SESSION['user_id'];

$branchId = null;
$branchName = 'Branch Not Found';
$products = [];
$errorMessage = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];

$successMessage = $_SESSION['stock_in_success'] ?? '';
unset($_SESSION['stock_in_success']);

/*
|--------------------------------------------------------------------------
| Load Assigned Branch and Products
|--------------------------------------------------------------------------
*/
try {
    $userStmt = $pdo->prepare("
        SELECT branch_name
        FROM users
        WHERE id = ?
          AND role = 'staff'
          AND active = 1
        LIMIT 1
    ");

    $userStmt->execute([$userId]);
    $staffBranchName = $userStmt->fetchColumn();

    // Match the assigned branch by ID or branch name.
    if (
        $staffBranchName !== false &&
        $staffBranchName !== null &&
        trim((string) $staffBranchName) !== ''
    ) {
        $branchStmt = $pdo->prepare("
            SELECT id, branch_name
            FROM branches
            WHERE CAST(id AS CHAR) = ?
               OR branch_name = ?
            LIMIT 1
        ");

        $branchStmt->execute([
            (string) $staffBranchName,
            (string) $staffBranchName
        ]);

        $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

        if ($branch) {
            $branchId = (string) $branch['id'];
            $branchName = $branch['branch_name'];
        }
    }

    // Fallback to the branch stored in the session.
    if ($branchId === null) {
        $sessionBranchId = current_branch();

        if ($sessionBranchId !== null && $sessionBranchId !== '') {
            $branchStmt = $pdo->prepare("
                SELECT id, branch_name
                FROM branches
                WHERE CAST(id AS CHAR) = ?
                LIMIT 1
            ");

            $branchStmt->execute([(string) $sessionBranchId]);
            $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

            if ($branch) {
                $branchId = (string) $branch['id'];
                $branchName = $branch['branch_name'];
            }
        }
    }

    if ($branchId === null) {
        throw new RuntimeException(
            'Your account has no assigned branch. Please contact the owner.'
        );
    }

    // Load the products available in the owner's masterlist.
    $productStmt = $pdo->query("
        SELECT id, product_name, category
        FROM products
        ORDER BY product_name ASC
    ");

    $products = $productStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (RuntimeException $e) {
    $errorMessage = $e->getMessage();

} catch (PDOException $e) {
    error_log('Stock-in loading error: ' . $e->getMessage());

    $errorMessage =
        'Unable to load branch or product information. Please check your database.';
}

/*
|--------------------------------------------------------------------------
| Record Stock In
|--------------------------------------------------------------------------
*/
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['record_stock_in'])
) {
    try {
        // Validate the CSRF token.
        $postedToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($postedToken) ||
            !hash_equals($csrfToken, $postedToken)
        ) {
            throw new RuntimeException(
                'Your session token is invalid. Please refresh and try again.'
            );
        }

        if ($branchId === null) {
            throw new RuntimeException(
                'Your assigned branch could not be found.'
            );
        }

        $productId = filter_var(
            $_POST['pid'] ?? null,
            FILTER_VALIDATE_INT
        );

        $quantity = filter_var(
            $_POST['qty'] ?? null,
            FILTER_VALIDATE_INT
        );

        $supplier = trim((string) ($_POST['supplier'] ?? ''));
        $reference = trim((string) ($_POST['ref'] ?? ''));

        if (
            $productId === false ||
            $productId === null ||
            $productId < 1
        ) {
            throw new RuntimeException('Please select a valid product.');
        }

        if (
            $quantity === false ||
            $quantity === null ||
            $quantity < 1
        ) {
            throw new RuntimeException('Quantity must be at least 1.');
        }

        if (mb_strlen($supplier) > 150) {
            throw new RuntimeException(
                'Supplier name must not exceed 150 characters.'
            );
        }

        if (mb_strlen($reference) > 100) {
            throw new RuntimeException(
                'Delivery reference must not exceed 100 characters.'
            );
        }

        // Verify that the selected product exists.
        $verifyProduct = $pdo->prepare("
            SELECT id
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $verifyProduct->execute([$productId]);

        if ($verifyProduct->fetchColumn() === false) {
            throw new RuntimeException(
                'The selected product does not exist.'
            );
        }

        $pdo->beginTransaction();

        // Lock the existing inventory row, if there is one.
        $inventoryStmt = $pdo->prepare("
            SELECT id
            FROM branch_inventory
            WHERE CAST(branch_id AS CHAR) = ?
              AND CAST(product_id AS CHAR) = ?
            LIMIT 1
            FOR UPDATE
        ");

        $inventoryStmt->execute([
            $branchId,
            (string) $productId
        ]);

        $inventoryId = $inventoryStmt->fetchColumn();

        if ($inventoryId !== false) {
            // Increase existing stock.
            $updateInventory = $pdo->prepare("
                UPDATE branch_inventory
                SET quantity = quantity + ?
                WHERE id = ?
            ");

            $updateInventory->execute([
                $quantity,
                $inventoryId
            ]);

        } else {
            // Create a new inventory record if it does not exist.
            $insertInventory = $pdo->prepare("
                INSERT INTO branch_inventory
                    (product_id, branch_id, quantity, min_stock)
                VALUES (?, ?, ?, 0)
            ");

            $insertInventory->execute([
                $productId,
                $branchId,
                $quantity
            ]);
        }

        // Record the stock-in transaction.
        $insertStockIn = $pdo->prepare("
            INSERT INTO stock_in
                (
                    product_id,
                    branch_id,
                    quantity,
                    supplier,
                    delivery_reference,
                    received_by,
                    received_at
                )
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        $insertStockIn->execute([
            $productId,
            $branchId,
            $quantity,
            $supplier !== '' ? $supplier : null,
            $reference !== '' ? $reference : null,
            $userId
        ]);

        // Save both changes together.
        $pdo->commit();

        $_SESSION['stock_in_success'] =
            'Stock-in recorded successfully. Branch inventory has been updated.';

        // Preserve popup mode after submission.
        header('Location: stock_in.php?popup=1&success=1');
        exit;

    } catch (RuntimeException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $errorMessage = $e->getMessage();

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log('Stock-in transaction error: ' . $e->getMessage());

        $errorMessage =
            'Unable to record stock-in. Please check your database structure and try again.';
    }
}

/*
|--------------------------------------------------------------------------
| Load Header Only for the Normal Page
|--------------------------------------------------------------------------
*/
if (!$isPopup) {
    require_once 'includes/header.php';
}
?>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    padding: 0;
    background: #FFFFFF;
    color: #20253B;
    font-family: Arial, sans-serif;
    font-size: 15px;
}

.stock-in-popup {
    width: 100%;
    padding: 22px;
}

.stock-in-popup h2 {
    margin: 0 0 6px;
    color: #20253B;
    font-size: 22px;
    font-weight: 700;
}

.stock-in-popup .subtitle {
    margin: 0 0 22px;
    color: #64748B;
    font-size: 14px;
    line-height: 1.5;
}

.stock-in-popup .notice {
    margin-bottom: 16px;
    padding: 12px 14px;
    border: 1px solid #BBF7D0;
    border-radius: 7px;
    background: #DCFCE7;
    color: #166534;
    line-height: 1.5;
    transition: opacity .5s ease;
}

.stock-in-popup .notice.error {
    border-color: #FECACA;
    background: #FEE2E2;
    color: #991B1B;
}

.stock-in-popup .notice.fade-out {
    opacity: 0;
}

.stock-in-popup .form-group {
    margin-bottom: 17px;
}

.stock-in-popup label {
    display: block;
    margin-bottom: 7px;
    color: #334155;
    font-size: 14px;
    font-weight: 600;
}

.stock-in-popup input,
.stock-in-popup select {
    display: block;
    width: 100%;
    min-height: 42px;
    padding: 10px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 7px;
    background: #FFFFFF;
    color: #20253B;
    font-family: inherit;
    font-size: 15px;
}

.stock-in-popup input:focus,
.stock-in-popup select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.stock-in-popup input[readonly] {
    background: #F1F5F9;
    color: #475569;
}

.stock-in-popup .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 24px;
}

.stock-in-popup button {
    padding: 11px 17px;
    border: none;
    border-radius: 7px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
}

.stock-in-popup button:disabled {
    opacity: .65;
    cursor: not-allowed;
}

.stock-in-popup .btn-submit {
    background: #1D4ED8;
    color: #FFFFFF;
}

.stock-in-popup .btn-submit:hover {
    background: #1E40AF;
}

.stock-in-popup .btn-cancel {
    background: #E2E8F0;
    color: #334155;
}

.stock-in-popup .btn-cancel:hover {
    background: #CBD5E1;
}

.stock-in-popup .form-error {
    margin-bottom: 16px;
    color: #991B1B;
}

@media (max-width: 500px) {
    .stock-in-popup {
        padding: 16px;
    }

    .stock-in-popup .form-actions {
        flex-direction: column;
    }

    .stock-in-popup button {
        width: 100%;
    }
}
</style>

<div class="stock-in-popup">

    <h2>Stock In</h2>

    <p class="subtitle">
        Record products received by your assigned branch.
    </p>

    <?php if ($successMessage !== ''): ?>
        <div class="notice" id="stockInNotice" role="status">
            <?= h($successMessage) ?>
        </div>
    <?php endif; ?>

    <?php if ($errorMessage !== ''): ?>
        <div class="notice error" id="stockInError" role="alert">
            <?= h($errorMessage) ?>
        </div>
    <?php endif; ?>

    <form method="POST"
          action="<?= $isPopup ? 'stock_in.php?popup=1' : 'stock_in.php' ?>"
          id="stockInForm">

        <input type="hidden"
               name="csrf_token"
               value="<?= h($csrfToken) ?>">

        <div class="form-group">
            <label for="branch_display">Assigned Branch</label>
            <input type="text"
                   id="branch_display"
                   value="<?= h($branchName) ?>"
                   readonly>
        </div>

        <div class="form-group">
            <label for="pid">Product *</label>

            <select id="pid" name="pid" required>
                <option value="">Select a product</option>

                <?php foreach ($products as $product): ?>
                    <option value="<?= h((string) $product['id']) ?>">
                        <?= h($product['product_name']) ?>
                        (<?= h($product['category']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="qty">Quantity Received *</label>

            <input type="number"
                   id="qty"
                   name="qty"
                   min="1"
                   step="1"
                   required
                   placeholder="Enter quantity">
        </div>

        <div class="form-group">
            <label for="supplier">Supplier</label>

            <input type="text"
                   id="supplier"
                   name="supplier"
                   maxlength="150"
                   placeholder="Enter supplier name">
        </div>

        <div class="form-group">
            <label for="ref">Delivery Reference</label>

            <input type="text"
                   id="ref"
                   name="ref"
                   maxlength="100"
                   placeholder="Enter delivery receipt or reference number">
        </div>

        <div class="form-actions">
            <?php if ($isPopup): ?>
                <button type="button"
                        class="btn-cancel"
                        onclick="closeStockInPopup()">
                    Cancel
                </button>
            <?php endif; ?>

            <button type="submit"
                    name="record_stock_in"
                    value="1"
                    class="btn-submit"
                    id="recordStockInButton">
                Record Stock In
            </button>
        </div>

    </form>
</div>

<script>
function closeStockInPopup() {
    if (window.parent && window.parent !== window) {
        window.parent.postMessage(
            { type: 'closeStockInPopup' },
            window.location.origin
        );
    } else {
        window.history.back();
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Preselect the product if a product ID was supplied by the inventory page.
    const params = new URLSearchParams(window.location.search);
    const productId = params.get('pid');
    const productSelect = document.getElementById('pid');

    if (productId && productSelect) {
        productSelect.value = productId;
    }

    // Fade notifications after 2.5 seconds.
    document.querySelectorAll('.stock-in-popup .notice').forEach(function (notice) {
        window.setTimeout(function () {
            notice.classList.add('fade-out');

            window.setTimeout(function () {
                notice.remove();
            }, 500);
        }, 2500);
    });

    // Prevent accidental repeated clicks while submitting.
    const form = document.getElementById('stockInForm');
    const submitButton = document.getElementById('recordStockInButton');

    if (form && submitButton) {
        form.addEventListener('submit', function () {
            if (form.checkValidity()) {
                submitButton.disabled = true;
                submitButton.textContent = 'Recording...';
            }
        });
    }
});
</script>

<?php
if (!$isPopup) {
    require_once 'includes/footer.php';
}
?>

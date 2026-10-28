
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// This page is for staff only.
if (is_owner()) {
    header('Location: owner_db.php');
    exit;
}

$page_title = 'My Branch Products';

$staffId = (int) $_SESSION['user_id'];
$branchId = null;
$inventoryProducts = [];
$pageError = '';

/*
|--------------------------------------------------------------------------
| Find the branch assigned to this staff account.
|--------------------------------------------------------------------------
*/
try {
    $branchStmt = $pdo->prepare("
        SELECT b.id
        FROM users u
        INNER JOIN branches b
            ON (
                CAST(b.id AS CHAR) = CAST(u.branch_name AS CHAR)
                OR b.branch_name = u.branch_name
            )
        WHERE u.id = ?
          AND u.role = 'staff'
          AND u.active = 1
        LIMIT 1
    ");

    $branchStmt->execute([$staffId]);
    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        $pageError = 'Your account has no valid assigned branch. Please contact the owner.';
    } else {
        $branchId = (string) $branch['id'];
    }
} catch (PDOException $e) {
    error_log('Staff branch lookup error: ' . $e->getMessage());
    $pageError = 'Unable to load your assigned branch.';
}

/*
|--------------------------------------------------------------------------
| Load products assigned to this branch.
|--------------------------------------------------------------------------
*/
if ($branchId !== null && $pageError === '') {
    try {
        $inventoryStmt = $pdo->prepare("
            SELECT
                p.id AS product_id,
                p.product_name,
                p.category,
                p.price,
                bi.quantity,
                bi.min_stock
            FROM branch_inventory bi
            INNER JOIN products p
                ON CAST(p.id AS CHAR) = CAST(bi.product_id AS CHAR)
            WHERE CAST(bi.branch_id AS CHAR) = ?
            ORDER BY p.product_name ASC
        ");

        $inventoryStmt->execute([$branchId]);
        $inventoryProducts = $inventoryStmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log('Staff products query error: ' . $e->getMessage());
        $pageError = 'Unable to load branch products. Please check the database structure.';
    }
}

require_once 'includes/header.php';
?>

<style>
/* =====================================
   SIDEBAR — MATCH REGISTERED STAFF
===================================== */
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
    border-left: none !important;
    transition: background-color .2s ease;
}

.layout > .sidebar a:hover,
.layout > .sidebar a.active,
.layout > .sidebar a[aria-current="page"] {
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

/* =====================================
   GENERAL PAGE DESIGN
===================================== */
.staff-products-page {
    padding: 22px;
    color: #20253B;
    font-size: 16px;
    font-family: inherit;
}

.staff-products-page * {
    box-sizing: border-box;
}

.staff-products-page .page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.staff-products-page h2 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.staff-products-page h3 {
    color: #20253B;
}

.staff-products-page .subtitle {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* =====================================
   PANELS
===================================== */
.staff-products-page .panel {
    background-color: #FFFFFF;
    padding: 20px;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 8px #0000000D;
    margin-bottom: 16px;
}

.staff-products-page .toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

/* =====================================
   BUTTONS
===================================== */
.staff-products-page button {
    cursor: pointer;
    padding: 11px 13px;
    border: none;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: background-color .2s ease;
}

.staff-products-page button:disabled {
    opacity: .6;
    cursor: not-allowed;
}

.staff-products-page .btn-primary {
    background-color: #1D4ED8;
    color: #FFFFFF;
    white-space: nowrap;
}

.staff-products-page .btn-primary:hover {
    background-color: #1E40AF;
}

.staff-products-page .btn-stock {
    background-color: #16A34A;
    color: #FFFFFF;
}

.staff-products-page .btn-stock:hover {
    background-color: #15803D;
}

.staff-products-page .btn-secondary {
    background-color: #E2E8F0;
    color: #334155;
}

.staff-products-page .btn-secondary:hover {
    background-color: #CBD5E1;
}

/* =====================================
   INVENTORY TABLE
===================================== */
.staff-products-page .table-wrap {
    width: 100%;
    overflow-x: auto;
}

.staff-products-page table {
    width: 100%;
    min-width: 680px;
    border-collapse: collapse;
    font-family: inherit;
    font-size: 16px;
}

.staff-products-page th,
.staff-products-page td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
}

.staff-products-page th {
    background-color: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    text-align: left;
    text-transform: none;
    letter-spacing: normal;
}

.staff-products-page tbody tr:last-child td {
    border-bottom: none;
}

.staff-products-page .actions {
    text-align: center;
    white-space: nowrap;
}

.staff-products-page .stock-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 20px;
    background-color: #DCFCE7;
    color: #166534;
    font-weight: 600;
    font-size: 13px;
}

.staff-products-page .stock-low {
    background-color: #FEF3C7;
    color: #92400E;
}

.staff-products-page .stock-empty {
    background-color: #FEE2E2;
    color: #991B1B;
}

.staff-products-page .empty-state {
    text-align: center;
    color: #64748B;
    padding: 25px !important;
    font-size: 16px;
}

/* =====================================
   NOTIFICATIONS
===================================== */
.staff-products-page .notice {
    padding: 12px;
    border-radius: 6px;
    margin-bottom: 15px;
    font-size: 16px;
}

.staff-products-page .notice.error {
    background-color: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

/* =====================================
   MODALS
===================================== */
.staff-products-page .modal {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 99999;
    padding: 20px;
    background-color: rgba(15, 23, 42, .60);
    align-items: center;
    justify-content: center;
}

.staff-products-page .modal.show {
    display: flex !important;
}

.staff-products-page .modal-card {
    width: 100%;
    max-width: 950px;
    height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background-color: #FFFFFF;
    border-radius: 14px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
    animation: staffProductsModalIn .18s ease-out;
}

@keyframes staffProductsModalIn {
    from {
        opacity: 0;
        transform: translateY(-12px) scale(.98);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

.staff-products-page .modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    background-color: #122CBB;
    color: #FFFFFF;
    flex-shrink: 0;
}

.staff-products-page .modal-header h3 {
    margin: 0;
    color: #FFFFFF;
    font-size: 18px;
    font-weight: 700;
}

.staff-products-page .close-modal {
    padding: 0 3px;
    border: none;
    background-color: transparent;
    color: #FFFFFF;
    font-size: 28px;
    line-height: 1;
    cursor: pointer;
}

.staff-products-page .close-modal:hover {
    background-color: transparent;
    color: #DBEAFE;
}

.staff-products-page .modal-body {
    flex: 1;
    min-height: 0;
    padding: 0;
}

.staff-products-page .stockin-frame {
    display: block;
    width: 100%;
    height: 100%;
    min-height: 400px;
    border: 0;
}

/* =====================================
   RESPONSIVE LAYOUT
===================================== */
@media (max-width: 700px) {
    .staff-products-page {
        padding: 12px;
    }

    .staff-products-page .page-heading {
        align-items: flex-start;
    }

    .staff-products-page .panel {
        padding: 12px;
    }

    .staff-products-page table {
        min-width: 680px;
    }

    .staff-products-page .modal {
        padding: 10px;
    }

    .staff-products-page .modal-card {
        height: 90vh;
    }
}
</style>

<div class="staff-products-page">

    <div class="page-heading">
        <div>
            <h2>My Branch Products</h2>
            <p class="subtitle">
                View your branch inventory and record incoming stock.
            </p>
        </div>

        <?php if ($branchId !== null): ?>
            <button type="button"
                    class="btn-primary"
                    onclick="openStockIn()">
                + Add Product
            </button>
        <?php endif; ?>
    </div>

    <?php if ($pageError !== ''): ?>
        <div class="notice error">
            <?= h($pageError) ?>
        </div>
    <?php else: ?>

        <div class="panel">
            <div class="toolbar">
                <div>
                    <h3 style="margin:0 0 5px;">Branch Inventory</h3>
                    <span class="subtitle">
                        <?= count($inventoryProducts) ?> product(s) assigned to this branch
                    </span>
                </div>
            </div>

            <div class="table-wrap">
                <table id="branchProductsTable">
                    <thead>
                        <tr>
                            <th>Product Name</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Minimum Stock</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (empty($inventoryProducts)): ?>
                        <tr>
                            <td colspan="6" class="empty-state">
                                No products have been added to this branch yet.
                                Click <strong>Add Product</strong> to open the stock-in page.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($inventoryProducts as $product): ?>
                            <?php
                                $quantity = (int) $product['quantity'];
                                $minStock = (int) $product['min_stock'];

                                if ($quantity <= 0) {
                                    $stockClass = 'stock-empty';
                                    $stockLabel = 'Out of stock';
                                } elseif ($quantity <= $minStock) {
                                    $stockClass = 'stock-low';
                                    $stockLabel = 'Low stock';
                                } else {
                                    $stockClass = '';
                                    $stockLabel = 'In stock';
                                }
                            ?>

                            <tr class="product-row">
                                <td><?= h($product['product_name']) ?></td>
                                <td><?= h($product['category']) ?></td>
                                <td>
                                    ₱<?= number_format((float) $product['price'], 2) ?>
                                </td>
                                <td>
                                    <strong><?= $quantity ?></strong>
                                    <br>
                                    <span class="stock-badge <?= $stockClass ?>">
                                        <?= h($stockLabel) ?>
                                    </span>
                                </td>
                                <td><?= $minStock ?></td>
                                <td class="actions">
                                    <button type="button"
                                            class="btn-stock"
                                            onclick="openStockIn('<?= h((string) $product['product_id']) ?>')">
                                        Stock In
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Stock In Popup -->
<div class="staff-products-page">
    <div class="modal" id="stockInModal" role="dialog"
         aria-modal="true" aria-labelledby="stockInTitle">

        <div class="modal-card">
            <div class="modal-header">
                <h3 id="stockInTitle">Stock In</h3>
                <button type="button"
                        class="close-modal"
                        onclick="closeStockIn()"
                        aria-label="Close">&times;</button>
            </div>

            <div class="modal-body">
                <iframe
                    id="stockInFrame"
                    class="stockin-frame"
                    title="Stock In Form"
                    src="about:blank">
                </iframe>
            </div>
        </div>
    </div>
</div>

<script>
function openStockIn(productId = '') {
    const frame = document.getElementById('stockInFrame');

    let url = 'stock_in.php?popup=1';

    if (productId !== '') {
        url += '&pid=' + encodeURIComponent(productId);
    }

    frame.src = url;
    document.getElementById('stockInModal').classList.add('show');
}

function closeStockIn() {
    document.getElementById('stockInModal').classList.remove('show');

    document.getElementById('stockInFrame').src = 'about:blank';

    window.location.reload();
}

window.addEventListener('message', function(event) {
    if (
        event.origin !== window.location.origin ||
        !event.data ||
        event.data.type !== 'closeStockInPopup'
    ) {
        return;
    }

    closeStockIn();
});

const stockInModal = document.getElementById('stockInModal');

if (stockInModal) {
    stockInModal.addEventListener('click', function(event) {
        if (event.target === stockInModal) {
            closeStockIn();
        }
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>

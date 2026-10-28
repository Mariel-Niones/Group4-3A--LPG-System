
<?php
require_once 'includes/auth.php';

$page_title = 'Dashboard';

$isOwner = is_owner();
$branchId = current_branch();

$totalProducts = 0;
$totalStock = 0;
$totalSalesToday = 0;
$totalTransactionsToday = 0;
$lowStockCount = 0;
$lowStockItems = [];

try {
    // Total registered products
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $totalProducts = (int) $stmt->fetchColumn();

    // Total stock units
    if ($isOwner) {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(quantity), 0)
            FROM branch_inventory
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(quantity), 0)
            FROM branch_inventory
            WHERE branch_id = ?
        ");
        $stmt->execute([$branchId]);
    }

    $totalStock = (int) $stmt->fetchColumn();

    // Today's sales and transaction count
    if ($isOwner) {
        $stmt = $pdo->query("
            SELECT
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COUNT(*) AS total_transactions
            FROM branch_sales
            WHERE DATE(created_at) = CURDATE()
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT
                COALESCE(SUM(total_amount), 0) AS total_sales,
                COUNT(*) AS total_transactions
            FROM branch_sales
            WHERE DATE(created_at) = CURDATE()
              AND branch_id = ?
        ");
        $stmt->execute([$branchId]);
    }

    $salesData = $stmt->fetch(PDO::FETCH_ASSOC);

    $totalSalesToday = (float) ($salesData['total_sales'] ?? 0);
    $totalTransactionsToday = (int) ($salesData['total_transactions'] ?? 0);

    // Low-stock count
    if ($isOwner) {
        $stmt = $pdo->query("
            SELECT COUNT(*)
            FROM branch_inventory
            WHERE quantity <= min_stock
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM branch_inventory
            WHERE quantity <= min_stock
              AND branch_id = ?
        ");
        $stmt->execute([$branchId]);
    }

    $lowStockCount = (int) $stmt->fetchColumn();

    // Low-stock product list
    $sql = "
        SELECT
            p.product_name,
            p.category,
            b.branch_name,
            bi.quantity,
            bi.min_stock
        FROM branch_inventory bi
        INNER JOIN products p ON p.id = bi.product_id
        INNER JOIN branches b ON b.id = bi.branch_id
        WHERE bi.quantity <= bi.min_stock
    ";

    if (!$isOwner) {
        $sql .= " AND bi.branch_id = ?";
    }

    $sql .= "
        ORDER BY bi.quantity ASC, p.product_name ASC
        LIMIT 10
    ";

    $stmt = $pdo->prepare($sql);

    if (!$isOwner) {
        $stmt->execute([$branchId]);
    } else {
        $stmt->execute();
    }

    $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}

require 'includes/header.php';
?>

<style>
/* Match the Products page sidebar colors */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="owner_db.php"],
.layout > .sidebar a[href="owner_db.php"]:hover {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: bold !important;
    border-left: none !important;
    border-radius: 8px;
}

/* Dashboard heading */
.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.dashboard-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.dashboard-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* Summary cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.dashboard-card {
    min-width: 0;
    padding: 20px;
    background-color: #FFFFFF;
    border: 1px solid #7394ee;
    border-radius: 10px;
    box-sizing: border-box;
    cursor: default;
    transition:
        background-color .2s ease,
        border-color .2s ease,
        box-shadow .2s ease,
        transform .2s ease;
}

/* Hover only: cards are not clickable */
.dashboard-card:hover {
    background-color: #F8FAFC;
    border-color: #4283ce;
    box-shadow: 0 4px 12px rgba(15, 76, 129, .10);
    transform: translateY(-3px);
}

.dashboard-card-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    margin-bottom: 14px;
}

.dashboard-card-title {
    margin: 0;
    color: #64748B;
    font-size: 16px;
    font-weight: 600;
}

.dashboard-card-icon {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 42px;
    height: 42px;
    flex-shrink: 0;
    background-color: #EFF6FF;
    border-radius: 8px;
    font-size: 22px;
}

.dashboard-card-value {
    margin: 0 0 6px;
    color: #20253B;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.dashboard-card-note {
    margin: 0;
    color: #64748B;
    font-size: 16px;
}

/* Table heading */
.dashboard-section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}

.dashboard-section-header h2 {
    margin: 0;
    color: #20253B;
    font-size: 20px;
    font-weight: 700;
}

.dashboard-section-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

.low-stock-count {
    display: inline-block;
    padding: 7px 12px;
    background-color: #FEF2F2;
    color: #991B1B;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
}

/* Table */
.dashboard-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font: inherit;
}

.dashboard-table th,
.dashboard-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
}

.dashboard-table th {
    background-color: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.dashboard-table tbody tr:last-child td {
    border-bottom: none;
}

.dashboard-table tbody tr:hover {
    background-color: #F8FAFC;
}

.dashboard-table .empty-row {
    padding: 24px 16px;
    color: #64748B;
    text-align: center;
}

.stock-status {
    display: inline-block;
    padding: 5px 9px;
    background-color: #FEF2F2;
    color: #991B1B;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 600;
}

/* Responsive layout */
@media (max-width: 1100px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }

    .dashboard-table th,
    .dashboard-table td {
        padding: 12px 10px;
    }
}
</style>

<div class="dashboard-page">

    <!-- Page heading -->
    <div class="dashboard-header">
        <div>
            <h1>Dashboard</h1>
            <p class="muted">
                Overview of BGS LPG Store inventory and sales.
            </p>
        </div>
    </div>

    <!-- Summary cards: hover effect only -->
    <div class="dashboard-cards">

        <div class="dashboard-card">
            <div class="dashboard-card-top">
                <p class="dashboard-card-title">Total Products</p>
                <div class="dashboard-card-icon"></div>
            </div>

            <p class="dashboard-card-value">
                <?= number_format($totalProducts) ?>
            </p>

            <p class="dashboard-card-note">
                Registered products
            </p>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-top">
                <p class="dashboard-card-title">Total Stock Units</p>
                <div class="dashboard-card-icon"></div>
            </div>

            <p class="dashboard-card-value">
                <?= number_format($totalStock) ?>
            </p>

            <p class="dashboard-card-note">
                <?= $isOwner ? 'Across all branches' : 'Across your branch' ?>
            </p>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-top">
                <p class="dashboard-card-title">Today's Sales</p>
                <div class="dashboard-card-icon"></div>
            </div>

            <p class="dashboard-card-value">
                ₱<?= number_format($totalSalesToday, 2) ?>
            </p>

            <p class="dashboard-card-note">
                <?= number_format($totalTransactionsToday) ?>
                transaction(s) today
            </p>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-top">
                <p class="dashboard-card-title">Low-Stock Items</p>
                <div class="dashboard-card-icon"></div>
            </div>

            <p class="dashboard-card-value">
                <?= number_format($lowStockCount) ?>
            </p>

            <p class="dashboard-card-note">
                At or below minimum stock
            </p>
        </div>

    </div>

    <!-- Low Stock Items table -->
    <section class="panel">

        <div class="dashboard-section-header">
            <div>
                <h2>Low Stock Items</h2>
                <p class="muted">
                    Products that have reached or fallen below their minimum stock level.
                </p>
            </div>

            <span class="low-stock-count">
                <?= number_format($lowStockCount) ?> item(s)
            </span>
        </div>

        <div class="dashboard-table-wrap">
            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>

                        <?php if ($isOwner): ?>
                            <th>Branch</th>
                        <?php endif; ?>

                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($lowStockItems)): ?>
                        <tr>
                            <td
                                colspan="<?= $isOwner ? 6 : 5 ?>"
                                class="empty-row"
                            >
                                No low-stock items found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($lowStockItems as $item): ?>
                            <tr>
                                <td>
                                    <?= h($item['product_name']) ?>
                                </td>

                                <td>
                                    <?= h($item['category']) ?>
                                </td>

                                <?php if ($isOwner): ?>
                                    <td>
                                        <?= h($item['branch_name']) ?>
                                    </td>
                                <?php endif; ?>

                                <td>
                                    <?= number_format((int)$item['quantity']) ?>
                                </td>

                                <td>
                                    <?= number_format((int)$item['min_stock']) ?>
                                </td>

                                <td>
                                    <span class="stock-status">
                                        Low Stock
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </section>

</div>

<?php require 'includes/footer.php'; ?>

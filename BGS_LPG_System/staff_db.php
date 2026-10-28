
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (is_owner()) {
    header('Location: owner_db.php');
    exit;
}

$page_title = 'Staff Dashboard';

$branchId = null;
$branchName = 'Branch Not Found';

$totalProducts = 0;
$totalStockUnits = 0;
$todaysSales = 0;
$lowStockCount = 0;
$lowStockItems = [];
$dashboardError = '';

try {
    /*
     * STEP 1:
     * Retrieve the assigned branch from the logged-in user's record.
     */
    $userStmt = $pdo->prepare(
        "SELECT branch_name
         FROM users
         WHERE id = ?
         LIMIT 1"
    );

    $userStmt->execute([$_SESSION['user_id']]);
    $staffBranchName = $userStmt->fetchColumn();

    /*
     * STEP 2:
     * Match the assigned branch name or branch ID.
     */
    if (
        $staffBranchName !== false &&
        $staffBranchName !== null &&
        trim((string) $staffBranchName) !== ''
    ) {
        $branchStmt = $pdo->prepare(
            "SELECT id, branch_name
             FROM branches
             WHERE CAST(id AS CHAR) = ?
                OR branch_name = ?
             LIMIT 1"
        );

        $branchStmt->execute([
            (string) $staffBranchName,
            (string) $staffBranchName
        ]);

        $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

        if ($branch) {
            $branchId = $branch['id'];
            $branchName = $branch['branch_name'];
        }
    }

    /*
     * STEP 3:
     * If necessary, fall back to the session branch_id.
     */
    if ($branchId === null) {
        $sessionBranchId = current_branch();

        if (
            $sessionBranchId !== null &&
            $sessionBranchId !== ''
        ) {
            $branchStmt = $pdo->prepare(
                "SELECT id, branch_name
                 FROM branches
                 WHERE CAST(id AS CHAR) = ?
                 LIMIT 1"
            );

            $branchStmt->execute([
                (string) $sessionBranchId
            ]);

            $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

            if ($branch) {
                $branchId = $branch['id'];
                $branchName = $branch['branch_name'];
            }
        }
    }

    /*
     * STEP 4:
     * Load dashboard information for the assigned branch.
     */
    if ($branchId !== null) {

        // Total products available in this branch's inventory.
        $stmt = $pdo->prepare(
            "SELECT COUNT(DISTINCT product_id)
             FROM branch_inventory
             WHERE branch_id = ?"
        );

        $stmt->execute([$branchId]);
        $totalProducts = (int) $stmt->fetchColumn();

        // Total stock units.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(quantity), 0)
             FROM branch_inventory
             WHERE branch_id = ?"
        );

        $stmt->execute([$branchId]);
        $totalStockUnits = (int) $stmt->fetchColumn();

        // Today's recorded sales.
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total_amount), 0)
             FROM branch_sales
             WHERE branch_id = ?
               AND created_at >= CURDATE()
               AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)"
        );

        $stmt->execute([$branchId]);
        $todaysSales = (float) $stmt->fetchColumn();

        // Count low-stock products.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM branch_inventory
             WHERE branch_id = ?
               AND quantity <= min_stock"
        );

        $stmt->execute([$branchId]);
        $lowStockCount = (int) $stmt->fetchColumn();

        // Retrieve low-stock product details.
        $stmt = $pdo->prepare(
            "SELECT
                p.product_name,
                p.category,
                bi.quantity,
                bi.min_stock
             FROM branch_inventory bi
             INNER JOIN products p
                ON p.id = bi.product_id
             WHERE bi.branch_id = ?
               AND bi.quantity <= bi.min_stock
             ORDER BY bi.quantity ASC, p.product_name ASC"
        );

        $stmt->execute([$branchId]);
        $lowStockItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        $dashboardError =
            'Your assigned branch could not be identified. ' .
            'Please ask the owner to check your staff account and branch assignment.';
    }

} catch (PDOException $e) {
    error_log('Staff dashboard error: ' . $e->getMessage());

    $dashboardError =
        'Unable to load dashboard information. ' .
        'Please check your database configuration and table columns.';
}

require_once 'includes/header.php';
?>

<style>
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="staff_db.php"] {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: 700;
    border-left: none !important;
    border-radius: 8px;
}

.staff-dashboard {
    font-size: 16px;
    color: #20253B;
}

.staff-dashboard h1 {
    margin: 0 0 8px;
    font-size: 2em;
    font-weight: 700;
    color: #20253B;
}

.dashboard-subtitle {
    color: #64748B;
    margin: 0 0 24px;
    font-size: 16px;
}

.dashboard-subtitle strong {
    color: #1D4ED8;
}

.dashboard-error {
    margin-bottom: 22px;
    padding: 14px 16px;
    border: 1px solid #FECACA;
    border-radius: 8px;
    background: #FEF2F2;
    color: #991B1B;
    line-height: 1.5;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.dashboard-card {
    background: #FFFFFF;
    border: 1px solid #7394ee;
    border-radius: 12px;
    padding: 22px;
    box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dashboard-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
}

.dashboard-card-label {
    color: #64748B;
    font-size: 15px;
    margin-bottom: 12px;
}

.dashboard-card-value {
    color: #1D4ED8;
    font-size: 28px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.dashboard-section {
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    overflow: hidden;
}

.dashboard-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 20px 22px;
    border-bottom: 1px solid #E2E8F0;
}

.dashboard-section-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: 700;
    color: #20253B;
}

.low-stock-count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    padding: 5px 10px;
    border-radius: 20px;
    background: #FEE2E2;
    color: #991B1B;
    font-size: 14px;
    font-weight: 700;
}

.dashboard-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.dashboard-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 16px;
}

.dashboard-table th,
.dashboard-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #E2E8F0;
    white-space: nowrap;
}

.dashboard-table th {
    background: #F1F5F9;
    color: #334155;
    font-weight: 700;
}

.dashboard-table tbody tr:hover {
    background: #F8FAFC;
}

.dashboard-table tbody tr:last-child td {
    border-bottom: none;
}

.stock-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
}

.stock-status.out {
    background: #FEE2E2;
    color: #991B1B;
}

.stock-status.low {
    background: #FEF3C7;
    color: #92400E;
}

.stock-empty {
    text-align: center !important;
    color: #64748B;
    padding: 30px 16px !important;
    white-space: normal !important;
}

@media (max-width: 1100px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }

    .dashboard-card {
        padding: 18px;
    }

    .staff-dashboard h1 {
        font-size: 1.6em;
    }

    .dashboard-section-header {
        padding: 16px;
    }
}
</style>

<div class="staff-dashboard">

    <h1>Staff Dashboard</h1>

    <p class="dashboard-subtitle">
        Overview of inventory and sales for
        <strong><?= h($branchName) ?></strong>.
    </p>

    <?php if ($dashboardError !== ''): ?>
        <div class="dashboard-error">
            <?= h($dashboardError) ?>
        </div>
    <?php endif; ?>

    <!-- Summary cards: hover effect only, not clickable. -->
    <div class="dashboard-cards">

        <div class="dashboard-card">
            <div class="dashboard-card-label">Total Products</div>
            <div class="dashboard-card-value">
                <?= number_format($totalProducts) ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-label">Total Stock Units</div>
            <div class="dashboard-card-value">
                <?= number_format($totalStockUnits) ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-label">Today's Sales</div>
            <div class="dashboard-card-value">
                ₱<?= number_format($todaysSales, 2) ?>
            </div>
        </div>

        <div class="dashboard-card">
            <div class="dashboard-card-label">Low-Stock Items</div>
            <div class="dashboard-card-value">
                <?= number_format($lowStockCount) ?>
            </div>
        </div>

    </div>

    <!-- Low-stock table -->
    <div class="dashboard-section">

        <div class="dashboard-section-header">
            <h2>Low-Stock Items</h2>

            <span class="low-stock-count">
                <?= number_format($lowStockCount) ?>
            </span>
        </div>

        <div class="dashboard-table-wrapper">

            <table class="dashboard-table">
                <thead>
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Current Stock</th>
                        <th>Minimum Stock</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($branchId === null): ?>

                        <tr>
                            <td colspan="5" class="stock-empty">
                                Your branch inventory cannot be displayed
                                until your branch assignment is corrected.
                            </td>
                        </tr>

                    <?php elseif (empty($lowStockItems)): ?>

                        <tr>
                            <td colspan="5" class="stock-empty">
                                No low-stock items in your branch.
                            </td>
                        </tr>

                    <?php else: ?>

                        <?php foreach ($lowStockItems as $item): ?>
                            <?php
                            $quantity = (int) $item['quantity'];
                            $isOutOfStock = $quantity <= 0;
                            ?>

                            <tr>
                                <td>
                                    <?= h($item['product_name']) ?>
                                </td>

                                <td>
                                    <?= h($item['category']) ?>
                                </td>

                                <td>
                                    <?= number_format($quantity) ?>
                                </td>

                                <td>
                                    <?= number_format(
                                        (int) $item['min_stock']
                                    ) ?>
                                </td>

                                <td>
                                    <?php if ($isOutOfStock): ?>
                                        <span class="stock-status out">
                                            Out of Stock
                                        </span>
                                    <?php else: ?>
                                        <span class="stock-status low">
                                            Low Stock
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>

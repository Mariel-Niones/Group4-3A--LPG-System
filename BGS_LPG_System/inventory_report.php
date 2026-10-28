
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isOwner = is_owner();
$page_title = 'Inventory Report';
$branchId = current_branch();

date_default_timezone_set('Asia/Manila');

$inventoryItems = [];
$pageError = '';

/*
|--------------------------------------------------------------------------
| Reporting Period
|--------------------------------------------------------------------------
*/
$period = $_GET['period'] ?? 'daily';

if (!in_array($period, ['daily', 'weekly', 'monthly'], true)) {
    $period = 'daily';
}

/*
|--------------------------------------------------------------------------
| Owner-Only Branch Search
|--------------------------------------------------------------------------
*/
$branchSearch = $isOwner
    ? trim((string) ($_GET['branch_search'] ?? ''))
    : '';

$today = new DateTimeImmutable('today');

switch ($period) {
    case 'weekly':
        $startDate = $today->modify('monday this week');
        $endDate = $startDate->modify('+6 days');
        break;

    case 'monthly':
        $startDate = $today->modify('first day of this month');
        $endDate = $startDate->modify('last day of this month');
        break;

    default:
        $startDate = $today;
        $endDate = $today;
        break;
}


$periodStart = $startDate->format('Y-m-d');
$periodEnd = $endDate->format('Y-m-d');

$dateRangeLabel = $startDate->format('M d, Y');

if ($period === 'weekly') {
    $dateRangeLabel .= ' – ' . $endDate->format('M d, Y');
} elseif ($period === 'monthly') {
    $dateRangeLabel = $startDate->format('F Y');
}


/*
|--------------------------------------------------------------------------
| Load Current Inventory
|--------------------------------------------------------------------------
| Owners can view inventory across all branches and search by branch name.
| Staff can only view inventory from their assigned branch.
|--------------------------------------------------------------------------
*/
try {
    $sql = "
        SELECT
            b.branch_name,
            p.product_name,
            p.category,
            bi.quantity,
            bi.min_stock
        FROM branch_inventory bi
        INNER JOIN products p
            ON bi.product_id = p.id
        INNER JOIN branches b
            ON bi.branch_id = b.id
    ";

    $params = [];

    if ($isOwner) {
        if ($branchSearch !== '') {
            $sql .= " WHERE b.branch_name LIKE ?";
            $params[] = '%' . $branchSearch . '%';
        }
    } else {
        if ($branchId === null || $branchId === '') {
            throw new RuntimeException(
                'Your account does not have an assigned branch.'
            );
        }

        $sql .= " WHERE bi.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " ORDER BY b.branch_name ASC, p.product_name ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    error_log('Inventory report error: ' . $e->getMessage());

    $pageError = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Unable to load inventory records. Please check your database tables and try again.';
}

/*
|--------------------------------------------------------------------------
| Inventory Summary
|--------------------------------------------------------------------------
*/
$totalRecords = count($inventoryItems);
$totalStockUnits = 0;
$lowStockItems = 0;
$outOfStockItems = 0;

foreach ($inventoryItems as $item) {
    $quantity = (int) $item['quantity'];
    $minimum = (int) $item['min_stock'];

    $totalStockUnits += $quantity;

    if ($quantity <= 0) {
        $outOfStockItems++;
    } elseif ($quantity <= $minimum) {
        $lowStockItems++;
    }
}

require_once 'includes/header.php';
?>

<style>
/* Sidebar */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="inventory_report.php"] {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: 700;
    border-left: none !important;
    border-radius: 8px;
}

/* Main report */
.inventory-report {
    width: 100%;
    max-width: 100%;
    color: #1E293B;
    font-size: 16px;
}

/* Heading */
.report-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 24px;
    margin-bottom: 24px;
}

.report-heading-text {
    flex: 1;
    min-width: 0;
}

.inventory-report h1 {
    margin: 0 0 8px;
    color: #0F172A;
    font-size: 28px;
    font-weight: 700;
    line-height: 1.3;
}

.report-subtitle {
    margin: 0;
    color: #64748B;
    font-size: 16px;
    line-height: 1.5;
}

.report-date-label {
    margin: 7px 0 0;
    color: #64748B;
    font-size: 14px;
}

/* Owner-only branch search */
.branch-search-container {
    display: flex;
    justify-content: center;
    width: 100%;
    margin: 18px 0 24px;
}

.branch-search-form {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    max-width: 500px;
}

.branch-search-form input {
    flex: 1;
    min-width: 0;
    padding: 11px 13px;
    border: 1px solid #CBD5E1;
    border-radius: 8px;
    background: #FFFFFF;
    color: #1E293B;
    font-size: 15px;
    outline: none;
}

.branch-search-form input:focus {
    border-color: #1D4ED8;
    box-shadow: 0 0 0 3px rgba(29, 78, 216, 0.12);
}

.branch-search-form button,
.branch-search-form .clear-search {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 11px 15px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.branch-search-form button {
    background: #1D4ED8;
    color: #FFFFFF;
}

.branch-search-form button:hover {
    background: #1E40AF;
}

.branch-search-form .clear-search {
    background: #E2E8F0;
    color: #334155;
}

.branch-search-form .clear-search:hover {
    background: #CBD5E1;
}

/* Reporting period buttons */
.report-period-options {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    flex-wrap: wrap;
    gap: 8px;
}

.report-period-top {
    flex-shrink: 0;
}

.report-period-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 17px;
    border: 1px solid #7394ee;
    border-radius: 8px;
    background: #FFFFFF;
    color: #475569;
    text-decoration: none;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.4;
    transition: background-color 0.2s, color 0.2s, border-color 0.2s;
}

.report-period-button:hover {
    background: #EFF6FF;
    border-color: #93C5FD;
    color: #1D4ED8;
}

.report-period-button.active {
    background: #1D4ED8;
    border-color: #1D4ED8;
    color: #FFFFFF;
}

/* Summary cards */
.inventory-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 26px;
}

.inventory-summary-card {
    min-width: 0;
    padding: 22px;
    background: #FFFFFF;
    border: 1px solid #7394ee;
    border-radius: 12px;
    box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
}

.summary-label {
    margin-bottom: 12px;
    color: #64748B;
    font-size: 14px;
    font-weight: 600;
}

.summary-value {
    color: #0F172A;
    font-size: 28px;
    line-height: 1.2;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Inventory table */
.inventory-table-panel {
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 2px 5px rgba(15, 23, 42, 0.04);
}

.inventory-table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    padding: 20px 22px;
    border-bottom: 1px solid #E2E8F0;
}

.inventory-table-header h2 {
    margin: 0;
    color: #0F172A;
    font-size: 19px;
    font-weight: 700;
}

.inventory-table-header p {
    margin: 5px 0 0;
    color: #64748B;
    font-size: 14px;
}

.inventory-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 16px;
}

.inventory-table thead {
    background: #F8FAFC;
}

.inventory-table th {
    padding: 15px 18px;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    text-align: left;
    text-transform: uppercase;
    white-space: nowrap;
}

.inventory-table td {
    padding: 16px 18px;
    color: #334155;
    border-top: 1px solid #E2E8F0;
    vertical-align: middle;
}

.inventory-table tbody tr:hover {
    background: #F8FAFC;
}

.inventory-table .number-cell {
    font-weight: 600;
    white-space: nowrap;
}

/* Stock status */
.stock-status {
    display: inline-block;
    padding: 6px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;
}

.status-in-stock {
    color: #166534;
    background: #DCFCE7;
}

.status-low-stock {
    color: #92400E;
    background: #FEF3C7;
}

.status-out-of-stock {
    color: #991B1B;
    background: #FEE2E2;
}

/* Empty and error states */
.inventory-empty,
.inventory-error {
    padding: 35px 20px;
    text-align: center;
    color: #64748B;
}

.inventory-error {
    color: #991B1B;
    background: #FEF2F2;
}

.inventory-empty strong {
    display: block;
    margin-bottom: 6px;
    color: #334155;
    font-size: 16px;
}

/* Responsive */
@media (max-width: 1100px) {
    .inventory-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .report-heading {
        align-items: stretch;
        flex-direction: column;
        gap: 16px;
    }

    .report-period-top {
        justify-content: flex-start;
        width: 100%;
    }

    .report-period-button {
        flex: 1;
        min-width: 0;
        padding: 10px 12px;
    }

    .branch-search-form {
        flex-wrap: wrap;
    }

    .branch-search-form input {
        width: 100%;
    }
}

@media (max-width: 600px) {
    .inventory-report h1 {
        font-size: 24px;
    }

    .inventory-summary {
        grid-template-columns: 1fr;
    }

    .inventory-summary-card {
        padding: 18px;
    }

    .inventory-table th,
    .inventory-table td {
        padding: 12px;
    }
}
</style>

<div class="inventory-report">

    <!-- Report Heading -->
    <div class="report-heading">
        <div class="report-heading-text">
            <h1>Inventory Report</h1>

            <p class="report-subtitle">
                <?php if ($isOwner): ?>
                    View inventory levels and stock availability across all branches.
                <?php else: ?>
                    View inventory levels and stock availability for your assigned branch.
                <?php endif; ?>
            </p>

            
<p class="report-date-label">
    <?= h($dateRangeLabel) ?>
</p>

        </div>

        <nav
            class="report-period-options report-period-top"
            aria-label="Select report period"
        >
            <?php
            $periodUrl = 'inventory_report.php?';

            if ($isOwner && $branchSearch !== '') {
                $periodUrl .= 'branch_search=' . urlencode($branchSearch) . '&';
            }
            ?>

            <a
                    href="<?= h($periodUrl . 'period=daily') ?>"
                    class="report-period-button <?= $period === 'daily' ? 'active' : '' ?>"
                    <?= $period === 'daily' ? 'aria-current="page"' : '' ?>
                >Daily</a>

            <a
                href="<?= h($periodUrl . 'period=weekly') ?>"
                class="report-period-button <?= $period === 'weekly' ? 'active' : '' ?>"
                <?= $period === 'weekly' ? 'aria-current="page"' : '' ?>
            >Weekly</a>

            <a
                href="<?= h($periodUrl . 'period=monthly') ?>"
                class="report-period-button <?= $period === 'monthly' ? 'active' : '' ?>"
                <?= $period === 'monthly' ? 'aria-current="page"' : '' ?>
            >Monthly</a>
        </nav>
    </div>

    <!-- Owner-Only Branch Search -->
    <?php if ($isOwner && $pageError === ''): ?>
        <div class="branch-search-container">
            <form
                method="GET"
                action="inventory_report.php"
                class="branch-search-form"
            >
                <input
                    type="hidden"
                    name="period"
                    value="<?= h($period) ?>"
                >

                <input
                    type="search"
                    name="branch_search"
                    placeholder="Search branch name..."
                    aria-label="Search branch name"
                    value="<?= h($branchSearch) ?>"
                >

                <button type="submit">Search</button>

                <?php if ($branchSearch !== ''): ?>
                    <a
                        href="inventory_report.php?period=<?= urlencode($period) ?>"
                        class="clear-search"
                    >Clear</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>

        <div class="inventory-table-panel">
            <div class="inventory-error">
                <?= h($pageError) ?>
            </div>
        </div>

    <?php else: ?>

        <!-- Summary Cards -->
        <div class="inventory-summary">

            <div class="inventory-summary-card">
                <div class="summary-label">Inventory Records</div>
                <div class="summary-value">
                    <?= number_format($totalRecords) ?>
                </div>
            </div>

            <div class="inventory-summary-card">
                <div class="summary-label">Total Stock Units</div>
                <div class="summary-value">
                    <?= number_format($totalStockUnits) ?>
                </div>
            </div>

            <div class="inventory-summary-card">
                <div class="summary-label">Low-Stock Items</div>
                <div class="summary-value">
                    <?= number_format($lowStockItems) ?>
                </div>
            </div>

            <div class="inventory-summary-card">
                <div class="summary-label">Out-of-Stock Items</div>
                <div class="summary-value">
                    <?= number_format($outOfStockItems) ?>
                </div>
            </div>

        </div>

        <!-- Inventory Details Table -->
        <section class="inventory-table-panel">

            <div class="inventory-table-header">
                <div>
                    <h2>Inventory Details</h2>
                    <p>
                        Current stock status
                        <?php if ($isOwner): ?>
                            by branch
                        <?php endif; ?>
                    </p>
                </div>
            </div>

            <?php if (empty($inventoryItems)): ?>

                <div class="inventory-empty">
                    <strong>No inventory records found.</strong>

                    <?php if ($isOwner && $branchSearch !== ''): ?>
                        No branches matched
                        "<?= h($branchSearch) ?>".
                        Try another branch name or clear the search.
                    <?php else: ?>
                        Inventory records will appear here once stock is assigned to products.
                    <?php endif; ?>
                </div>

            <?php else: ?>

                <div class="inventory-table-wrapper">
                    <table class="inventory-table">

                        <thead>
                            <tr>
                                <?php if ($isOwner): ?>
                                    <th>Branch Name</th>
                                <?php endif; ?>

                                <th>Product Name</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Minimum Stock</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($inventoryItems as $item): ?>
                                <?php
                                $quantity = (int) $item['quantity'];
                                $minimum = (int) $item['min_stock'];

                                if ($quantity <= 0) {
                                    $statusText = 'Out of Stock';
                                    $statusClass = 'status-out-of-stock';
                                } elseif ($quantity <= $minimum) {
                                    $statusText = 'Low Stock';
                                    $statusClass = 'status-low-stock';
                                } else {
                                    $statusText = 'In Stock';
                                    $statusClass = 'status-in-stock';
                                }
                                ?>

                                <tr>
                                    <?php if ($isOwner): ?>
                                        <td><?= h($item['branch_name']) ?></td>
                                    <?php endif; ?>

                                    <td><?= h($item['product_name']) ?></td>
                                    <td><?= h($item['category']) ?></td>

                                    <td class="number-cell">
                                        <?= number_format($quantity) ?>
                                    </td>

                                    <td class="number-cell">
                                        <?= number_format($minimum) ?>
                                    </td>

                                    <td>
                                        <span class="stock-status <?= $statusClass ?>">
                                            <?= h($statusText) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>

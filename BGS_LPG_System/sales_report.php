
<?php
require_once 'includes/auth.php';

if (empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$isOwner = is_owner();
$page_title = 'Sales Report';
$branchId = current_branch();

date_default_timezone_set('Asia/Manila');

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
$startDate = $today;
$endDate = $today->modify('+1 day');

if ($period === 'weekly') {
    // Monday through the following Monday.
    $startDate = $today->modify('monday this week');
    $endDate = $startDate->modify('+1 week');
} elseif ($period === 'monthly') {
    // First day of this month through the first day of next month.
    $startDate = $today->modify('first day of this month');
    $endDate = $startDate->modify('first day of next month');
}

$dateRangeLabel = $startDate->format('M d, Y');

if ($period === 'weekly') {
    $dateRangeLabel .= ' – ' . $endDate->modify('-1 day')->format('M d, Y');
} elseif ($period === 'monthly') {
    $dateRangeLabel = $startDate->format('F Y');
}

/*
|--------------------------------------------------------------------------
| Load Sales Transactions
|--------------------------------------------------------------------------
*/
$transactions = [];
$totalSales = 0;
$totalTransactions = 0;
$pageError = '';

try {
    /*
     * Owners can view sales across all branches and search by branch name.
     * Staff can only view sales from their assigned branch.
     */

    $sql = "
        SELECT
            bs.id,
            bs.branch_id,
            bs.customer_name,
            bs.payment_method,
            bs.total_amount,
            bs.created_at,
            b.branch_name,
            u.staff_name
        FROM branch_sales bs
        LEFT JOIN branches b ON b.id = bs.branch_id
        LEFT JOIN users u ON u.id = bs.user_id
        WHERE bs.created_at >= ?
          AND bs.created_at < ?
    ";

    $params = [
        $startDate->format('Y-m-d 00:00:00'),
        $endDate->format('Y-m-d 00:00:00')
    ];

    if ($isOwner) {
        if ($branchSearch !== '') {
            $sql .= " AND b.branch_name LIKE ?";
            $params[] = '%' . $branchSearch . '%';
        }
    } else {
        if ($branchId === null || $branchId === '') {
            throw new RuntimeException(
                'Your account does not have an assigned branch.'
            );
        }

        $sql .= " AND bs.branch_id = ?";
        $params[] = $branchId;
    }

    $sql .= " ORDER BY bs.created_at DESC, bs.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($transactions as $transaction) {
        $totalSales += (float) $transaction['total_amount'];
    }

    $totalTransactions = count($transactions);

} catch (Throwable $e) {
    error_log('Sales report error: ' . $e->getMessage());

    $pageError = $e instanceof RuntimeException
        ? $e->getMessage()
        : 'Unable to load the sales report. Please check your database tables and try again.';
}

require_once 'includes/header.php';
?>

<style>
/* Sidebar */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="sales_report.php"] {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: 700;
    border-left: none !important;
    border-radius: 8px;
}

/* Main report */
.sales-report {
    color: #20253B;
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

.sales-report h1 {
    margin: 0 0 8px;
    color: #20253B;
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

/* Period buttons */
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
.sales-summary-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.sales-summary-card {
    padding: 22px 24px;
    background: #FFFFFF;
    border: 1px solid #7394ee;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.sales-summary-label {
    margin: 0 0 12px;
    color: #64748B;
    font-size: 14px;
    font-weight: 600;
}

.sales-summary-value {
    margin: 0;
    color: #1E293B;
    font-size: 28px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

/* Transactions table */
.sales-table-panel {
    overflow: hidden;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
}

.sales-table-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 20px 24px;
    border-bottom: 1px solid #E2E8F0;
}

.sales-table-header h2 {
    margin: 0;
    color: #20253B;
    font-size: 19px;
    font-weight: 700;
}

.sales-table-count {
    color: #64748B;
    font-size: 14px;
}

.sales-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.sales-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 850px;
}

.sales-table th {
    padding: 15px 18px;
    background: #F8FAFC;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
    text-align: left;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.sales-table td {
    padding: 16px 18px;
    border-top: 1px solid #E2E8F0;
    color: #334155;
    font-size: 14px;
    vertical-align: middle;
}

.sales-table tbody tr:hover {
    background: #F8FAFC;
}

.sales-table .amount-cell {
    color: #166534;
    font-weight: 700;
    white-space: nowrap;
}

.sales-table .empty-cell {
    padding: 42px 20px;
    color: #64748B;
    text-align: center;
}

/* Error notice */
.report-notice {
    margin-bottom: 20px;
    padding: 14px 16px;
    border: 1px solid #FECACA;
    border-radius: 8px;
    background: #FEF2F2;
    color: #991B1B;
    font-size: 14px;
}

/* Responsive */
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

    .sales-summary-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .sales-summary-card {
        padding: 18px;
    }

    .sales-summary-value {
        font-size: 25px;
    }

    .sales-table-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 6px;
        padding: 18px;
    }
}
</style>

<div class="sales-report">

    <!-- Report Heading -->
    <div class="report-heading">
        <div class="report-heading-text">
            <h1>Sales Report</h1>

            <p class="report-subtitle">
                <?php if ($isOwner): ?>
                    Overview of recorded sales across all branches.
                <?php else: ?>
                    Overview of recorded sales for your assigned branch.
                <?php endif; ?>
            </p>

            <?php if ($pageError === ''): ?>
                <p class="report-date-label">
                    <?= h($dateRangeLabel) ?>
                </p>
            <?php endif; ?>
        </div>

        <?php if ($pageError === ''): ?>
            <nav
                class="report-period-options report-period-top"
                aria-label="Select report period"
            >
                <?php
                $periodUrl = 'sales_report.php?';

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
        <?php endif; ?>
    </div>

    <!-- Owner-Only Branch Search -->
    <?php if ($isOwner && $pageError === ''): ?>
        <div class="branch-search-container">
            <form
                method="GET"
                action="sales_report.php"
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
                        href="sales_report.php?period=<?= urlencode($period) ?>"
                        class="clear-search"
                    >Clear</a>
                <?php endif; ?>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($pageError !== ''): ?>

        <div class="report-notice">
            <?= h($pageError) ?>
        </div>

    <?php else: ?>

        <!-- Summary Cards -->
        <div class="sales-summary-grid">

            <div class="sales-summary-card">
                <p class="sales-summary-label">Total Sales</p>

                <p class="sales-summary-value">
                    ₱<?= number_format($totalSales, 2) ?>
                </p>
            </div>

            <div class="sales-summary-card">
                <p class="sales-summary-label">Total Transactions</p>

                <p class="sales-summary-value">
                    <?= number_format($totalTransactions) ?>
                </p>
            </div>

        </div>

        <!-- Sales Transactions Table -->
        <section class="sales-table-panel">

            <div class="sales-table-header">
                <h2>Sales Transactions</h2>

                <span class="sales-table-count">
                    <?= number_format($totalTransactions) ?>
                    <?= $totalTransactions === 1 ? 'transaction' : 'transactions' ?>
                </span>
            </div>

            <div class="sales-table-wrapper">
                <table class="sales-table">

                    <thead>
                        <tr>
                            <th>Sale ID</th>

                            <?php if ($isOwner): ?>
                                <th>Branch</th>
                            <?php endif; ?>

                            <th>Customer</th>
                            <th>Payment Method</th>
                            <th>Total Amount</th>
                            <th>Recorded By</th>
                            <th>Date &amp; Time</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td
                                    colspan="<?= $isOwner ? 7 : 6 ?>"
                                    class="empty-cell"
                                >
                                    <?php if ($isOwner && $branchSearch !== ''): ?>
                                        No sales found for a branch matching
                                        "<?= h($branchSearch) ?>"
                                        during this reporting period.
                                        Try another branch name or clear the search.
                                    <?php else: ?>
                                        No sales transactions were recorded
                                        during this period.
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>

                            <?php foreach ($transactions as $sale): ?>
                                <tr>
                                    <td>#<?= h($sale['id']) ?></td>

                                    <?php if ($isOwner): ?>
                                        <td>
                                            <?= h($sale['branch_name'] ?? 'Unknown Branch') ?>
                                        </td>
                                    <?php endif; ?>

                                    <td>
                                        <?php
                                        $customerName = trim(
                                            (string) ($sale['customer_name'] ?? '')
                                        );
                                        ?>
                                        <?= h(
                                            $customerName !== ''
                                                ? $customerName
                                                : 'Walk-in Customer'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $paymentMethod = trim(
                                            (string) ($sale['payment_method'] ?? '')
                                        );
                                        ?>
                                        <?= h(
                                            $paymentMethod !== ''
                                                ? ucfirst($paymentMethod)
                                                : 'Not specified'
                                        ) ?>
                                    </td>

                                    <td class="amount-cell">
                                        ₱<?= number_format(
                                            (float) $sale['total_amount'],
                                            2
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $staffName = trim(
                                            (string) ($sale['staff_name'] ?? '')
                                        );
                                        ?>
                                        <?= h(
                                            $staffName !== ''
                                                ? $staffName
                                                : 'Unknown'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?php
                                        $timestamp = strtotime($sale['created_at']);
                                        ?>
                                        <?= $timestamp !== false
                                            ? h(date('M d, Y h:i A', $timestamp))
                                            : 'Unknown' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                        <?php endif; ?>
                    </tbody>

                </table>
            </div>

        </section>

    <?php endif; ?>

</div>

<?php require_once 'includes/footer.php'; ?>

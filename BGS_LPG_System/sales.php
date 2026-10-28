
<?php
require_once 'includes/auth.php';

if (!is_owner()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Sales Monitoring';

function sales_escape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Load branches for the filter.
$branches = $pdo->query(
    "SELECT id, branch_name FROM branches ORDER BY branch_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Filters
$branch_filter = trim((string)($_GET['branch_id'] ?? 'all'));
$date_from = trim((string)($_GET['date_from'] ?? ''));
$date_to = trim((string)($_GET['date_to'] ?? ''));
$search = trim((string)($_GET['search'] ?? ''));

// Validate branch filter.
$valid_branch_ids = array_map(
    static fn($branch) => (string)$branch['id'],
    $branches
);

if (
    $branch_filter !== 'all' &&
    !in_array($branch_filter, $valid_branch_ids, true)
) {
    $branch_filter = 'all';
}

// Validate dates.
if (
    $date_from !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_from)
) {
    $date_from = '';
}

if (
    $date_to !== '' &&
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_to)
) {
    $date_to = '';
}

if ($date_from !== '' && $date_to !== '' && $date_from > $date_to) {
    $date_from = '';
    $date_to = '';
}

$conditions = [];
$params = [];

if ($branch_filter !== 'all') {
    $conditions[] = 'bs.branch_id = ?';
    $params[] = $branch_filter;
}

if ($date_from !== '') {
    $conditions[] = 'bs.created_at >= ?';
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to !== '') {
    $conditions[] = 'bs.created_at < DATE_ADD(?, INTERVAL 1 DAY)';
    $params[] = $date_to;
}

if ($search !== '') {
    $conditions[] = "(
        CAST(bs.id AS CHAR) LIKE ?
        OR COALESCE(bs.customer_name, '') LIKE ?
        OR COALESCE(b.branch_name, '') LIKE ?
        OR COALESCE(u.staff_name, '') LIKE ?
        OR COALESCE(bs.payment_method, '') LIKE ?
    )";

    $keyword = '%' . $search . '%';

    for ($i = 0; $i < 5; $i++) {
        $params[] = $keyword;
    }
}

$where_sql = $conditions
    ? 'WHERE ' . implode(' AND ', $conditions)
    : '';

// Filtered totals.
$summary_stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS transaction_count,
        COALESCE(SUM(bs.total_amount), 0) AS total_sales
    FROM branch_sales bs
    LEFT JOIN branches b ON b.id = bs.branch_id
    LEFT JOIN users u ON u.id = bs.user_id
    $where_sql
");
$summary_stmt->execute($params);
$summary = $summary_stmt->fetch(PDO::FETCH_ASSOC);

// Today's sales across all branches, independent of the filters.
$today_stmt = $pdo->query("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM branch_sales
    WHERE created_at >= CURDATE()
      AND created_at < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");
$today_sales = (float)$today_stmt->fetchColumn();

// Transaction list.
$sales_stmt = $pdo->prepare("
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
    $where_sql
    ORDER BY bs.created_at DESC, bs.id DESC
");
$sales_stmt->execute($params);
$sales = $sales_stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
/* Match the Branch Inventories content design */
.layout > .content {
    font-size: 16px;
}

/* Sidebar */
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a {
    color: #FFFFFF !important;
}

.layout > .sidebar a[href="sales.php"],
.layout > .sidebar a[href="sales.php"]:hover {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: bold !important;
    border-left: none !important;
    border-radius: 8px;
}

/* Logout */
.layout > .sidebar a[href="logout.php"],
.layout a[href="logout.php"] {
    background-color: #DC2626 !important;
    color: #FFFFFF !important;
    margin-top: 15px;
    text-align: center;
    font-weight: bold;
    border-radius: 8px;
}

.layout > .sidebar a[href="logout.php"]:hover,
.layout a[href="logout.php"]:hover {
    background-color: #991B1B !important;
}

/* Heading */
.sales-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.sales-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.sales-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* Summary cards */
.sales-summary {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.sales-summary-card {
    background: #FFFFFF;
    padding: 22px;
    border-radius: 12px;
    border: 1px solid #7394ee;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .07);
    min-width: 0;
}

.sales-summary-label {
    color: #64748B;
    font-size: 15px;
    margin-bottom: 12px;
}

.sales-summary-value {
    color: #20253B;
    font-size: 27px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.sales-summary-card:first-child .sales-summary-value {
    color: #1D4ED8;
}

/* Panels */
.sales-panel {
    background: #FFFFFF;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .07);
    margin-bottom: 24px;
}

.sales-panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.sales-panel-heading h2 {
    margin: 0;
    color: #20253B;
    font-size: 1.17em;
    font-weight: 600;
}

/* Buttons */
.sales-btn {
    cursor: pointer;
    padding: 8px 13px;
    border: none;
    border-radius: 6px;
    color: #FFFFFF;
    text-decoration: none;
    display: inline-block;
    font-family: inherit;
    font-size: 14px;
    font-weight: 600;
    transition: background-color .2s ease;
}

.sales-btn-primary {
    background-color: #1D4ED8;
}

.sales-btn-primary:hover {
    background-color: #1E40AF;
}

.sales-btn-reset {
    background-color: #E2E8F0;
    color: #334155;
}

.sales-btn-reset:hover {
    background-color: #CBD5E1;
}

/* Filters */
.sales-toolbar {
    display: flex;
    align-items: flex-end;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.sales-filters {
    display: grid;
    grid-template-columns: repeat(4, minmax(130px, 1fr));
    gap: 14px;
    width: 100%;
}

.sales-field label {
    display: block;
    margin-bottom: 6px;
    font-weight: 600;
    color: #20253B;
    font-size: 15px;
}

.sales-field input,
.sales-field select {
    width: 100%;
    box-sizing: border-box;
    padding: 10px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #FFFFFF;
    color: #20253B;
    font-family: inherit;
    font-size: 16px;
    min-height: 42px;
}

.sales-field input:focus,
.sales-field select:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.sales-filter-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 16px;
}

/* Table */
.sales-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.sales-table {
    width: 100%;
    border-collapse: collapse;
    font: inherit;
}

.sales-table th,
.sales-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
}

.sales-table th {
    background: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    text-align: left;
    white-space: nowrap;
}

.sales-table tr:last-child td {
    border-bottom: none;
}

.sales-table tbody tr:hover {
    background: #F8FAFC;
}

.sales-table .amount {
    text-align: right;
    color: #166534;
    font-weight: 600;
    white-space: nowrap;
}

.sales-empty {
    text-align: center;
    padding: 24px 12px !important;
    color: #64748B;
}

.sales-count {
    margin-top: 15px;
    color: #64748B;
    font-size: 14px;
}

/* Responsive */
@media (max-width: 1100px) {
    .sales-filters {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .sales-summary {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 700px) {
    .sales-summary {
        grid-template-columns: 1fr;
    }

    .sales-panel {
        padding: 16px;
    }

    .sales-table {
        min-width: 900px;
    }

    .sales-table th,
    .sales-table td {
        padding: 12px 10px;
    }
}

@media (max-width: 480px) {
    .sales-filters {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Page heading -->
<div class="sales-header">
    <div>
        <h1>Sales Monitoring</h1>
        <p class="muted">
            View and monitor sales transactions across all branches.
        </p>
    </div>
</div>

<!-- Summary cards -->
<div class="sales-summary">
    <div class="sales-summary-card">
        <div class="sales-summary-label">
            Total Sales (Filtered Results)
        </div>
        <div class="sales-summary-value">
            ₱<?= number_format((float)$summary['total_sales'], 2) ?>
        </div>
    </div>

    <div class="sales-summary-card">
        <div class="sales-summary-label">
            Transactions (Filtered Results)
        </div>
        <div class="sales-summary-value">
            <?= number_format((int)$summary['transaction_count']) ?>
        </div>
    </div>

    <div class="sales-summary-card">
        <div class="sales-summary-label">
            Today's Sales — All Branches
        </div>
        <div class="sales-summary-value">
            ₱<?= number_format($today_sales, 2) ?>
        </div>
    </div>
</div>

<!-- Search and filter panel -->
<section class="sales-panel">
    <div class="sales-panel-heading">
        <h2>Search and Filter Sales</h2>
    </div>

    <form method="get" action="sales.php">
        <div class="sales-filters">

            <div class="sales-field">
                <label for="branch_id">Branch</label>
                <select name="branch_id" id="branch_id">
                    <option value="all"
                        <?= $branch_filter === 'all' ? 'selected' : '' ?>>
                        All Branches
                    </option>

                    <?php foreach ($branches as $branch): ?>
                        <option
                            value="<?= sales_escape($branch['id']) ?>"
                            <?= $branch_filter === (string)$branch['id'] ? 'selected' : '' ?>>
                            <?= sales_escape($branch['branch_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sales-field">
                <label for="date_from">From Date</label>
                <input
                    type="date"
                    name="date_from"
                    id="date_from"
                    value="<?= sales_escape($date_from) ?>">
            </div>

            <div class="sales-field">
                <label for="date_to">To Date</label>
                <input
                    type="date"
                    name="date_to"
                    id="date_to"
                    value="<?= sales_escape($date_to) ?>">
            </div>

            <div class="sales-field">
                <label for="search">Search</label>
                <input
                    type="search"
                    name="search"
                    id="search"
                    placeholder="Sale ID, staff, customer..."
                    value="<?= sales_escape($search) ?>">
            </div>

        </div>

        <div class="sales-filter-actions">
            <button type="submit" class="sales-btn sales-btn-primary">
                Apply Filters
            </button>

            <a href="sales.php" class="sales-btn sales-btn-reset">
                Reset
            </a>
        </div>
    </form>
</section>

<!-- Sales table -->
<section class="sales-panel">
    <div class="sales-panel-heading">
        <h2>Branch Sales Transactions</h2>
        <span>
            Total Records:
            <strong><?= number_format(count($sales)) ?></strong>
        </span>
    </div>

    <div class="sales-table-wrap">
        <table class="sales-table">
            <thead>
                <tr>
                   
                    <th>Branch</th>
                    <th>Customer</th>
                    <th>Recorded By</th>
                    <th>Payment Method</th>
                    <th style="text-align:right;">Total Amount</th>
                    <th>Date &amp; Time</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!empty($sales)): ?>
                    <?php foreach ($sales as $sale): ?>
                        <tr>
                            

                            <td>
                                <?= sales_escape(
                                    $sale['branch_name'] ?? 'Unknown Branch'
                                ) ?>
                            </td>

                            <td>
                                <?= sales_escape(
                                    $sale['customer_name'] ?: 'Walk-in Customer'
                                ) ?>
                            </td>

                            <td>
                                <?= sales_escape(
                                    $sale['staff_name'] ?? 'Unknown Staff'
                                ) ?>
                            </td>

                            <td>
                                <?= sales_escape($sale['payment_method']) ?>
                            </td>

                            <td class="amount">
                                ₱<?= number_format(
                                    (float)$sale['total_amount'],
                                    2
                                ) ?>
                            </td>

                            <td>
                                <?php if (!empty($sale['created_at'])): ?>
                                    <?= sales_escape(date(
                                        'M d, Y h:i A',
                                        strtotime($sale['created_at'])
                                    )) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="sales-empty">
                            No sales transactions found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="sales-count">
        Showing <?= number_format(count($sales)) ?> transaction(s).
    </div>
</section>

<?php require 'includes/footer.php'; ?>

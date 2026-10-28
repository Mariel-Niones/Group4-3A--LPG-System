
<?php
require_once 'includes/auth.php';

if (!is_owner()) {
    header('Location: index.php');
    exit;
}

$page_title = 'Sales History';

/* Get all recorded sales transactions. */
$sql = "
    SELECT
        bs.id,
        bs.customer_name,
        bs.payment_method,
        bs.total_amount,
        bs.created_at,
        b.branch_name,
        u.staff_name
    FROM branch_sales bs
    LEFT JOIN branches b
        ON b.id = bs.branch_id
    LEFT JOIN users u
        ON u.id = bs.user_id
    ORDER BY bs.created_at DESC, bs.id DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

require 'includes/header.php';
?>

<style>
.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a[href="sales_history.php"] {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: 700;
    border-left: none !important;
    border-radius: 8px;
}

.sales-history-page {
    font-size: 16px;
    color: #20253B;
}

.sales-history-page h1 {
    margin: 0 0 24px;
    font-size: 2em;
    font-weight: 700;
    color: #20253B;
}

.sales-history-table-container {
    width: 100%;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
    border-radius: 10px;
    overflow: hidden;
}

.sales-history-table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.sales-history-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 16px;
}

.sales-history-table th,
.sales-history-table td {
    padding: 14px 16px;
    text-align: left;
    border-bottom: 1px solid #E2E8F0;
    white-space: nowrap;
}

.sales-history-table th {
    background: #F1F5F9;
    color: #334155;
    font-weight: 700;
}

.sales-history-table tbody tr:hover {
    background: #F8FAFC;
}

.sales-history-table tbody tr:last-child td {
    border-bottom: none;
}

.sales-history-table .amount {
    color: #15803D;
    font-weight: 700;
}

.sales-history-table .empty-row {
    padding: 28px 16px;
    text-align: center;
    color: #64748B;
}

@media (max-width: 768px) {
    .sales-history-page h1 {
        font-size: 1.6em;
    }
}
</style>

<div class="sales-history-page">

    <h1>Sales History</h1>

    <div class="sales-history-table-container">
        <div class="sales-history-table-wrapper">

            <table class="sales-history-table">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Branch</th>
                        <th>Customer</th>
                        <th>Recorded By</th>
                        <th>Payment Method</th>
                        <th>Total Amount</th>
                        <th>Date &amp; Time</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($sales)): ?>
                        <tr>
                            <td colspan="7" class="empty-row">
                                No sales transactions recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td>
                                    #<?= h((string) $sale['id']) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $sale['branch_name']
                                        ?? 'Unknown Branch'
                                    ) ?>
                                </td>

                                <td>
                                    <?php
                                    $customerName = trim(
                                        $sale['customer_name'] ?? ''
                                    );
                                    ?>
                                    <?= h(
                                        $customerName !== ''
                                            ? $customerName
                                            : 'Walk-in Customer'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $sale['staff_name']
                                        ?? 'Unknown Staff'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $sale['payment_method']
                                        ?? 'Not specified'
                                    ) ?>
                                </td>

                                <td class="amount">
                                    ₱<?= number_format(
                                        (float) $sale['total_amount'],
                                        2
                                    ) ?>
                                </td>

                                <td>
                                    <?php
                                    $timestamp = strtotime(
                                        $sale['created_at'] ?? ''
                                    );
                                    ?>
                                    <?= $timestamp
                                        ? h(date(
                                            'M d, Y h:i A',
                                            $timestamp
                                        ))
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                    <?php endif; ?>
                </tbody>
            </table>

        </div>
    </div>

</div>

<?php require 'includes/footer.php'; ?>


<?php
require_once 'includes/auth.php';

$page_title = 'Branch Inventories';

$is_owner_user = is_owner();
$staff_branch_id = (string)($_SESSION['branch_id'] ?? '');

if (!$is_owner_user && $staff_branch_id === '') {
    http_response_code(403);
    exit('Your account does not have an assigned branch. Please contact the owner.');
}

// Load branches for the owner filter.
$branches = $pdo->query(
    "SELECT id, branch_name FROM branches ORDER BY branch_name ASC"
)->fetchAll(PDO::FETCH_ASSOC);

// Determine the selected branch.
$selected_branch_id = $is_owner_user
    ? trim((string)($_GET['branch_id'] ?? 'all'))
    : $staff_branch_id;

$valid_branch_ids = array_map(
    static fn($branch) => (string)$branch['id'],
    $branches
);

if (
    $is_owner_user &&
    $selected_branch_id !== 'all' &&
    !in_array($selected_branch_id, $valid_branch_ids, true)
) {
    $selected_branch_id = 'all';
}

// Load inventory.
$sql = "
    SELECT
        bi.id,
        bi.branch_id,
        bi.quantity,
        bi.min_stock,
        p.product_name,
        p.category,
        p.price,
        b.branch_name
    FROM branch_inventory bi
    INNER JOIN products p ON p.id = bi.product_id
    INNER JOIN branches b ON b.id = bi.branch_id
    WHERE 1 = 1
";

$params = [];

if ($is_owner_user && $selected_branch_id !== 'all') {
    $sql .= " AND bi.branch_id = ?";
    $params[] = $selected_branch_id;
} elseif (!$is_owner_user) {
    $sql .= " AND bi.branch_id = ?";
    $params[] = $staff_branch_id;
}

$sql .= " ORDER BY b.branch_name ASC, p.product_name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count low-stock and out-of-stock items using a fixed threshold of 5.
$low_stock_count = 0;
$out_stock_count = 0;

foreach ($inventory_items as $item) {
    $quantity = (int)$item['quantity'];

    if ($quantity <= 0) {
        $out_stock_count++;
    } elseif ($quantity <= 5) {
        $low_stock_count++;
    }
}

require 'includes/header.php';
?>

<style>
/* Match the existing BGS LPG Store design */
.layout > .content {
    font-size: 16px;
}

.layout > .sidebar {
    background-color: #1D4ED8 !important;
}

.layout > .sidebar a {
    color: #FFFFFF !important;
}

.layout > .sidebar a[href="inventory.php"] {
    background-color: #0F4C81 !important;
    color: #FFFFFF !important;
    font-weight: bold !important;
    border-left: none !important;
    border-radius: 8px;
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

.layout > .sidebar a[href="logout.php"]:hover,
.layout a[href="logout.php"]:hover {
    background-color: #991B1B !important;
}

/* Page heading */
.inventory-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 20px;
}

.inventory-header h1 {
    margin: 0;
    color: #20253B;
    font-size: 2em;
    font-weight: 700;
}

.inventory-header .muted {
    margin: 6px 0 0;
    color: #64748B;
    font-size: 16px;
}

/* Warning labels */
.inventory-warning {
    padding: 13px 16px;
    border-radius: 7px;
    margin-bottom: 18px;
    font-size: 15px;
    line-height: 1.5;
}

.inventory-warning.low {
    background: #FEF3C7;
    border: 1px solid #FDE68A;
    color: #92400E;
}

.inventory-warning.out {
    background: #FEE2E2;
    border: 1px solid #FECACA;
    color: #991B1B;
}

.inventory-warning strong {
    display: block;
    margin-bottom: 3px;
}

/* Filters and search */
.inventory-toolbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.inventory-filters {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.inventory-filters label {
    font-weight: 600;
    color: #20253B;
}

.inventory-filters select,
.inventory-search input {
    padding: 10px 12px;
    border: 1px solid #CBD5E1;
    border-radius: 6px;
    background: #FFFFFF;
    color: #20253B;
    font-family: inherit;
    font-size: 16px;
}

.inventory-filters select:focus,
.inventory-search input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px #1D4ED81F;
}

.inventory-search {
    width: 100%;
    max-width: 360px;
}

.inventory-search input {
    width: 100%;
    box-sizing: border-box;
}

/* White inventory panel */
.inventory-panel {
    background: #FFFFFF;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0, 0, 0, .07);
    margin-bottom: 24px;
}

.inventory-panel-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 18px;
}

.inventory-panel-heading h2 {
    margin: 0;
    color: #20253B;
    font-size: 1.17em;
    font-weight: 600;
}

.inventory-panel-heading span {
    color: #64748B;
    font-size: 15px;
}

/* Table */
.inventory-table-wrap {
    width: 100%;
    overflow-x: auto;
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font: inherit;
}

.inventory-table th,
.inventory-table td {
    padding: 14px 16px;
    border-bottom: 1px solid #E2E8F0;
    vertical-align: middle;
    font-size: 16px;
    text-align: left;
}

.inventory-table th {
    background: #F1F5F9;
    color: #20253B;
    font-weight: 600;
    white-space: nowrap;
}

.inventory-table tbody tr:hover {
    background: #F8FAFC;
}

.inventory-table tr:last-child td {
    border-bottom: none;
}

/* Status labels */
.stock-status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    white-space: nowrap;
}

.stock-status.in-stock {
    background: #DCFCE7;
    color: #166534;
}

.stock-status.low-stock {
    background: #FEF3C7;
    color: #92400E;
    border: 1px solid #FDE68A;
}

.stock-status.out-stock {
    background: #FEE2E2;
    color: #991B1B;
    border: 1px solid #FECACA;
}

.inventory-empty {
    text-align: center !important;
    padding: 25px !important;
    color: #64748B;
}

@media (max-width: 800px) {
    .inventory-toolbar {
        align-items: stretch;
    }

    .inventory-search {
        max-width: none;
    }
}

@media (max-width: 700px) {
    .inventory-panel {
        padding: 16px;
    }

    .inventory-table {
        min-width: 700px;
    }
}
</style>

<div class="inventory-header">
    <div>
        <h1>Branch Inventories</h1>
        <p class="muted">
            Monitor product quantities and identify items that need restocking.
        </p>
    </div>
</div>

<?php if ($out_stock_count > 0): ?>
    <div class="inventory-warning out">
        <strong>⚠ Out of Stock Warning</strong>
        <?= $out_stock_count ?> product(s) currently have zero stock.
        Please arrange restocking as needed.
    </div>
<?php endif; ?>

<?php if ($low_stock_count > 0): ?>
    <div class="inventory-warning low">
        <strong>⚠ Low Stock Warning</strong>
        <?= $low_stock_count ?> product(s) have only 1–5 units remaining.
        Please check these items and arrange restocking.
    </div>
<?php endif; ?>

<div class="inventory-toolbar">
    <form method="get" class="inventory-filters">
        <?php if ($is_owner_user): ?>
            <label for="branchFilter">Branch:</label>

            <select name="branch_id" id="branchFilter"
                    onchange="this.form.submit()">
                <option value="all"
                    <?= $selected_branch_id === 'all' ? 'selected' : '' ?>>
                    All Branches
                </option>

                <?php foreach ($branches as $branch): ?>
                    <option
                        value="<?= htmlspecialchars((string)$branch['id'], ENT_QUOTES, 'UTF-8') ?>"
                        <?= $selected_branch_id === (string)$branch['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($branch['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php else: ?>
            
        <?php endif; ?>
    </form>

</div>

<section class="inventory-panel">
    <div class="inventory-panel-heading">
        <h2>Inventory List</h2>
        <span>
            Total Records:
            <strong id="inventoryRecordCount"><?= count($inventory_items) ?></strong>
        </span>
    </div>

    <div class="inventory-table-wrap">
        <table class="inventory-table" id="inventoryTable">
            <thead>
                <tr>
                    <?php if ($is_owner_user): ?>
                        <th>Branch</th>
                    <?php endif; ?>
                    <th>Product Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Quantity</th>
                    <th>Minimum Stock</th>
                    <th>Status</th>
                </tr>
            </thead>

            <tbody>
                <?php if (empty($inventory_items)): ?>
                    <tr>
                        <td colspan="<?= $is_owner_user ? 7 : 6 ?>"
                            class="inventory-empty">
                            No inventory records found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($inventory_items as $item): ?>
                        <?php
                        $quantity = (int)$item['quantity'];

                        if ($quantity <= 0) {
                            $status = 'Out of Stock';
                            $status_class = 'out-stock';
                        } elseif ($quantity <= 5) {
                            $status = '⚠ Low Stock';
                            $status_class = 'low-stock';
                        } else {
                            $status = 'In Stock';
                            $status_class = 'in-stock';
                        }

                        $search_text = $item['branch_name'] . ' ' .
                                       $item['product_name'] . ' ' .
                                       $item['category'] . ' ' .
                                       $status;
                        ?>

                        <tr class="inventory-row"
                            data-search="<?= htmlspecialchars($search_text, ENT_QUOTES, 'UTF-8') ?>">
                            <?php if ($is_owner_user): ?>
                                <td>
                                    <?= htmlspecialchars($item['branch_name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            <?php endif; ?>

                            <td>
                                <?= htmlspecialchars($item['product_name'], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                <?= htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8') ?>
                            </td>

                            <td>
                                ₱<?= number_format((float)$item['price'], 2) ?>
                            </td>

                            <td>
                                <strong><?= number_format($quantity) ?></strong>
                            </td>

                            <td>
                                <?= number_format((int)$item['min_stock']) ?>
                            </td>

                            <td>
                                <span class="stock-status <?= $status_class ?>">
                                    <?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <tr id="noSearchResults" style="display:none;">
                        <td colspan="<?= $is_owner_user ? 7 : 6 ?>"
                            class="inventory-empty">
                            No matching inventory records found.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('inventorySearch');
    const rows = document.querySelectorAll(
        '#inventoryTable tbody tr.inventory-row'
    );
    const noResults = document.getElementById('noSearchResults');
    const recordCount = document.getElementById('inventoryRecordCount');

    if (!searchInput) return;

    function filterInventory() {
        const searchText = searchInput.value.toLowerCase().trim();
        let visibleCount = 0;

        rows.forEach(function (row) {
            const rowText = (
                row.dataset.search || row.textContent
            ).toLowerCase();

            const matches = rowText.includes(searchText);

            row.style.display = matches ? '' : 'none';

            if (matches) visibleCount++;
        });

        if (noResults) {
            noResults.style.display =
                visibleCount === 0 && rows.length > 0 ? '' : 'none';
        }

        if (recordCount) {
            recordCount.textContent = visibleCount;
        }
    }

    searchInput.addEventListener('input', filterInventory);
});
</script>

<?php require 'includes/footer.php'; ?>

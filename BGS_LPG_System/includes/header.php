
<?php
require_once __DIR__ . '/auth.php';
$current_page = strtolower(basename($_SERVER['PHP_SELF']));
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($page_title ?? 'Dashboard') ?> | BGS LPG</title>
    <link rel="stylesheet" href="assets/style.css">

    <style>
        /* Account information and Logout button */
        .account {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            color: #FFFFFF;
            font-size: 13px;
        }

        .logout-btn {
            display: inline-block;
            background-color: #DC2626;
            color: #FFFFFF !important;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 7px;
            font-weight: bold;
            transition: background-color 0.2s ease;
        }

        .logout-btn:hover {
            background-color: #991B1B;
            color: #FFFFFF !important;
        }

        @media (max-width: 520px) {
            .account {
                gap: 8px;
                flex-wrap: wrap;
                font-size: 11px;
            }

            .logout-btn {
                padding: 6px 10px;
            }
        }
    </style>
</head>
<body>

<header class="topbar">
    <div class="brand">
        <img src="assets/bgs-logo.jpg" class="logo" alt="BGS logo">
        <div>
            <strong>BGS LPG Store</strong>
            <small>Inventory &amp; Sales Management</small>
        </div>
    </div>


<?php
/* Get the current user's branch name for the header. */
$headerBranchName = '';

if (!empty($_SESSION['user_id'])) {
    try {
        $headerBranchStmt = $pdo->prepare(
            "SELECT b.branch_name
             FROM users u
             LEFT JOIN branches b
                ON u.branch_name = b.branch_name
                OR CAST(b.id AS CHAR) = u.branch_name
             WHERE u.id = ?
             LIMIT 1"
        );

        $headerBranchStmt->execute([$_SESSION['user_id']]);
        $headerBranchName = $headerBranchStmt->fetchColumn();

        if (
            $headerBranchName === false ||
            $headerBranchName === null ||
            trim((string) $headerBranchName) === ''
        ) {
            $headerBranchName = $_SESSION['branch_name'] ?? 'No Branch Assigned';
        }
    } catch (PDOException $e) {
        error_log('Header branch lookup error: ' . $e->getMessage());
        $headerBranchName = $_SESSION['branch_name'] ?? 'No Branch Assigned';
    }
} else {
    $headerBranchName = 'No Branch Assigned';
}
?>

<div class="account">
    <span>
        <?= h($_SESSION['name'] ?? 'User') ?>
        · <?= h($headerBranchName) ?>
        · <?= h(ucfirst($_SESSION['role'] ?? '')) ?>
    </span>

    <a href="logout.php" class="logout-btn">Logout</a>
</div>

</header>

<div class="layout">
   
<aside class="sidebar">

    <!-- Dashboard: different page for owner and staff -->
    <a href="<?= is_owner() ? 'owner_db.php' : 'staff_db.php' ?>"
       class="<?= in_array($current_page, ['owner_db.php', 'staff_db.php']) ? 'active' : '' ?>">
        Dashboard
    </a>

    <?php if (is_owner()): ?>

        <!-- Owner-only management pages -->
        <a href="branches.php"
           class="<?= $current_page === 'branches.php' ? 'active' : '' ?>">
            Branches
        </a>

        <a href="products.php"
           class="<?= $current_page === 'products.php' ? 'active' : '' ?>">
            Product Masterlist
        </a>

        <a href="inventory.php"
           class="<?= $current_page === 'inventory.php' ? 'active' : '' ?>">
            Branch Inventories
        </a>

        <a href="sales_report.php"
           class="<?= $current_page === 'sales_report.php' ? 'active' : '' ?>">
            Sales Reports
        </a>

        <a href="inventory_report.php"
           class="<?= $current_page === 'inventory_report.php' ? 'active' : '' ?>">
            Inventory Reports
        </a>

        <a href="users.php"
           class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
            Store Staffs
        </a>

    <?php else: ?>

        <!-- Staff pages -->
        <a href="staff_products.php"
           class="<?= $current_page === 'staff_products.php' ? 'active' : '' ?>">
            Products
        </a>

        <a href="record_sale.php"
           class="<?= $current_page === 'record_sale.php' ? 'active' : '' ?>">
            Record Sale
        </a>

        <a href="sales_history.php"
           class="<?= $current_page === 'sales_history.php' ? 'active' : '' ?>">
            Sales History
        </a>

        
<a href="inventory_report.php"
   class="<?= $current_page === 'inventory_report.php' ? 'active' : '' ?>">
    Inventory Report
</a>

<a href="sales_report.php"
   class="<?= $current_page === 'sales_report.php' ? 'active' : '' ?>">
    Sales Report
</a>


    <?php endif; ?>

</aside>


    <main class="content">

        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert">
                <?= h($_SESSION['flash']); unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

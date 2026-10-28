
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

    <div class="account">
        <span>
            <?= h($_SESSION['name']) ?>
            · <?= h(ucfirst($_SESSION['role'])) ?>
        </span>
        <a href="logout.php" class="logout-btn">Logout</a>
    </div>
</header>

<div class="layout">
    <aside class="sidebar">

        <a href="owner_db.php"
           class="<?= $current_page === 'owner_db.php' ? 'active' : '' ?>">
            Dashboard
        </a>

        <a href="branches.php"
               class="<?= $current_page === 'branches.php' ? 'active' : '' ?>">
                Branches
            </a>

        <a href="products.php"
           class="<?= $current_page === 'products.php' ? 'active' : '' ?>">
            Products
        </a>

        <a href="inventory.php"
           class="<?= $current_page === 'inventory.php' ? 'active' : '' ?>">
            Branch Inventories
        </a>

        <a href="sales.php"
           class="<?= $current_page === 'sales.php' ? 'active' : '' ?>">
            Sales
        </a>

        <a href="sales_history.php"
           class="<?= $current_page === 'sales_history.php' ? 'active' : '' ?>">
            Sales History
        </a>

        <a href="reports.php"
           class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
            Reports
        </a>

        <?php if (is_owner()): ?>

            

            <a href="users.php"
               class="<?= $current_page === 'users.php' ? 'active' : '' ?>">
                Store Staffs
            </a>

        <?php endif; ?>

    </aside>

    <main class="content">

        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert">
                <?= h($_SESSION['flash']); unset($_SESSION['flash']); ?>
            </div>
        <?php endif; ?>

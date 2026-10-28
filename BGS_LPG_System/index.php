
<?php
require 'config.php';

// Redirect logged-in users according to their role.
if (!empty($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'owner') {
        header('Location: owner_db.php');
        exit;
    } elseif (($_SESSION['role'] ?? '') === 'staff') {
        header('Location: staff_db.php');
        exit;
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>BGS LPG Store | Welcome</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(135deg, #1D4ED8, #0F4C81);
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .welcome-card {
            width: 100%;
            max-width: 520px;
            background: #FFFFFF;
            border-radius: 20px;
            padding: 45px 30px;
            text-align: center;
            box-shadow: 0 12px 35px #00000030;
        }

        .welcome-logo {
            width: 160px;
            height: 160px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .welcome-card h1 {
            color: #1D4ED8;
            font-size: 30px;
            margin: 0 0 12px;
        }

        .welcome-card p {
            color: #4B5563;
            font-size: 16px;
            line-height: 1.6;
            margin: 0 0 30px;
        }

        .login-btn {
            display: inline-block;
            width: 100%;
            max-width: 280px;
            padding: 14px 24px;
            background: #1D4ED8;
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            transition: background 0.2s;
        }

        .login-btn:hover {
            background: #0F4C81;
        }

        .copyright {
            margin-top: 30px;
            font-size: 12px;
            color: #6B7280;
        }

        @media (max-width: 480px) {
            .welcome-card {
                padding: 35px 20px;
            }

            .welcome-logo {
                width: 130px;
                height: 130px;
            }

            .welcome-card h1 {
                font-size: 25px;
            }
        }
    </style>
</head>

<body>

    <main class="welcome-card">

        <img
            src="assets/bgs-logo.jpg"
            alt="Bohol Gas Saver Logo"
            class="welcome-logo"
        >

        <h1>BGS LPG Store</h1>

        <p>
            LPG Inventory and Sales Management System
        </p>

        <a href="login.php" class="login-btn">
            Login to System
        </a>

        <div class="copyright">
            &copy; <?= date('Y') ?> BGS LPG Inventory and Sales Management System.
            All rights reserved.
        </div>

    </main>

</body>
</html>

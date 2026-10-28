<?php
require 'config.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$error = '';

// Load branches for the dropdown
$branches = $pdo->query(
    "SELECT id, branch_name FROM branches ORDER BY branch_name"
)->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $staffName = trim($_POST['staff_name'] ?? '');
    $branchId = trim($_POST['branch_id'] ?? '');
    $password = $_POST['password'] ?? '';

    // Find the active user and match the selected branch
    $stmt = $pdo->prepare(
        "SELECT users.*, branches.id AS selected_branch_id
         FROM users
         INNER JOIN branches
            ON users.branch_name = branches.branch_name
         WHERE users.staff_name = ?
           AND branches.id = ?
           AND users.active = 1"
    );

    $stmt->execute([$staffName, $branchId]);
    $users = $stmt->fetchAll();

    $u = false;

    // Verify the password
    foreach ($users as $user) {
        if (password_verify($password, $user['password'])) {
            $u = $user;
            break;
        }
    }

    // Log in if the credentials are correct
    if ($u) {
        session_regenerate_id(true);

        $_SESSION['user_id'] = $u['id'];
        $_SESSION['name'] = $u['staff_name'];
        $_SESSION['role'] = $u['role'];
        $_SESSION['branch_id'] = $u['selected_branch_id'];

        // Redirect according to the user's role
        if ($u['role'] === 'owner') {
            header('Location: owner_db.php');
        } elseif ($u['role'] === 'staff') {
            header('Location: staff_db.php');
        } else {
            header('Location: login.php');
        }

        exit;
    }

    $error = 'Information entered is incorrect.';
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Login | BGS LPG</title>

    <link rel="stylesheet" href="assets/style.css">

    <!-- Lucide icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <style>
        .login-card p {
            color: #000;
        }

        .password-note {
            display: none;
            font-size: 12px;
            font-weight: normal;
            color: #000;
            margin-top: 6px;
            line-height: 1.5;
        }

        .password-note.show {
            display: block;
        }

        /* Password field wrapper */
        .password-wrapper {
            position: relative;
            width: 100%;
        }

        .login-card .password-wrapper input {
            padding-right: 50px;
        }

        /* Show/hide password button */
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 5px;
            color: #20253b;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .toggle-password svg {
            width: 20px;
            height: 20px;
        }

        .toggle-password:focus-visible {
            outline: 2px solid #0F4C81;
            outline-offset: 2px;
            border-radius: 4px;
        }

        /* Back button */
        .back-btn {
            display: block;
            width: 100%;
            margin-top: 12px;
            padding: 12px;
            text-align: center;
            background: #6B7280;
            color: #FFFFFF;
            text-decoration: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            transition: background 0.2s;
            box-sizing: border-box;
        }

        .back-btn:hover {
            background: #4B5563;
            color: #FFFFFF;
        }
    </style>
</head>

<body class="login-page">
<main class="login-card">

    <img
        class="logo login-logo"
        src="assets/bgs-logo.jpg"
        alt="Bohol Gas Saver logo"
    >

    <h1>BGS LPG Store</h1>
    <p>LPG Inventory and Sales Management System</p>

    <?php if (isset($_GET['registered'])): ?>
        <div class="alert">
            Registration successful. You can now log in.
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="post">

        <!-- 1. BRANCH NAME DROPDOWN -->
        <label>
            Branch Name

            <select name="branch_id" required>
                <option value="">Select your branch</option>

                <?php foreach ($branches as $branch): ?>
                    <option
                        value="<?= htmlspecialchars((string)$branch['id']) ?>"
                        <?= (
                            isset($_POST['branch_id']) &&
                            (string)$_POST['branch_id'] ===
                            (string)$branch['id']
                        ) ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($branch['branch_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- 2. USERNAME INPUT -->
        <label>
            Username

            <input
                type="text"
                name="staff_name"
                required
                autofocus
                value="<?= htmlspecialchars($_POST['staff_name'] ?? '') ?>"
                placeholder="Enter your username"
            >
        </label>

        <!-- 3. PASSWORD INPUT WITH SHOW/HIDE ICON -->
        <label>
            Password

            <div class="password-wrapper">
                <input
                    type="password"
                    name="password"
                    id="password"
                    required
                    placeholder="Enter your password"
                >

                <button
                    type="button"
                    class="toggle-password"
                    id="togglePassword"
                    aria-label="Show password"
                    title="Show password"
                    aria-pressed="false"
                >
                    <i data-lucide="eye"></i>
                </button>
            </div>

            <small class="password-note" id="passwordNote">
                Password should be at least 8 characters long.
            </small>
        </label>

        <!-- LOGIN BUTTON -->
        <button class="btn" type="submit">Login</button>

        <!-- BACK BUTTON -->
        <a href="index.php" class="back-btn">Back</a>

    </form>

</main>

<script>
    const passwordInput = document.getElementById('password');
    const passwordNote = document.getElementById('passwordNote');
    const togglePassword = document.getElementById('togglePassword');

    // Update the password length note
    function updatePasswordNote() {
        const isFocused =
            document.activeElement === passwordInput;

        const isTooShort =
            passwordInput.value.length > 0 &&
            passwordInput.value.length < 8;

        passwordNote.classList.toggle(
            'show',
            isFocused || isTooShort
        );
    }

    passwordInput.addEventListener('focus', updatePasswordNote);
    passwordInput.addEventListener('blur', updatePasswordNote);
    passwordInput.addEventListener('input', updatePasswordNote);

    // Show or hide the password
    togglePassword.addEventListener('click', function () {
        const isHidden = passwordInput.type === 'password';

        passwordInput.type = isHidden ? 'text' : 'password';

        togglePassword.innerHTML = isHidden
            ? '<i data-lucide="eye-off"></i>'
            : '<i data-lucide="eye"></i>';

        togglePassword.setAttribute(
            'aria-label',
            isHidden ? 'Hide password' : 'Show password'
        );

        togglePassword.setAttribute(
            'aria-pressed',
            isHidden ? 'true' : 'false'
        );

        togglePassword.title = isHidden
            ? 'Hide password'
            : 'Show password';

        // Render the updated Lucide icon
        lucide.createIcons();
    });

    // Render the initial eye icon
    lucide.createIcons();
</script>

</body>
</html>

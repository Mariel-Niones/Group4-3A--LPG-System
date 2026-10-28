
<?php
require_once 'includes/auth.php';

// Only the owner can register staff accounts
if (!is_owner()) {
    header('Location: index.php');
    exit;
}

// Only process submitted forms
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: users.php');
    exit;
}

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';

if (
    $session_token === '' ||
    $submitted_token === '' ||
    !hash_equals($session_token, $submitted_token)
) {
    header('Location: users.php?error=security');
    exit;
}

// Get form values
$staff_name = trim($_POST['staff_name'] ?? '');
$branch_name = trim($_POST['branch_name'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

// Validate required fields
if (
    $staff_name === '' ||
    $branch_name === '' ||
    $password === '' ||
    $confirm_password === ''
) {
    header('Location: users.php?error=missing');
    exit;
}

// Validate staff name length
if (strlen($staff_name) > 100) {
    header('Location: users.php?error=name');
    exit;
}

// Validate password length
if (strlen($password) < 8) {
    header('Location: users.php?error=password');
    exit;
}

// Confirm passwords match
if ($password !== $confirm_password) {
    header('Location: users.php?error=mismatch');
    exit;
}

try {
    // Verify that the selected branch exists
    $branchStmt = $pdo->prepare(
        "SELECT branch_name
         FROM branches
         WHERE branch_name = :branch_name
         LIMIT 1"
    );

    $branchStmt->execute([
        'branch_name' => $branch_name
    ]);

    $branch = $branchStmt->fetch(PDO::FETCH_ASSOC);

    if (!$branch) {
        header('Location: users.php?error=branch');
        exit;
    }

    // Check whether this staff name already exists
    $checkStmt = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE staff_name = :staff_name
         LIMIT 1"
    );

    $checkStmt->execute([
        'staff_name' => $staff_name
    ]);

    if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
        header('Location: users.php?error=exists');
        exit;
    }

    // Hash the password securely
    $hashed_password = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    // Create an active staff account
    $insertStmt = $pdo->prepare(
        "INSERT INTO users
            (staff_name, branch_name, password, role, active)
         VALUES
            (:staff_name, :branch_name, :password, 'staff', 1)"
    );

    $insertStmt->execute([
        'staff_name' => $staff_name,
        'branch_name' => $branch['branch_name'],
        'password' => $hashed_password
    ]);

    // Redirect back to the staff list
    header('Location: users.php?registered=1');
    exit;

} catch (PDOException $e) {
    // Log the database error without exposing details to the user
    error_log('Staff registration failed: ' . $e->getMessage());

    header('Location: users.php?error=database');
    exit;
}


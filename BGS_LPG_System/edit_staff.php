
<?php
require 'includes/auth.php';

function editResponse(bool $success, string $message, int $status = 200): void {
    $isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest';

    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => $success,
            'message' => $message
        ]);
        exit;
    }

    header('Location: users.php?' . ($success ? 'updated=1' : 'error=1'));
    exit;
}

if (!is_owner()) {
    editResponse(false, 'You are not authorized to edit staff accounts.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    editResponse(false, 'Invalid request method.', 405);
}

if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
) {
    editResponse(false, 'Your session has expired. Refresh the page and try again.', 403);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$name = trim($_POST['staff_name'] ?? '');
$branchName = trim($_POST['branch_name'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

if (!$id || $id < 1 || $name === '' || $branchName === '') {
    editResponse(false, 'Please complete all required fields.', 400);
}

if (mb_strlen($name) > 100) {
    editResponse(false, 'Staff name must not exceed 100 characters.', 400);
}

if ($password !== '' && strlen($password) < 8) {
    editResponse(false, 'The new password must contain at least 8 characters.', 400);
}

if ($password !== $confirmPassword) {
    editResponse(false, 'The passwords do not match.', 400);
}

try {
    $check = $pdo->prepare(
        "SELECT id
         FROM users
         WHERE id = ? AND role = 'staff'
         LIMIT 1"
    );
    $check->execute([$id]);

    if (!$check->fetch(PDO::FETCH_ASSOC)) {
        editResponse(false, 'Staff account not found.', 404);
    }

    $branchCheck = $pdo->prepare(
        "SELECT id
         FROM branches
         WHERE branch_name = ?
         LIMIT 1"
    );
    $branchCheck->execute([$branchName]);

    if (!$branchCheck->fetch(PDO::FETCH_ASSOC)) {
        editResponse(false, 'The selected branch does not exist.', 400);
    }

    if ($password !== '') {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare(
            "UPDATE users
             SET staff_name = ?, branch_name = ?, password = ?
             WHERE id = ? AND role = 'staff'"
        );
        $stmt->execute([$name, $branchName, $passwordHash, $id]);
    } else {
        $stmt = $pdo->prepare(
            "UPDATE users
             SET staff_name = ?, branch_name = ?
             WHERE id = ? AND role = 'staff'"
        );
        $stmt->execute([$name, $branchName, $id]);
    }

    editResponse(true, 'Staff information updated successfully.');
} catch (Throwable $e) {
    error_log('Staff edit error: ' . $e->getMessage());
    editResponse(false, 'A database error occurred while saving staff information.', 500);
}


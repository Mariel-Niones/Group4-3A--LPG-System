
<?php
require 'includes/auth.php';

function deleteResponse(bool $success, string $message, int $status = 200): void {
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

    header('Location: users.php?' . ($success ? 'deleted=1' : 'error=1'));
    exit;
}

if (!is_owner()) {
    deleteResponse(false, 'You are not authorized to deactivate staff.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deleteResponse(false, 'Invalid request method.', 405);
}

if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
) {
    deleteResponse(false, 'Your session has expired. Refresh the page and try again.', 403);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    deleteResponse(false, 'Invalid staff account ID.', 400);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET active = 0
         WHERE id = ? AND role = 'staff' AND active = 1"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare(
            "SELECT active
             FROM users
             WHERE id = ? AND role = 'staff'
             LIMIT 1"
        );
        $check->execute([$id]);
        $account = $check->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            deleteResponse(false, 'Staff account not found.', 404);
        }

        if ((int) $account['active'] === 0) {
            deleteResponse(true, 'This staff account is already deactivated.');
        }

        deleteResponse(false, 'Unable to deactivate the staff account.', 500);
    }

    deleteResponse(true, 'Staff account deactivated successfully.');
} catch (Throwable $e) {
    error_log('Staff deactivation error: ' . $e->getMessage());
    deleteResponse(false, 'A database error occurred while deactivating the account.', 500);
}

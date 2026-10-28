
<?php
require 'includes/auth.php';

function activationResponse(bool $success, string $message, int $status = 200): void {
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

    header('Location: users.php?' . ($success ? 'activated=1' : 'error=1'));
    exit;
}

if (!is_owner()) {
    activationResponse(false, 'You are not authorized to activate accounts.', 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    activationResponse(false, 'Invalid request method.', 405);
}

if (
    empty($_SESSION['csrf_token']) ||
    !isset($_POST['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], (string) $_POST['csrf_token'])
) {
    activationResponse(false, 'Your session has expired. Refresh the page and try again.', 403);
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id < 1) {
    activationResponse(false, 'Invalid staff account ID.', 400);
}

try {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET active = 1
         WHERE id = ? AND role = 'staff' AND active = 0"
    );
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        $check = $pdo->prepare(
            "SELECT active FROM users
             WHERE id = ? AND role = 'staff'
             LIMIT 1"
        );
        $check->execute([$id]);
        $account = $check->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            activationResponse(false, 'Staff account not found.', 404);
        }

        if ((int) $account['active'] === 1) {
            activationResponse(true, 'This staff account is already active.');
        }

        activationResponse(false, 'Unable to activate the staff account.', 500);
    }

    activationResponse(true, 'Staff account activated successfully.');
} catch (Throwable $e) {
    error_log('Staff activation error: ' . $e->getMessage());
    activationResponse(false, 'A database error occurred while activating the account.', 500);
}

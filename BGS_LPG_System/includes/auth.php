<?php
require_once __DIR__ . '/../config.php';
if (empty($_SESSION['user_id'])) { header('Location: login.php'); exit; }
function h($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function is_owner(): bool { return ($_SESSION['role'] ?? '') === 'owner'; }
function require_owner(): void { if (!is_owner()) { http_response_code(403); exit('Owner access required.'); } }
function current_branch(): ?int { return isset($_SESSION['branch_id']) ? (int)$_SESSION['branch_id'] : null; }
function branch_filter_sql(string $alias=''): array {
    if (is_owner()) return ['', []];
    $prefix = $alias ? $alias.'.' : '';
    return [" AND {$prefix}branch_id = ?", [current_branch() ?? 0]];
}

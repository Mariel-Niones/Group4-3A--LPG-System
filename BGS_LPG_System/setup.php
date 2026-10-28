<?php
require __DIR__ . '/config.php';
$hash = password_hash('password', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE username='owner'");
$stmt->execute([$hash]);
echo '<h2>BGS LPG System setup</h2><p>Owner account password has been set to <b>password</b>.</p><p>Login: <b>owner</b> / <b>password</b></p><p><b>Important:</b> Delete setup.php after this page runs.</p><p><a href="login.php">Go to login</a></p>';

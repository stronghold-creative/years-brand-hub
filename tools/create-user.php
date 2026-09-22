<?php
// Run once over SSH, or temporarily via the browser, then delete.
//   php tools/create-user.php "Nathan Hall" nathan@example.com "a-long-password" admin
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

[$script, $name, $email, $password, $role] = array_pad($argv ?? [], 5, null);

if (!$name || !$email || !$password) {
    exit("Usage: php tools/create-user.php \"Name\" email password [admin|editor|viewer]\n");
}
if (strlen($password) < 12) {
    exit("Use a password of at least 12 characters.\n");
}

db_run(
    'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE name = VALUES(name), password_hash = VALUES(password_hash), role = VALUES(role)',
    [$name, strtolower(trim($email)), password_hash($password, PASSWORD_DEFAULT), $role ?: 'editor']
);

echo "User saved.\n";

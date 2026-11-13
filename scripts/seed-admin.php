<?php
declare(strict_types=1);

/*
 * Create or update an admin account with a password_hash() hash.
 *
 *   docker compose exec app php scripts/seed-admin.php <username> <Manager|Kitchen Staff> "<Full name>"
 *
 * The password is read from STDIN (or the ADMIN_PASSWORD env var) so it never
 * appears in shell history or in the repository.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

[$script, $username, $role, $fullName] = array_pad($argv, 4, null);
$roles = ['Manager', 'Kitchen Staff'];

if (!is_string($username) || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
    || !in_array($role, $roles, true) || !is_string($fullName) || trim($fullName) === '') {
    fwrite(STDERR, "Usage: php scripts/seed-admin.php <username> <Manager|\"Kitchen Staff\"> \"<Full name>\"\n");
    exit(1);
}

$password = getenv('ADMIN_PASSWORD');
if ($password === false || $password === '') {
    fwrite(STDOUT, 'Password (min 10 chars): ');
    $password = rtrim((string) fgets(STDIN), "\r\n");
}
if (strlen($password) < 10) {
    fwrite(STDERR, "Password must be at least 10 characters.\n");
    exit(1);
}

$statement = db()->prepare(
    'INSERT INTO admin_users (username, password_hash, full_name, role)
     VALUES (:username, :password_hash, :full_name, :role)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash),
                             full_name = VALUES(full_name), role = VALUES(role), is_active = 1'
);
$statement->execute([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'full_name' => trim($fullName),
    'role' => $role,
]);

fwrite(STDOUT, "Saved admin '{$username}' ({$role}).\n");

<?php
declare(strict_types=1);

$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
  foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) continue;
    [$k,$v] = array_map('trim', explode('=', $line, 2));
    putenv("$k=$v");
  }
}

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: 'company_accounts';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$email = $argv[1] ?? 'admin@example.com';
$password = $argv[2] ?? 'Admin@123';
$fullname = $argv[3] ?? 'Admin';

$hash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $pdo->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
$stmt->execute([$email]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
  $stmt = $pdo->prepare('UPDATE users SET name=?, password_hash=?, role=? WHERE id=?');
  $stmt->execute([$fullname, $hash, 'admin', (int)$row['id']]);
  echo "Updated admin: $email\n";
} else {
  $stmt = $pdo->prepare('INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)');
  $stmt->execute([$fullname, $email, $hash, 'admin']);
  echo "Created admin: $email\n";
}

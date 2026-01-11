<?php
declare(strict_types=1);

// Simple PHP API front controller (no frameworks)
// Base path is /api (handled by Apache alias/VirtualHost)

date_default_timezone_set('UTC');

// Load env from ../.env if exists
$envPath = dirname(__DIR__) . '/.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#') || !str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if (!getenv($k)) {
            putenv("$k=$v");
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $val = getenv($key);
    return $val === false ? $default : $val;
}

function db(): PDO
{
    static $pdo;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $host = env('DB_HOST', '127.0.0.1');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'company_accounts');
    $user = env('DB_USER', 'root');
    $pass = env('DB_PASS', '');
    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return $pdo;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function signJwt(array $payload): string
{
    $secret = env('JWT_SECRET', 'change-me');
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $segments = [
        base64url_encode(json_encode($header)),
        base64url_encode(json_encode($payload)),
    ];
    $signingInput = implode('.', $segments);
    $signature = hash_hmac('sha256', $signingInput, $secret, true);
    $segments[] = base64url_encode($signature);
    return implode('.', $segments);
}

function verifyJwt(?string $token): ?array
{
    if (!$token) return null;
    $parts = explode('.', $token);
    if (count($parts) !== 3) return null;
    [$h64, $p64, $s64] = $parts;
    $payload = json_decode(base64url_decode($p64), true);
    $secret = env('JWT_SECRET', 'change-me');
    $validSig = base64url_encode(hash_hmac('sha256', "$h64.$p64", $secret, true));
    if (!hash_equals($validSig, $s64)) return null;
    if (isset($payload['exp']) && time() > $payload['exp']) return null;
    return $payload;
}

function getBearerToken(): ?string
{
    $hdr = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['Authorization']
        ?? '';

    if (!$hdr && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        if (isset($headers['Authorization'])) $hdr = $headers['Authorization'];
        if (isset($headers['authorization'])) $hdr = $headers['authorization'];
    }

    if (preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
        return trim($m[1]);
    }
    return null;
}


function jsonBody(): array
{
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

function respond($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function logActivity(PDO $pdo, ?int $userId, string $action, ?string $description = null, ?string $entityType = null, ?int $entityId = null, $metadata = null): void
{
    $stmt = $pdo->prepare('INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id, metadata, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $userId,
        $action,
        $description,
        $entityType,
        $entityId,
        $metadata ? json_encode($metadata) : null,
    ]);
}

// Routing helpers
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
// Strip /api prefix if present
$path = preg_replace('#^/api#', '', $path);
$path = '/' . ltrim($path, '/');

// CORS (same origin متوقع؛ يمكن تفعيل عند الحاجة)
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$pdo = db();

// Public endpoints
if ($path === '/health' && $method === 'GET') {
    respond(['status' => 'ok']);
}

if ($path === '/auth/login' && $method === 'POST') {
    $body = jsonBody();
    $email = $body['email'] ?? '';
    $password = $body['password'] ?? '';
    if (!$email || !$password) {
        respond(['error' => 'Email and password are required'], 422);
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

$hash = $user['password_hash'] ?? null;
if (!$user || !$hash || !password_verify($password, $hash)) {
    respond(['error' => 'Invalid credentials'], 401);
}

    $allowedRoutes = [];
    if (!empty($user['allowed_routes'])) {
        $decoded = json_decode((string)$user['allowed_routes'], true);
        if (is_array($decoded)) $allowedRoutes = $decoded;
    }

    $payload = [
        'sub' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'],
        'allowed_routes' => $allowedRoutes,
        'iat' => time(),
        'exp' => time() + 60 * 60 * 24, // 24h
    ];
    $token = signJwt($payload);
    logActivity($pdo, (int)$user['id'], 'LOGIN', 'User login', 'user', (int)$user['id']);
    respond([
        'token' => $token,
        'user' => [
            'id' => (int)$user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'allowed_routes' => $allowedRoutes,
        ],
    ], 200);
}


// Auth middleware
// Auth middleware
$user = verifyJwt(getBearerToken());
if (!$user) {
    respond(['error' => 'Unauthorized'], 401);
}
$authUserId = (int)($user['sub'] ?? 0);
$authRole = $user['role'] ?? '';

function requireAdmin(string $role): void
{
    if ($role !== 'admin') {
        respond(['error' => 'Forbidden'], 403);
    }
}

function requireWriteAccess(string $role): void
{
    if ($role === 'viewer') {
        respond(['error' => 'Forbidden'], 403);
    }
}

function jsonError(string $message, int $status = 422): void
{
    respond(['error' => $message], $status);
}


// Helper for path params
function matchRoute(string $pattern, string $path): array|false
{
    $regex = '#^' . preg_replace('#:([a-zA-Z_][a-zA-Z0-9_]*)#', '(?P<$1>[^/]+)', $pattern) . '$#';
    if (preg_match($regex, $path, $matches)) {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }
    return false;
}

// Users CRUD (admin only)
if ($path === '/users' && $method === 'GET') {
    requireAdmin($authRole);
    $stmt = $pdo->query('SELECT id, name, email, role, allowed_routes, created_at, updated_at FROM users ORDER BY id DESC');
    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $routes = [];
        if (!empty($row['allowed_routes'])) {
            $decoded = json_decode((string)$row['allowed_routes'], true);
            if (is_array($decoded)) $routes = $decoded;
        }
        $row['allowed_routes'] = $routes;
    }
    respond($rows);
}

if ($path === '/users' && $method === 'POST') {
    requireAdmin($authRole);
    $body = jsonBody();
    foreach (['name','email','password','role'] as $f) {
        if (empty($body[$f])) {
            respond(['error' => "$f is required"], 422);
        }
    }
    if (!in_array($body['role'], ['admin','viewer'], true)) {
        respond(['error' => 'role must be admin or viewer'], 422);
    }
    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM users WHERE email = ?');
    $stmt->execute([$body['email']]);
    if ((int)$stmt->fetch()['cnt'] > 0) {
        respond(['error' => 'Email already exists'], 409);
    }
    $hash = password_hash($body['password'], PASSWORD_BCRYPT);
    $allowedRoutes = null;
    if ($body['role'] === 'viewer') {
        if (empty($body['allowed_routes']) || !is_array($body['allowed_routes'])) {
            respond(['error' => 'allowed_routes is required for viewer'], 422);
        }
        $allowedRoutes = json_encode(array_values($body['allowed_routes']));
    }
    $stmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, allowed_routes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['name'],
        $body['email'],
        $hash,
        $body['role'],
        $allowedRoutes,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'CREATE_USER', "Created user #$id", 'user', $id, $body);
    respond(['id' => $id], 201);
}

// Expense categories (read: any auth, write: admin)
if ($path === '/expense-categories' && $method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM expense_categories ORDER BY sort_order ASC, id DESC');
    respond($stmt->fetchAll());
}

if ($path === '/expense-categories' && $method === 'POST') {
    requireAdmin($authRole);
    $body = jsonBody();
    if (empty($body['name'])) jsonError('name is required');
    $stmt = $pdo->prepare('INSERT INTO expense_categories (name, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['name'],
        isset($body['is_active']) ? (int)$body['is_active'] : 1,
        isset($body['sort_order']) ? (int)$body['sort_order'] : 0,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'CREATE_EXPENSE_CATEGORY', "Created expense category #$id", 'expense_category', $id, $body);
    respond(['id' => $id], 201);
}

if ($params = matchRoute('/expense-categories/:id', $path)) {
    if ($method === 'PATCH') {
        requireAdmin($authRole);
        $body = jsonBody();
        $sets = [];
        $bind = [];
        foreach (['name','is_active','sort_order'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $bind[] = $f === 'name' ? $body[$f] : (int)$body[$f];
            }
        }
        if (!$sets) jsonError('No fields to update');
        $bind[] = (int)$params['id'];
        $sql = 'UPDATE expense_categories SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_EXPENSE_CATEGORY', "Updated expense category #{$params['id']}", 'expense_category', (int)$params['id'], $body);
        respond(['success' => true]);
    }
    if ($method === 'DELETE') {
        requireAdmin($authRole);
        $stmt = $pdo->prepare('DELETE FROM expense_categories WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        logActivity($pdo, $authUserId, 'DELETE_EXPENSE_CATEGORY', "Deleted expense category #{$params['id']}", 'expense_category', (int)$params['id']);
        respond(['success' => true]);
    }
}

// Payment methods (read: any auth, write: admin)
if ($path === '/payment-methods' && $method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM payment_methods ORDER BY sort_order ASC, id DESC');
    respond($stmt->fetchAll());
}

if ($path === '/payment-methods' && $method === 'POST') {
    requireAdmin($authRole);
    $body = jsonBody();
    if (empty($body['name'])) jsonError('name is required');
    $stmt = $pdo->prepare('INSERT INTO payment_methods (name, is_active, sort_order, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['name'],
        isset($body['is_active']) ? (int)$body['is_active'] : 1,
        isset($body['sort_order']) ? (int)$body['sort_order'] : 0,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'CREATE_PAYMENT_METHOD', "Created payment method #$id", 'payment_method', $id, $body);
    respond(['id' => $id], 201);
}

if ($params = matchRoute('/payment-methods/:id', $path)) {
    if ($method === 'PATCH') {
        requireAdmin($authRole);
        $body = jsonBody();
        $sets = [];
        $bind = [];
        foreach (['name','is_active','sort_order'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $bind[] = $f === 'name' ? $body[$f] : (int)$body[$f];
            }
        }
        if (!$sets) jsonError('No fields to update');
        $bind[] = (int)$params['id'];
        $sql = 'UPDATE payment_methods SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_PAYMENT_METHOD', "Updated payment method #{$params['id']}", 'payment_method', (int)$params['id'], $body);
        respond(['success' => true]);
    }
    if ($method === 'DELETE') {
        requireAdmin($authRole);
        $stmt = $pdo->prepare('DELETE FROM payment_methods WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        logActivity($pdo, $authUserId, 'DELETE_PAYMENT_METHOD', "Deleted payment method #{$params['id']}", 'payment_method', (int)$params['id']);
        respond(['success' => true]);
    }
}

// Expense settings (admin)
if ($path === '/expense-settings' && $method === 'GET') {
    requireAdmin($authRole);
    $stmt = $pdo->query('SELECT * FROM settings_expenses ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->exec('INSERT INTO settings_expenses (require_description, enable_attachments, created_at, updated_at) VALUES (0,1,NOW(),NOW())');
        $stmt = $pdo->query('SELECT * FROM settings_expenses ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
    }
    respond($row);
}

if ($path === '/expense-settings' && $method === 'PATCH') {
    requireAdmin($authRole);
    $body = jsonBody();
    $sets = [];
    $bind = [];
    foreach (['require_description','max_amount','enable_attachments','default_category_id'] as $f) {
        if (array_key_exists($f, $body)) {
            $sets[] = "$f = ?";
            $bind[] = $body[$f];
        }
    }
    if (!$sets) jsonError('No fields to update');
    $stmt = $pdo->query('SELECT id FROM settings_expenses ORDER BY id ASC LIMIT 1');
    $row = $stmt->fetch();
    if (!$row) {
        $pdo->exec('INSERT INTO settings_expenses (require_description, enable_attachments, created_at, updated_at) VALUES (0,1,NOW(),NOW())');
        $stmt = $pdo->query('SELECT id FROM settings_expenses ORDER BY id ASC LIMIT 1');
        $row = $stmt->fetch();
    }
    $bind[] = (int)$row['id'];
    $sql = 'UPDATE settings_expenses SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    respond(['success' => true]);
}
if ($params = matchRoute('/users/:id', $path)) {
    requireAdmin($authRole);
    if ($method === 'PATCH') {
        $stmt = $pdo->prepare('SELECT role, allowed_routes FROM users WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        $current = $stmt->fetch();
        if (!$current) respond(['error' => 'Not found'], 404);

        $body = jsonBody();
        $fields = ['name', 'email', 'role', 'password', 'allowed_routes'];
        $sets = [];
        $bind = [];
        $roleTarget = $body['role'] ?? $current['role'];
        if (!in_array($roleTarget, ['admin','viewer'], true)) {
            respond(['error' => 'role must be admin or viewer'], 422);
        }
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                if ($f === 'password') {
                    $sets[] = 'password_hash = ?';
                    $bind[] = password_hash((string)$body['password'], PASSWORD_BCRYPT);
                } elseif ($f === 'email') {
                    // ensure unique email
                    $stmt = $pdo->prepare('SELECT COUNT(*) AS cnt FROM users WHERE email = ? AND id <> ?');
                    $stmt->execute([$body['email'], (int)$params['id']]);
                    if ((int)$stmt->fetch()['cnt'] > 0) {
                        respond(['error' => 'Email already exists'], 409);
                    }
                    $sets[] = 'email = ?';
                    $bind[] = $body['email'];
                } elseif ($f === 'allowed_routes') {
                    if ($roleTarget === 'admin') {
                        $sets[] = 'allowed_routes = ?';
                        $bind[] = null;
                    } else {
                        $routes = is_array($body['allowed_routes']) ? $body['allowed_routes'] : [];
                        if (empty($routes)) {
                            respond(['error' => 'allowed_routes is required for viewer'], 422);
                        }
                        $sets[] = 'allowed_routes = ?';
                        $bind[] = json_encode(array_values($routes));
                    }
                } else {
                    $sets[] = "$f = ?";
                    $bind[] = $body[$f];
                }
            }
        }
        if (!array_key_exists('allowed_routes', $body) && $roleTarget === 'admin') {
            $sets[] = 'allowed_routes = ?';
            $bind[] = null;
        }
        if (!$sets) respond(['error' => 'No fields to update'], 422);
        $bind[] = (int)$params['id'];
        $sql = 'UPDATE users SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_USER', "Updated user #{$params['id']}", 'user', (int)$params['id'], $body);
        respond(['success' => true]);
    }
    if ($method === 'DELETE') {
        $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        logActivity($pdo, $authUserId, 'DELETE_USER', "Deleted user #{$params['id']}", 'user', (int)$params['id']);
        respond(['success' => true]);
    }
}

// Periods
if ($path === '/periods' && $method === 'GET') {
    $stmt = $pdo->query('SELECT * FROM periods ORDER BY id DESC');
    respond($stmt->fetchAll());
}

if ($path === '/periods' && $method === 'POST') {
    requireWriteAccess($authRole);
    $body = jsonBody();
    if (empty($body['name'])) {
        respond(['error' => 'name is required'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO periods (name, start_date, end_date, is_closed, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['name'],
        $body['start_date'] ?? null,
        $body['end_date'] ?? null,
        !empty($body['is_closed']) ? 1 : 0,
    ]);
    $id = (int)$pdo->lastInsertId();
    respond(['id' => $id], 201);
}

if ($params = matchRoute('/periods/:id/close', $path)) {
    if ($method === 'PATCH') {
        requireWriteAccess($authRole);
        $stmt = $pdo->prepare('UPDATE periods SET is_closed = 1, updated_at = NOW() WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        respond(['success' => true]);
    }
}

// Transactions
if ($path === '/transactions' && $method === 'GET') {
    $where = [];
    $bind = [];
    if (!empty($_GET['period_id'])) {
        $where[] = 'period_id = ?';
        $bind[] = (int)$_GET['period_id'];
    }
    if (!empty($_GET['type'])) {
        $where[] = 'type = ?';
        $bind[] = $_GET['type'];
    }
    if (!empty($_GET['source'])) {
        $where[] = 'source = ?';
        $bind[] = $_GET['source'];
    }
    $sql = 'SELECT * FROM transactions';
    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }
    $sql .= ' ORDER BY date DESC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    respond($stmt->fetchAll());
}

// Expenses CRUD
if ($path === '/expenses' && $method === 'GET') {
    $where = [];
    $bind = [];
    if (!empty($_GET['date_from'])) { $where[] = 'date >= ?'; $bind[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'date <= ?'; $bind[] = $_GET['date_to']; }
    if (!empty($_GET['category_id'])) { $where[] = 'category_id = ?'; $bind[] = (int)$_GET['category_id']; }
    if (!empty($_GET['payment_method_id'])) { $where[] = 'payment_method_id = ?'; $bind[] = (int)$_GET['payment_method_id']; }
    if (!empty($_GET['q'])) { $where[] = 'description LIKE ?'; $bind[] = '%' . $_GET['q'] . '%'; }
    $sql = 'SELECT * FROM expenses';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY date DESC, id DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    respond($stmt->fetchAll());
}

if ($path === '/expenses' && $method === 'POST') {
    requireWriteAccess($authRole);
    $body = jsonBody();
    foreach (['date','amount','category_id','payment_method_id'] as $f) {
        if (!isset($body[$f])) jsonError("$f is required");
    }
    $stmt = $pdo->prepare('INSERT INTO expenses (date, amount, category_id, payment_method_id, description, attachment_url, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['date'],
        $body['amount'],
        (int)$body['category_id'],
        (int)$body['payment_method_id'],
        $body['description'] ?? null,
        $body['attachment_url'] ?? null,
        $authUserId ?: null,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'CREATE_EXPENSE', "Created expense #$id", 'expense', $id, $body);
    respond(['id' => $id], 201);
}

if ($params = matchRoute('/expenses/:id', $path)) {
    if ($method === 'PATCH') {
        requireWriteAccess($authRole);
        $body = jsonBody();
        $sets = [];
        $bind = [];
        foreach (['date','amount','category_id','payment_method_id','description','attachment_url'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $bind[] = $body[$f];
            }
        }
        if (!$sets) jsonError('No fields to update');
        $bind[] = (int)$params['id'];
        $sql = 'UPDATE expenses SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_EXPENSE', "Updated expense #{$params['id']}", 'expense', (int)$params['id'], $body);
        respond(['success' => true]);
    }
    if ($method === 'DELETE') {
        requireWriteAccess($authRole);
        $stmt = $pdo->prepare('DELETE FROM expenses WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        logActivity($pdo, $authUserId, 'DELETE_EXPENSE', "Deleted expense #{$params['id']}", 'expense', (int)$params['id']);
        respond(['success' => true]);
    }
}

if ($params = matchRoute('/transactions/:id', $path)) {
    if ($method === 'GET') {
        $stmt = $pdo->prepare('SELECT * FROM transactions WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        $row = $stmt->fetch();
        if (!$row) respond(['error' => 'Not found'], 404);
        respond($row);
    }
    if ($method === 'PATCH') {
        requireWriteAccess($authRole);
        $body = jsonBody();
        $fields = ['period_id', 'date', 'type', 'source', 'amount', 'description'];
        $sets = [];
        $bind = [];
        foreach ($fields as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $bind[] = $body[$f];
            }
        }
        if (!$sets) respond(['error' => 'No fields to update'], 422);
        $bind[] = (int)$params['id'];
        $sql = 'UPDATE transactions SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_TRANSACTION', "Updated transaction #{$params['id']}", 'transaction', (int)$params['id'], $body);
        respond(['success' => true]);
    }
    if ($method === 'DELETE') {
        requireWriteAccess($authRole);
        $stmt = $pdo->prepare('DELETE FROM transactions WHERE id = ?');
        $stmt->execute([(int)$params['id']]);
        logActivity($pdo, $authUserId, 'DELETE_TRANSACTION', "Deleted transaction #{$params['id']}", 'transaction', (int)$params['id']);
        respond(['success' => true]);
    }
}

if ($path === '/transactions' && $method === 'POST') {
    requireWriteAccess($authRole);
    $body = jsonBody();
    foreach (['period_id','date','type','source','amount'] as $f) {
        if (empty($body[$f]) && $body[$f] !== '0') {
            respond(['error' => "$f is required"], 422);
        }
    }
    $stmt = $pdo->prepare('INSERT INTO transactions (transaction_type_id, period_id, date, type, source, amount, description, created_by, created_at, updated_at, category_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)');
    $stmt->execute([
        $body['transaction_type_id'] ?? null,
        $body['period_id'],
        $body['date'],
        $body['type'],
        $body['source'],
        $body['amount'],
        $body['description'] ?? null,
        $authUserId ?: null,
        $body['category_id'] ?? null,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'CREATE_TRANSACTION', "Created transaction #$id ({$body['type']} {$body['amount']})", 'transaction', $id, $body);
    respond(['id' => $id], 201);
}

// Manual balances
if ($path === '/manual-balances' && $method === 'GET') {
    $where = [];
    $bind = [];
    if (!empty($_GET['period_id'])) {
        $where[] = 'period_id = ?';
        $bind[] = (int)$_GET['period_id'];
    }
    $sql = 'SELECT * FROM manual_balances';
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
    $sql .= ' ORDER BY created_at DESC';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bind);
    respond($stmt->fetchAll());
}

if ($path === '/manual-balances' && $method === 'POST') {
    requireWriteAccess($authRole);
    $body = jsonBody();
    if (!isset($body['period_id'], $body['manual_company_balance'])) {
        respond(['error' => 'period_id and manual_company_balance are required'], 422);
    }
    $stmt = $pdo->prepare('INSERT INTO manual_balances (period_id, manual_company_balance, note, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
    $stmt->execute([
        $body['period_id'],
        $body['manual_company_balance'],
        $body['note'] ?? null,
    ]);
    $id = (int)$pdo->lastInsertId();
    logActivity($pdo, $authUserId, 'UPDATE_MANUAL_BALANCE', "Updated manual balance #$id", 'manual_balance', $id, $body);
    respond(['id' => $id], 201);
}

if ($params = matchRoute('/manual-balances/:id', $path)) {
    if ($method === 'PATCH') {
        requireWriteAccess($authRole);
        $body = jsonBody();
        $sets = [];
        $bind = [];
        foreach (['period_id','manual_company_balance','note'] as $f) {
            if (array_key_exists($f, $body)) {
                $sets[] = "$f = ?";
                $bind[] = $body[$f];
            }
        }
        if (!$sets) respond(['error' => 'No fields to update'], 422);
        $bind[] = (int)$params['id'];
        $stmt = $pdo->prepare('UPDATE manual_balances SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE id = ?');
        $stmt->execute($bind);
        logActivity($pdo, $authUserId, 'UPDATE_MANUAL_BALANCE', "Updated manual balance #{$params['id']}", 'manual_balance', (int)$params['id'], $body);
        respond(['success' => true]);
    }
}

// Dashboard summary
if ($path === '/dashboard/summary' && $method === 'GET') {
    $periodId = isset($_GET['period_id']) ? (int)$_GET['period_id'] : null;
    if (!$periodId) respond(['error' => 'period_id is required'], 422);
    $stmt = $pdo->prepare('SELECT * FROM periods WHERE id = ?');
    $stmt->execute([$periodId]);
    $period = $stmt->fetch();
    if (!$period) respond(['error' => 'Period not found'], 404);

    $sum = function($type = null, $source = null) use ($pdo, $periodId) {
        $sql = 'SELECT COALESCE(SUM(amount),0) AS total FROM transactions WHERE period_id = ?';
        $bind = [$periodId];
        if ($type) { $sql .= ' AND type = ?'; $bind[] = $type; }
        if ($source) { $sql .= ' AND source = ?'; $bind[] = $source; }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($bind);
        return (float)$stmt->fetch()['total'];
    };

    $total_income = $sum('income');
    $income_general = $sum('income', 'sales_general');
    $income_person1 = $sum('income', 'income_person1');
    $income_person2 = $sum('income', 'income_person2');
    $total_expenses = $sum('expense');
    $expense_private_khaled = $sum('expense', 'expense_private_khaled');
    $expense_private_omar = $sum('expense', 'expense_private_omar');
    $withdrawal_person1 = $sum('withdrawal', 'withdrawal_person1');
    $withdrawal_person2 = $sum('withdrawal', 'withdrawal_person2');
    $debt_khaled_to_omar = $sum('partner_debt', 'debt_khaled_to_omar');
    $debt_omar_to_khaled = $sum('partner_debt', 'debt_omar_to_khaled');

    $stmt = $pdo->prepare('SELECT manual_company_balance FROM manual_balances WHERE period_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->execute([$periodId]);
    $manual_company_balance = $stmt->fetch()['manual_company_balance'] ?? null;

    $theoretical_balance = $total_income - $total_expenses - $withdrawal_person1 - $withdrawal_person2;
    $difference = $manual_company_balance === null ? null : (float)$manual_company_balance - $theoretical_balance;

    respond([
        'period' => $period['name'],
        'total_income' => $total_income,
        'income_general' => $income_general,
        'income_person1' => $income_person1,
        'income_person2' => $income_person2,
        'total_expenses' => $total_expenses,
        'expense_private_khaled' => $expense_private_khaled,
        'expense_private_omar' => $expense_private_omar,
        'withdrawal_person1' => $withdrawal_person1,
        'withdrawal_person2' => $withdrawal_person2,
        'debt_khaled_to_omar' => $debt_khaled_to_omar,
        'debt_omar_to_khaled' => $debt_omar_to_khaled,
        'net_company_profit' => $theoretical_balance,
        'manual_company_balance' => $manual_company_balance !== null ? (float)$manual_company_balance : null,
        'theoretical_balance' => $theoretical_balance,
        'difference' => $difference,
    ]);
}

// Activity logs (GET only)
if ($path === '/activity-logs' && $method === 'GET') {
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;
    $where = [];
    $bind = [];
    if (!empty($_GET['action']) && $_GET['action'] !== 'all') { $where[] = 'action = ?'; $bind[] = $_GET['action']; }
    if (!empty($_GET['user_id'])) { $where[] = 'user_id = ?'; $bind[] = (int)$_GET['user_id']; }
    if (!empty($_GET['date_from'])) { $where[] = 'DATE(created_at) >= ?'; $bind[] = $_GET['date_from']; }
    if (!empty($_GET['date_to'])) { $where[] = 'DATE(created_at) <= ?'; $bind[] = $_GET['date_to']; }
    $sqlWhere = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $stmt = $pdo->prepare("SELECT COUNT(*) AS cnt FROM activity_logs $sqlWhere");
    $stmt->execute($bind);
    $total = (int)$stmt->fetch()['cnt'];

    $stmt = $pdo->prepare("SELECT * FROM activity_logs $sqlWhere ORDER BY created_at DESC, id DESC LIMIT $limit OFFSET $offset");
    $stmt->execute($bind);
    $rows = $stmt->fetchAll();

    respond([
        'data' => $rows,
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
        ],
    ]);
}

respond(['error' => 'Not Found', 'path' => $path], 404);

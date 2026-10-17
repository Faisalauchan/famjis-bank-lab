<?php
require_once __DIR__ . '/lib.php';
header('Content-Type: application/json');

$resource = $_GET['resource'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($resource === '' && isset($_SERVER['PATH_INFO'])) {
  $parts = explode('/', trim($_SERVER['PATH_INFO'], '/'));
  $resource = $parts[0] ?? '';
  $id = isset($parts[1]) ? (int)$parts[1] : 0;
}

$caller = api_user();
if (!$caller) {
  http_response_code(401);
  echo json_encode(['error' => 'missing or invalid token']);
  exit;
}

if ($resource === 'user') {
  // VULN: the caller's identity is never compared to the requested id.
  $row = db()->prepare("SELECT id, username, name, email, acct_type, balance, secret_note FROM users WHERE id = ?");
  $row->execute([$id]);
  $user = $row->fetch();
  if (!$user) { http_response_code(404); echo json_encode(['error' => 'no such account']); exit; }
  $tx = db()->prepare("SELECT tdate, descr, amount, balance_after FROM txns WHERE user_id = ? ORDER BY tdate DESC, id DESC");
  $tx->execute([$id]);
  echo json_encode([
    'account_number' => acct_no($user['id']),
    'customer_id'    => (int)$user['id'],
    'holder'         => $user['name'],
    'email'          => $user['email'],
    'account_type'   => $user['acct_type'],
    'balance'        => (float)$user['balance'],
    'note'           => $user['secret_note'],
    'transactions'   => $tx->fetchAll(),
  ], JSON_PRETTY_PRINT);
  exit;
}

if ($resource === 'admin') {
  // VULN: trusts the role claim in a forgeable token.
  if (($caller['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'admin role required']);
    exit;
  }
  echo json_encode([
    'message' => 'Admin API access granted.',
    'flag'    => make_flag('jwt'),
  ], JSON_PRETTY_PRINT);
  exit;
}

http_response_code(404);
echo json_encode(['error' => 'unknown resource', 'try' => ['/api/user/{id}', '/api/admin']]);

<?php
// Seeds the SQLite database if not already present, and writes the upload flag.
require_once __DIR__ . '/lib.php';

if (!is_dir(dirname(DB_PATH)))  mkdir(dirname(DB_PATH), 0775, true);
if (!is_dir(PRIVATE_DIR))       mkdir(PRIVATE_DIR, 0775, true);

// upload flag lives outside the web root; only reachable via RCE
file_put_contents(PRIVATE_DIR . '/upload_flag.txt', make_flag('upload') . "\n");

if (file_exists(DB_PATH) && filesize(DB_PATH) > 0) return;  // keep student data

$pdo = db();
$pdo->exec("CREATE TABLE users (
  id INTEGER PRIMARY KEY, username TEXT UNIQUE, pass TEXT, role TEXT,
  name TEXT, email TEXT, acct_type TEXT, balance REAL, secret_note TEXT)");
$pdo->exec("CREATE TABLE txns (
  id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, tdate TEXT,
  descr TEXT, amount REAL, balance_after REAL)");
$pdo->exec("CREATE TABLE messages (
  id INTEGER PRIMARY KEY AUTOINCREMENT, author TEXT, body TEXT, created TEXT)");

$adminPass = getenv('ADMIN_PASS') ?: 'S3cretAdmin_pw!';
$uins = $pdo->prepare("INSERT INTO users (id,username,pass,role,name,email,acct_type,balance,secret_note)
                       VALUES (?,?,?,?,?,?,?,?,?)");

$uins->execute([1, 'admin', password_hash($adminPass, PASSWORD_DEFAULT), 'admin',
  'Branch Admin', 'admin@famjisbank.test', 'Operations', 0.00, null]);
$uins->execute([1023, 'you', password_hash('password123', PASSWORD_DEFAULT), 'user',
  'Amara Okonkwo', 'amara@customer.test', 'Premium Current', 4210.55, 'Your own account. Nothing secret here.']);
$uins->execute([1024, 'j.okafor', password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'user',
  'Jideofor Okafor', 'j.okafor@customer.test', 'Platinum Savings', 88120.00, make_flag('idor')]);

$fillers = [
  [1025, 'a.bello',   'Aisha Bello',     'Classic Current',  1004.20],
  [1026, 's.mwangi',  'Samuel Mwangi',   'Classic Current',   640.00],
  [1027, 'r.nwaogu',  'Ruth Nwaogu',     'Premium Current', 12900.10],
  [1028, 'u.michael', 'Ubong Michael',   'Student',           305.75],
  [1029, 'f.yahya',   'Faris Yahya',     'Platinum Savings', 7430.00],
  [1030, 'd.auchan',  'Digital Auchan',  'Business',        53001.99],
];
foreach ($fillers as $f) {
  $uins->execute([$f[0], $f[1], password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT), 'user',
    $f[2], strtolower(str_replace(' ', '.', $f[2])) . '@customer.test', $f[3], $f[4], 'No notes on file.']);
}

// transactions for a few accounts so statements look real
$tins = $pdo->prepare("INSERT INTO txns (user_id,tdate,descr,amount,balance_after) VALUES (?,?,?,?,?)");
function seed_txns($tins, $uid, $start, $lines) {
  $bal = $start;
  foreach ($lines as $i => $ln) {
    $bal += $ln[2];
    $tins->execute([$uid, $ln[0], $ln[1], $ln[2], round($bal, 2)]);
  }
}
seed_txns($tins, 1023, 3800.00, [
  ['2026-10-02', 'Salary credit - Tech Solutions Ltd', 950.00],
  ['2026-10-04', 'POS - Shoprite Kaduna', -62.40],
  ['2026-10-06', 'Transfer to R. Nwaogu', -200.00],
  ['2026-10-09', 'Airtime top-up', -15.00],
  ['2026-10-12', 'Refund - Jumia', 40.00],
  ['2026-10-14', 'ATM withdrawal', -300.00],
  ['2026-10-15', 'Interest', 0.35],
]);
seed_txns($tins, 1024, 85000.00, [
  ['2026-10-01', 'Salary credit - Okafor Holdings', 3200.00],
  ['2026-10-05', 'Fixed deposit rollover', -1000.00],
  ['2026-10-10', 'Dividend payout', 920.00],
  ['2026-10-13', 'Wire transfer received', 0.00],
]);
foreach ([1025,1026,1027,1029,1030] as $uid) {
  seed_txns($tins, $uid, 500.00, [
    ['2026-10-03', 'POS purchase', -33.10],
    ['2026-10-08', 'Deposit', 120.00],
    ['2026-10-14', 'Service charge', -2.50],
  ]);
}

$pdo->prepare("INSERT INTO messages (author,body,created) VALUES (?,?,?)")
    ->execute(['FAMJIS Support', 'Welcome to FAMJIS BANK support. Leave a message and an agent will review it shortly.', date('Y-m-d H:i')]);

echo "seeded for STUDENT_ID=" . STUDENT_ID . "\n";

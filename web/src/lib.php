<?php
// FAMJIS BANK - shared library (config, db, jwt, flags, auth, layout)
// WARNING: this application is deliberately insecure. Local training only.

@include '/var/www/private/generated.php';
if (!defined('STUDENT_ID'))  define('STUDENT_ID',  getenv('STUDENT_ID')  ?: 'DEMO');
if (!defined('SECRET_SALT')) define('SECRET_SALT', getenv('SECRET_SALT') ?: 'change_me');

define('BANK_HOME',   getenv('BANK_HOME') ?: '/var/www');
define('DB_PATH',     BANK_HOME . '/data/bank.sqlite');
define('PRIVATE_DIR', BANK_HOME . '/private');
define('JWT_SECRET',  'secret123');   // deliberately weak, crackable

// ---------- database ----------
function db() {
  static $pdo = null;
  if ($pdo === null) {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
  }
  return $pdo;
}

// ---------- flags (deterministic, per student) ----------
function make_flag($bug) {
  $sid = strtolower(trim(STUDENT_ID));
  $h = substr(hash('sha256', $sid . '|' . $bug . '|' . SECRET_SALT), 0, 6);
  return 'ICDFA{' . $bug . '_' . $h . '}';
}

// ---------- helpers ----------
function money($n)   { return 'NGN ' . number_format((float)$n, 2); }
function acct_no($id){ return '3000' . str_pad((string)$id, 6, '0', STR_PAD_LEFT); }
function e($s)       { return htmlspecialchars((string)$s, ENT_QUOTES); }

// ---------- JWT (deliberately vulnerable) ----------
function b64url($d) { return rtrim(strtr(base64_encode($d), '+/', '-_'), '='); }
function b64url_dec($d) {
  $pad = (4 - strlen($d) % 4) % 4;
  return base64_decode(strtr($d, '-_', '+/') . str_repeat('=', $pad));
}
function jwt_make($payload, $secret = JWT_SECRET) {
  $h = b64url(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
  $p = b64url(json_encode($payload));
  $sig = b64url(hash_hmac('sha256', "$h.$p", $secret, true));
  return "$h.$p.$sig";
}
// Returns the payload array if "valid", or null. Insecure on purpose:
//  - accepts alg:none with no signature
//  - verifies HS256 against a weak, guessable secret
function jwt_verify($token) {
  $parts = explode('.', $token);
  if (count($parts) < 2) return null;
  $h = $parts[0]; $p = $parts[1]; $sig = $parts[2] ?? '';
  $head = json_decode(b64url_dec($h), true);
  $pay  = json_decode(b64url_dec($p), true);
  if (!is_array($head) || !is_array($pay)) return null;
  $alg = strtolower($head['alg'] ?? '');
  if ($alg === 'none') return $pay;                       // VULN: alg:none
  if ($alg === 'hs256') {
    $expected = b64url(hash_hmac('sha256', "$h.$p", JWT_SECRET, true));
    return hash_equals($expected, $sig) ? $pay : null;     // VULN: weak secret
  }
  return null;
}

// ---------- sessions / auth ----------
function start_sess() {
  if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('session');
    session_start();
  }
}
function current_user() {   // browser UI user (session cookie)
  start_sess();
  if (empty($_SESSION['uid'])) return null;
  return ['uid' => $_SESSION['uid'], 'role' => $_SESSION['role'], 'name' => $_SESSION['name']];
}
function require_login() {
  $u = current_user();
  if (!$u) { header('Location: login.php'); exit; }
  return $u;
}
function api_user() {        // API user (JWT bearer or token cookie)
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
  if (stripos($hdr, 'Bearer ') === 0) return jwt_verify(trim(substr($hdr, 7)));
  if (!empty($_COOKIE['token'])) return jwt_verify($_COOKIE['token']);
  return null;
}

// ---------- layout ----------
function page_head($title, $chrome = true) {
  $t = e($title);
  echo "<!doctype html><html lang='en'><head><meta charset='utf-8'>";
  echo "<meta name='viewport' content='width=device-width,initial-scale=1'>";
  echo "<title>$t &middot; FAMJIS BANK</title>";
  echo "<style>" . css() . "</style></head><body>";
  if ($chrome) topbar();
  echo "<div class='wrap'>";
}
function page_foot() {
  $u = current_user();
  echo "</div><footer class='foot'>FAMJIS BANK &middot; Member FDIC-style demo &middot; "
     . "<span class='warn'>Training environment, not a real bank. Do not deploy publicly.</span>";
  // Developer API access lives discreetly in the footer (not on the main dashboard).
  if ($u) echo " &middot; <a class='devlink' href='dashboard.php#devapi'>Developer API</a>";
  echo "</footer>";
  echo "</body></html>";
}
function topbar() {
  $u = current_user();
  $isAdmin = $u && ($u['role'] ?? '') === 'admin';
  echo "<header class='topbar" . ($isAdmin ? ' admin' : '') . "'><div class='bar'>";
  echo "<a class='brand' href='dashboard.php'><span class='logo'>FB</span><span>FAMJIS <b>BANK</b></span>"
     . ($isAdmin ? "<span class='admintag'>Admin</span>" : "") . "</a>";
  echo "<nav class='navlinks'>";
  echo "<a href='dashboard.php'>Overview</a><a href='account.php'>Accounts</a>";
  echo "<a href='messages.php'>Support</a><a href='upload.php'>Documents</a>";
  if ($isAdmin) echo "<a class='adminnav' href='vault.php'>Admin Console</a>";
  echo "</nav>";
  if ($u) {
    $initials = strtoupper(substr(trim($u['name']), 0, 1));
    echo "<div class='userbox'><span class='avatar" . ($isAdmin ? ' admin' : '') . "'>$initials</span>";
    echo "<span class='uname'>" . e($u['name']) . "</span>";
    if ($isAdmin) echo "<span class='role'>Administrator</span>";
    echo "<a class='logout' href='logout.php'>Sign out</a></div>";
  } else {
    echo "<div class='userbox'><a class='logout' href='login.php'>Sign in</a></div>";
  }
  echo "</div></header>";
}
function css() {
  return <<<CSS
:root{--green:#0b5d3b;--green-d:#083a26;--green-l:#13764b;--gold:#c8a24a;--gold-l:#e7c877;
--ink:#14241c;--muted:#6b7a72;--line:#e5e9e6;--bg:#eef2ef;--card:#ffffff;--credit:#0b7a3b;--debit:#b23b3b}
*{box-sizing:border-box}
body{margin:0;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;background:var(--bg);color:var(--ink);line-height:1.5}
a{color:var(--green);text-decoration:none}a:hover{text-decoration:underline}
.topbar{background:var(--green);color:#fff}
.bar{max-width:1000px;margin:0 auto;display:flex;align-items:center;gap:22px;padding:12px 20px}
.brand{display:flex;align-items:center;gap:10px;color:#fff;font-size:18px;font-weight:600}
.brand:hover{text-decoration:none}
.logo{width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,var(--gold),var(--gold-l));
color:var(--green-d);display:grid;place-items:center;font-weight:800;font-size:15px}
.navlinks{display:flex;gap:4px;flex:1}
.navlinks a{color:#d4e7dd;font-size:14px;padding:6px 12px;border-radius:7px}
.navlinks a:hover{color:#fff;background:rgba(255,255,255,.1);text-decoration:none}
.userbox{display:flex;align-items:center;gap:10px}
.avatar{width:30px;height:30px;border-radius:50%;background:var(--gold);color:var(--green-d);display:grid;place-items:center;font-weight:700;font-size:13px}
.uname{color:#eaf3ee;font-size:14px}
.logout{color:#cfe3d9;font-size:13px;border:1px solid rgba(255,255,255,.25);padding:5px 11px;border-radius:7px}
.logout:hover{background:rgba(255,255,255,.12);text-decoration:none;color:#fff}
.wrap{max-width:1000px;margin:26px auto;padding:0 20px}
.cols{display:grid;grid-template-columns:2fr 1fr;gap:20px}
@media(max-width:760px){.cols{grid-template-columns:1fr}.bar{flex-wrap:wrap}}
.card{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:22px;margin-bottom:20px;box-shadow:0 1px 3px rgba(20,36,28,.05)}
.card h2{margin:0 0 14px;font-size:16px;letter-spacing:.2px}
.sub{color:var(--muted);font-size:13px}
h1{font-size:24px;margin:0 0 6px}
.balance{background:linear-gradient(135deg,var(--green-l),var(--green-d));color:#fff;border:0}
.balance .lbl{color:#bfe0cf;font-size:13px;text-transform:uppercase;letter-spacing:1px}
.balance .amt{font-size:36px;font-weight:700;margin:6px 0}
.balance .acct{color:#d7ebe0;font-size:13px;font-family:Consolas,monospace}
.chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.chip{background:rgba(255,255,255,.14);color:#fff;border-radius:9px;padding:9px 14px;font-size:13px;font-weight:600}
.actions{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}
.action{display:flex;align-items:center;gap:12px;border:1px solid var(--line);border-radius:11px;padding:14px;background:#fafcfb}
.action:hover{border-color:var(--green);text-decoration:none}
.ico{width:38px;height:38px;border-radius:10px;background:#e8f2ec;color:var(--green);display:grid;place-items:center;font-size:18px;flex:none}
.action .t{font-size:14px;font-weight:600;color:var(--ink)}.action .d{font-size:12px;color:var(--muted)}
.tx{display:flex;align-items:center;gap:14px;padding:13px 0;border-bottom:1px solid var(--line)}
.tx:last-child{border-bottom:0}
.tx .ti{width:34px;height:34px;border-radius:9px;display:grid;place-items:center;font-size:15px;flex:none}
.tx .ti.in{background:#e3f4ea;color:var(--credit)}.tx .ti.out{background:#fbe9e9;color:var(--debit)}
.tx .meta{flex:1}.tx .desc{font-size:14px}.tx .date{font-size:12px;color:var(--muted)}
.tx .amt{font-weight:600;font-size:14px}.tx .amt.in{color:var(--credit)}.tx .amt.out{color:var(--debit)}
table.stmt{width:100%;border-collapse:collapse}
table.stmt th{text-align:left;font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);padding:10px;border-bottom:2px solid var(--line)}
table.stmt td{padding:11px 10px;border-bottom:1px solid var(--line);font-size:14px}
.btn{display:inline-block;background:var(--green);color:#fff;border:0;border-radius:9px;padding:11px 20px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:var(--green-d);text-decoration:none}
.btn.gold{background:var(--gold);color:var(--green-d)}
label{display:block;font-size:13px;color:var(--muted);margin:12px 0 5px;font-weight:600}
input,textarea{width:100%;padding:11px;border:1px solid #cdd6d1;border-radius:9px;font-size:14px;font-family:inherit}
input:focus,textarea:focus{outline:0;border-color:var(--green)}
code{font-family:Consolas,monospace;background:#0f1a15;color:#8fe8b8;padding:2px 7px;border-radius:5px;font-size:13px}
pre{font-family:Consolas,monospace;background:#0f1a15;color:#d9efe2;padding:14px;border-radius:10px;overflow:auto;font-size:13px}
.flag{display:inline-block;background:#0f1a15;color:var(--gold-l);padding:12px 18px;border-radius:10px;font-family:Consolas,monospace;font-size:16px;letter-spacing:.5px}
.foot{max-width:1000px;margin:10px auto 40px;padding:0 20px;color:var(--muted);font-size:12px}
.warn{color:#9a6a00}
.badge{display:inline-block;background:#e8f2ec;color:var(--green);font-size:11px;font-weight:700;padding:3px 9px;border-radius:20px;text-transform:uppercase;letter-spacing:.5px}
.thread{display:flex;flex-direction:column;gap:12px}
.bubble{max-width:80%;padding:11px 15px;border-radius:14px;font-size:14px}
.bubble.them{background:#eef3f0;border-top-left-radius:4px;align-self:flex-start}
.bubble.me{background:var(--green);color:#fff;border-top-right-radius:4px;align-self:flex-end}
.bubble .who{font-size:11px;color:var(--muted);margin-bottom:3px}
.bubble.me .who{color:#cfe3d9}
.drop{border:2px dashed #bcd0c6;border-radius:12px;padding:30px;text-align:center;color:var(--muted);background:#fafcfb}
.notice{border-radius:10px;padding:12px 15px;font-size:14px;margin-bottom:14px}
.notice.ok{background:#e3f4ea;color:#0b5d3b}.notice.err{background:#fbe9e9;color:#9b2c2c}
/* ---- admin chrome (visibly distinct from a normal customer) ---- */
.topbar.admin{background:linear-gradient(135deg,#0a1f16,var(--green-d));border-bottom:3px solid var(--gold)}
.topbar.admin .navlinks a{color:#cbe0d4}
.admintag{margin-left:9px;background:var(--gold);color:var(--green-d);font-size:10px;font-weight:800;
padding:2px 8px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;vertical-align:middle}
.navlinks a.adminnav{background:var(--gold);color:var(--green-d);font-weight:700}
.navlinks a.adminnav:hover{background:var(--gold-l);color:var(--green-d)}
.avatar.admin{background:var(--gold);box-shadow:0 0 0 2px var(--gold-l)}
.role{color:var(--gold-l);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
border:1px solid rgba(200,162,74,.5);padding:2px 8px;border-radius:20px}
.adminbar{background:linear-gradient(135deg,#0a1f16,var(--green-d));color:#fff;border:0}
.adminbar h2{color:var(--gold-l)}
.adminbar .sub{color:#bcd3c7}
.adminbar .action{background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15)}
.adminbar .action:hover{border-color:var(--gold)}
.adminbar .action .t{color:#fff}.adminbar .action .d{color:#bcd3c7}
.adminbar .ico{background:rgba(200,162,74,.2);color:var(--gold-l)}
details.devapi{background:var(--card);border:1px solid var(--line);border-radius:14px;padding:0;margin-bottom:20px;box-shadow:0 1px 3px rgba(20,36,28,.05)}
details.devapi>summary{cursor:pointer;list-style:none;padding:16px 22px;font-size:14px;font-weight:600;color:var(--muted)}
details.devapi>summary::-webkit-details-marker{display:none}
details.devapi>summary::before{content:'\\25B8';margin-right:8px;color:var(--green)}
details.devapi[open]>summary::before{content:'\\25BE'}
details.devapi>.inner{padding:0 22px 20px}
a.devlink{color:var(--muted)}
CSS;
}

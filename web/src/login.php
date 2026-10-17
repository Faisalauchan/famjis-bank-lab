<?php
require_once __DIR__ . '/lib.php';
start_sess();
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';
  $row = db()->prepare("SELECT * FROM users WHERE username = ?");
  $row->execute([$u]);
  $user = $row->fetch();
  if ($user && password_verify($p, $user['pass'])) {
    $_SESSION['uid']  = (int)$user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $token = jwt_make(['uid' => (int)$user['id'], 'role' => $user['role'], 'name' => $user['name']]);
    setcookie('token', $token, ['path' => '/', 'httponly' => false, 'samesite' => 'Lax']);
    header('Location: dashboard.php');
    exit;
  }
  $err = 'Invalid username or password.';
}
page_head('Sign in', false);
?>
<style>
.login{display:grid;grid-template-columns:1fr 1fr;min-height:100vh}
.hero{background:linear-gradient(140deg,#0b5d3b,#083a26);color:#fff;padding:56px 48px;display:flex;flex-direction:column;justify-content:center}
.hero .logo{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,#c8a24a,#e7c877);color:#083a26;display:grid;place-items:center;font-weight:800;font-size:20px;margin-bottom:28px}
.hero h1{font-size:34px;margin:0 0 14px;color:#fff}
.hero p{color:#bfe0cf;font-size:16px;max-width:420px}
.hero ul{color:#d7ebe0;font-size:14px;margin-top:24px;padding-left:18px}.hero li{margin:8px 0}
.panel{display:flex;align-items:center;justify-content:center;padding:40px}
.loginbox{width:100%;max-width:380px}
@media(max-width:800px){.login{grid-template-columns:1fr}.hero{display:none}}
</style>
<div class="login">
  <div class="hero">
    <div class="logo">FB</div>
    <h1>Banking you can trust</h1>
    <p>Welcome to FAMJIS BANK online. Manage accounts, review statements and reach support, all in one place.</p>
    <ul>
      <li>24/7 access to your accounts</li>
      <li>Instant transfers across FAMJIS</li>
      <li>Secure document upload</li>
    </ul>
  </div>
  <div class="panel">
    <div class="loginbox">
      <h1 style="font-size:22px">Sign in to online banking</h1>
      <p class="sub">Enter your FAMJIS BANK credentials.</p>
      <?php if ($err) echo "<div class='notice err'>" . e($err) . "</div>"; ?>
      <form method="post">
        <label>Username</label>
        <input name="username" autofocus autocomplete="username">
        <label>Password</label>
        <input name="password" type="password" autocomplete="current-password">
        <button class="btn" style="width:100%;margin-top:18px" type="submit">Sign in</button>
      </form>
      <p class="sub" style="margin-top:18px">Demo customer: <code>you</code> / <code>password123</code></p>
    </div>
  </div>
</div>
<?php page_foot();

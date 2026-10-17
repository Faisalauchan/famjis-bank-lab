<?php
require_once __DIR__ . '/lib.php';
$u = current_user();   // browser session, NOT the API token
if (!$u || $u['role'] !== 'admin') {
  http_response_code(403);
  page_head('Admin');
  echo "<div class='card'><h1>403 &middot; Restricted</h1>"
     . "<p class='sub'>The branch administrator console requires a valid admin session.</p></div>";
  page_foot();
  exit;
}
// quick stats for flavour
$n = db()->query("SELECT COUNT(*) c FROM users")->fetch()['c'];
page_head('Admin console');
?>
<h1>Branch administrator console</h1>
<p class="sub">Signed in with an administrator session.</p>
<div class="cols" style="margin-top:14px">
  <div class="card">
    <h2>Overview</h2>
    <p class="sub">Customers on file</p><p style="font-size:24px;font-weight:700"><?php echo (int)$n; ?></p>
    <p class="sub">Pending support messages</p>
    <p style="font-size:24px;font-weight:700"><?php echo (int)db()->query("SELECT COUNT(*) c FROM messages")->fetch()['c']; ?></p>
  </div>
  <div class="card">
    <h2>Restricted</h2>
    <p class="sub">XSS task flag:</p>
    <p class="flag"><?php echo e(make_flag('xss')); ?></p>
  </div>
</div>
<?php page_foot();

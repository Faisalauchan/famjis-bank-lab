<?php
require_once __DIR__ . '/lib.php';
$u = require_login();
$row = db()->prepare("SELECT * FROM users WHERE id = ?");
$row->execute([$u['uid']]);
$me = $row->fetch();
$tx = db()->prepare("SELECT * FROM txns WHERE user_id = ? ORDER BY tdate DESC, id DESC LIMIT 5");
$tx->execute([$u['uid']]);
$txns = $tx->fetchAll();
$token = $_COOKIE['token'] ?? '(sign in again)';
page_head('Overview');
?>
<h1>Good day, <?php echo e(explode(' ', $me['name'])[0]); ?></h1>
<p class="sub">Here is a snapshot of your account.</p>

<div class="cols" style="margin-top:18px">
  <div>
    <div class="card balance">
      <div class="lbl"><?php echo e($me['acct_type']); ?></div>
      <div class="amt"><?php echo money($me['balance']); ?></div>
      <div class="acct">Account <?php echo acct_no($me['id']); ?></div>
      <div class="chips">
        <span class="chip">Available <?php echo money($me['balance']); ?></span>
        <span class="chip">Customer ID <?php echo (int)$me['id']; ?></span>
      </div>
    </div>

    <div class="card">
      <h2>Recent activity</h2>
      <?php foreach ($txns as $t): $in = $t['amount'] >= 0; ?>
        <div class="tx">
          <div class="ti <?php echo $in ? 'in' : 'out'; ?>"><?php echo $in ? '&#8601;' : '&#8599;'; ?></div>
          <div class="meta">
            <div class="desc"><?php echo e($t['descr']); ?></div>
            <div class="date"><?php echo e($t['tdate']); ?></div>
          </div>
          <div class="amt <?php echo $in ? 'in' : 'out'; ?>">
            <?php echo ($in ? '+' : '-') . number_format(abs($t['amount']), 2); ?>
          </div>
        </div>
      <?php endforeach; ?>
      <p style="margin:14px 0 0"><a href="account.php">View full statement &rarr;</a></p>
    </div>
  </div>

  <div>
    <div class="card">
      <h2>Quick actions</h2>
      <div class="actions">
        <a class="action" href="account.php"><span class="ico">&#128179;</span><span><span class="t">Accounts</span><span class="d">Balances &amp; statement</span></span></a>
        <a class="action" href="messages.php"><span class="ico">&#128172;</span><span><span class="t">Support</span><span class="d">Message an agent</span></span></a>
        <a class="action" href="upload.php"><span class="ico">&#128196;</span><span><span class="t">Documents</span><span class="d">Upload a file</span></span></a>
        <a class="action" href="account.php"><span class="ico">&#128178;</span><span><span class="t">Transfer</span><span class="d">Move money</span></span></a>
      </div>
    </div>

    <div class="card">
      <h2>Developer API access <span class="badge">beta</span></h2>
      <p class="sub">Your personal bearer token for the FAMJIS account API.</p>
      <pre style="white-space:pre-wrap;word-break:break-all"><?php echo e($token); ?></pre>
      <p class="sub">Try: <code>curl -H "Authorization: Bearer &lt;token&gt;" http://localhost:8080/api/user/<?php echo (int)$me['id']; ?></code></p>
    </div>
  </div>
</div>
<?php page_foot();

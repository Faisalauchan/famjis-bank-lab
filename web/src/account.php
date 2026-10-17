<?php
require_once __DIR__ . '/lib.php';
$u = require_login();
$row = db()->prepare("SELECT * FROM users WHERE id = ?");
$row->execute([$u['uid']]);
$me = $row->fetch();
$tx = db()->prepare("SELECT * FROM txns WHERE user_id = ? ORDER BY tdate DESC, id DESC");
$tx->execute([$u['uid']]);
$txns = $tx->fetchAll();
page_head('Accounts');
?>
<h1>Your account</h1>
<div class="cols" style="margin-top:14px">
  <div class="card">
    <h2>Statement</h2>
    <table class="stmt">
      <tr><th>Date</th><th>Description</th><th style="text-align:right">Amount</th><th style="text-align:right">Balance</th></tr>
      <?php foreach ($txns as $t): $in = $t['amount'] >= 0; ?>
        <tr>
          <td class="sub"><?php echo e($t['tdate']); ?></td>
          <td><?php echo e($t['descr']); ?></td>
          <td style="text-align:right;color:<?php echo $in ? 'var(--credit)' : 'var(--debit)'; ?>">
            <?php echo ($in ? '+' : '-') . number_format(abs($t['amount']), 2); ?></td>
          <td style="text-align:right"><?php echo number_format($t['balance_after'], 2); ?></td>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
  <div>
    <div class="card">
      <h2>Account details</h2>
      <p class="sub">Holder</p><p><?php echo e($me['name']); ?></p>
      <p class="sub">Account number</p><p><?php echo acct_no($me['id']); ?></p>
      <p class="sub">Type</p><p><?php echo e($me['acct_type']); ?></p>
      <p class="sub">Balance</p><p style="font-size:20px;font-weight:700"><?php echo money($me['balance']); ?></p>
    </div>
    <div class="card">
      <h2>Account API</h2>
      <p class="sub">Statements are served by the account API:</p>
      <pre>GET /api/user/<?php echo (int)$me['id']; ?>
Authorization: Bearer &lt;token&gt;</pre>
      <p class="sub">The API returns the full record for the requested account number.</p>
    </div>
  </div>
</div>
<?php page_foot();

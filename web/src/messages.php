<?php
require_once __DIR__ . '/lib.php';
$u = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $body = $_POST['body'] ?? '';
  db()->prepare("INSERT INTO messages (author, body, created) VALUES (?,?,?)")
      ->execute([$u['name'], $body, date('Y-m-d H:i')]);
  header('Location: messages.php');
  exit;
}

$msgs = db()->query("SELECT * FROM messages ORDER BY id ASC")->fetchAll();
$meName = $u['name'];
page_head('Support');
?>
<h1>Support</h1>
<p class="sub">Message a FAMJIS BANK agent. New messages are reviewed by the branch team.</p>

<div class="card" style="margin-top:14px">
  <div class="thread">
    <?php foreach ($msgs as $m): $mine = ($m['author'] === $meName); ?>
      <div class="bubble <?php echo $mine ? 'me' : 'them'; ?>">
        <div class="who"><?php echo e($m['author']); ?> &middot; <?php echo e($m['created']); ?></div>
        <!-- VULN: message body rendered without encoding (stored XSS) -->
        <div><?php echo $m['body']; ?></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<div class="card">
  <form method="post">
    <label>New message</label>
    <textarea name="body" rows="3" placeholder="How can we help?"></textarea>
    <button class="btn" type="submit" style="margin-top:12px">Send to support</button>
  </form>
</div>
<?php page_foot();

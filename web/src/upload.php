<?php
require_once __DIR__ . '/lib.php';
$u = require_login();
$notice = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc']) && $_FILES['doc']['name'] !== '') {
  $name = basename($_FILES['doc']['name']);
  // VULN: weak filter. Only a trailing ".php" is blocked, so double extensions
  // (shell.php.jpg) and alternates (.phtml, .php5) pass, and the uploads dir
  // is configured to execute them.
  if (preg_match('/\.php$/i', $name)) {
    $notice = "<div class='notice err'>That file type is not allowed.</div>";
  } else {
    $dest = __DIR__ . '/uploads/' . $name;
    if (move_uploaded_file($_FILES['doc']['tmp_name'], $dest)) {
      $url = '/uploads/' . $name;
      $notice = "<div class='notice ok'>Uploaded. Your document is available at <a href='" . e($url) . "'>" . e($url) . "</a></div>";
    } else {
      $notice = "<div class='notice err'>Upload failed.</div>";
    }
  }
}
page_head('Documents');
?>
<h1>Documents</h1>
<p class="sub">Upload proof of address or a cheque image to support your account.</p>

<div class="card" style="margin-top:14px">
  <?php echo $notice; ?>
  <form method="post" enctype="multipart/form-data">
    <div class="drop">
      <div style="font-size:30px">&#128228;</div>
      <p>Choose a document to upload</p>
      <input type="file" name="doc" style="max-width:320px;margin:0 auto">
    </div>
    <button class="btn" type="submit" style="margin-top:16px">Upload document</button>
  </form>
  <p class="sub" style="margin-top:14px">Uploaded files are stored under <code>/uploads/</code> and reviewed by our team.</p>
</div>
<?php page_foot();

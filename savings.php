<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $category = trim((string)($_POST['category'] ?? 'Savings'));
  $desc = trim((string)($_POST['description'] ?? ''));
  $amount = (float)($_POST['amount'] ?? 0);
  $tdate = trim((string)($_POST['tdate'] ?? today()));

  if ($category === '') $errors[] = "Category required.";
  if ($amount <= 0) $errors[] = "Amount must be > 0.";

  if (!$errors) {
    $stmt = $pdo->prepare("INSERT INTO transactions(user_id,type,category,description,amount,tdate,created_at)
                           VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$userId,'saving',$category,$desc,$amount,$tdate,date('Y-m-d H:i:s')]);
    $flash = "Savings recorded!";
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT * FROM transactions WHERE user_id=? AND type='saving'";
$params = [$userId];
if ($q !== '') {
  $sql .= " AND (category LIKE ? OR description LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
$sql .= " ORDER BY tdate DESC, id DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalSaving = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='saving'")->fetchColumn();

$pageTitle = "Savings";
include __DIR__ . '/includes/header.php';
?>
<h1>Savings</h1>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="grid two">
  <div class="panel">
    <h2>Add Savings</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="add">
      <div class="row"><label>Category</label><input name="category" placeholder="Emergency Fund / Phone / Tuition" required></div>
      <div class="row"><label>Description</label><input name="description" placeholder="optional"></div>
      <div class="row"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div class="row"><label>Date</label><input name="tdate" type="date" value="<?=h(today())?>" required></div>
      <button class="btn" type="submit">Add Savings</button>
    </form>
  </div>

  <div class="panel">
    <h2>Total Savings</h2>
    <div class="card-value"><?=money($totalSaving)?></div>
    <form method="get" class="form" style="margin-top:12px;">
      <div class="row"><label>Search Savings</label><input name="q" value="<?=h($q)?>" placeholder="e.g., tuition"></div>
      <button class="btn" type="submit">Search</button>
    </form>
  </div>
</section>

<div class="panel">
  <h2>Savings List</h2>
  <div class="table">
    <div class="thead"><div>Date</div><div>Type</div><div>Details</div><div class="right">Amount</div><div></div></div>
    <?php foreach($list as $t): ?>
      <div class="trow">
        <div><?=h($t['tdate'])?></div>
        <div><span class="pill saving">SAVING</span></div>
        <div>
          <strong><?=h($t['category'])?></strong>
          <?php if (!empty($t['description'])): ?><div class="muted" style="font-size:12px;"><?=h($t['description'])?></div><?php endif; ?>
        </div>
        <div class="right">-<?=money((float)$t['amount'])?></div>
        <div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

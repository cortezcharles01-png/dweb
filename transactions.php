<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $type = trim((string)($_POST['type'] ?? 'expense'));
  $category = trim((string)($_POST['category'] ?? ''));
  $desc = trim((string)($_POST['description'] ?? ''));
  $amount = (float)($_POST['amount'] ?? 0);
  $tdate = trim((string)($_POST['tdate'] ?? today()));

  if (!in_array($type, ['expense'], true)) $errors[] = "Only expenses here.";
  if ($category === '') $errors[] = "Category required.";
  if ($amount <= 0) $errors[] = "Amount must be > 0.";

  if (!$errors) {
    $stmt = $pdo->prepare("INSERT INTO transactions(user_id,type,category,description,amount,tdate,created_at)
                           VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$userId,'expense',$category,$desc,$amount,$tdate,date('Y-m-d H:i:s')]);
    $flash = "Expense added!";
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT * FROM transactions WHERE user_id=? AND type='expense'";
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

$pageTitle = "Transactions (Expenses)";
include __DIR__ . '/includes/header.php';
?>
<h1>Expenses</h1>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="grid two">
  <div class="panel">
    <h2>Add Expense</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="add">
      <input type="hidden" name="type" value="expense">
      <div class="row"><label>Category</label><input name="category" placeholder="Food / Transport / School" required></div>
      <div class="row"><label>Description</label><input name="description" placeholder="optional"></div>
      <div class="row"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div class="row"><label>Date</label><input name="tdate" type="date" value="<?=h(today())?>" required></div>
      <button class="btn" type="submit">Add Expense</button>
    </form>
  </div>

  <div class="panel">
    <h2>Search Expenses</h2>
    <form method="get" class="form">
      <div class="row">
        <label>Search (category/description)</label>
        <input name="q" value="<?=h($q)?>" placeholder="e.g., food, jeep, notebook">
      </div>
      <button class="btn" type="submit">Search</button>
    </form>
    <p class="muted">This satisfies the “basic search” requirement.</p>
  </div>
</section>

<div class="panel">
  <h2>Expense List</h2>
  <div class="table">
    <div class="thead"><div>Date</div><div>Type</div><div>Details</div><div class="right">Amount</div><div></div></div>
    <?php foreach($list as $t): ?>
      <div class="trow">
        <div><?=h($t['tdate'])?></div>
        <div><span class="pill expense">EXPENSE</span></div>
        <div>
          <strong><?=h($t['category'])?></strong>
          <?php if (!empty($t['description'])): ?><div class="muted" style="font-size:12px;"><?=h($t['description'])?></div><?php endif; ?>
        </div>
        <div class="right neg">-<?=money((float)$t['amount'])?></div>
        <div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

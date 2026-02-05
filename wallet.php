<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $category = trim((string)($_POST['category'] ?? 'Allowance'));
  $desc = trim((string)($_POST['description'] ?? ''));
  $amount = (float)($_POST['amount'] ?? 0);
  $tdate = trim((string)($_POST['tdate'] ?? today()));

  if ($amount <= 0) $errors[] = "Amount must be > 0.";

  if (!$errors) {
    $stmt = $pdo->prepare("INSERT INTO transactions(user_id,type,category,description,amount,tdate,created_at)
                           VALUES(?,?,?,?,?,?,?)");
    $stmt->execute([$userId,'income',$category,$desc,$amount,$tdate,date('Y-m-d H:i:s')]);
    $flash = "Income added to wallet!";
  }
}

$sumIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='income'")->fetchColumn();

$list = $pdo->query("SELECT * FROM transactions WHERE user_id=$userId AND type='income' ORDER BY tdate DESC, id DESC LIMIT 25")
            ->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Wallet";
include __DIR__ . '/includes/header.php';
?>
<h1>Wallet</h1>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="grid two">
  <div class="panel">
    <h2>Add Income</h2>
    <form method="post" class="form">
      <div class="row"><label>Category</label><input name="category" value="Allowance" required></div>
      <div class="row"><label>Description</label><input name="description" placeholder="optional"></div>
      <div class="row"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div class="row"><label>Date</label><input name="tdate" type="date" value="<?=h(today())?>" required></div>
      <button class="btn" type="submit">Add</button>
    </form>
  </div>

  <div class="panel">
    <h2>Total Wallet Income</h2>
    <div class="card-value"><?=money($sumIncome)?></div>
    <p class="muted">This is total income recorded (balance is shown in Dashboard).</p>
  </div>
</section>

<div class="panel">
  <h2>Recent Wallet Income</h2>
  <div class="table">
    <div class="thead"><div>Date</div><div>Type</div><div>Category</div><div class="right">Amount</div><div></div></div>
    <?php foreach($list as $t): ?>
      <div class="trow">
        <div><?=h($t['tdate'])?></div>
        <div><span class="pill income">INCOME</span></div>
        <div><?=h($t['category'])?></div>
        <div class="right">+<?=money((float)$t['amount'])?></div>
        <div></div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

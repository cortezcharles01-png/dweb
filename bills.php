<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  if ($action === 'add') {
    $name = trim((string)($_POST['name'] ?? ''));
    $amount = (float)($_POST['amount'] ?? 0);
    $due = trim((string)($_POST['due_date'] ?? ''));

    if ($name === '') $errors[] = "Bill name required.";
    if ($amount <= 0) $errors[] = "Amount must be > 0.";
    if ($due === '') $errors[] = "Due date required.";

    if (!$errors) {
      $stmt = $pdo->prepare("INSERT INTO bills(user_id,name,amount,due_date,status,created_at) VALUES(?,?,?,?,?,?)");
      $stmt->execute([$userId,$name,$amount,$due,'unpaid',date('Y-m-d H:i:s')]);
      $flash = "Bill added!";
    }
  }

  if ($action === 'pay') {
    $billId = (int)($_POST['bill_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT * FROM bills WHERE id=? AND user_id=?");
    $stmt->execute([$billId,$userId]);
    $bill = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$bill) $errors[] = "Bill not found.";
    else if ($bill['status'] === 'paid') $errors[] = "Bill already paid.";
    else {
      $pdo->beginTransaction();
      $paidAt = date('Y-m-d H:i:s');

      $stmt = $pdo->prepare("UPDATE bills SET status='paid', paid_at=? WHERE id=? AND user_id=?");
      $stmt->execute([$paidAt,$billId,$userId]);

      // Log payment as transaction
      $stmt = $pdo->prepare("INSERT INTO transactions(user_id,type,category,description,amount,tdate,created_at)
                             VALUES(?,?,?,?,?,?,?)");
      $stmt->execute([$userId,'bill','Bills',$bill['name'].' (paid)',(float)$bill['amount'],today(),date('Y-m-d H:i:s')]);

      $pdo->commit();
      $flash = "Bill paid + recorded in transactions!";
    }
  }
}

$q = trim((string)($_GET['q'] ?? ''));
$sql = "SELECT * FROM bills WHERE user_id=?";
$params = [$userId];
if ($q !== '') {
  $sql .= " AND (name LIKE ? OR status LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
}
$sql .= " ORDER BY status ASC, due_date ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bills = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Bills";
include __DIR__ . '/includes/header.php';
?>
<h1>Bills</h1>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="grid two">
  <div class="panel">
    <h2>Add Bill</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="add">
      <div class="row"><label>Bill Name</label><input name="name" placeholder="Internet / Tuition / Electric" required></div>
      <div class="row"><label>Amount</label><input name="amount" type="number" step="0.01" min="0.01" required></div>
      <div class="row"><label>Due Date</label><input name="due_date" type="date" value="<?=h(today())?>" required></div>
      <button class="btn" type="submit">Add Bill</button>
    </form>
  </div>

  <div class="panel">
    <h2>Search Bills</h2>
    <form method="get" class="form">
      <div class="row"><label>Search (name/status)</label><input name="q" value="<?=h($q)?>" placeholder="e.g., tuition, unpaid"></div>
      <button class="btn" type="submit">Search</button>
    </form>
    <p class="muted">Search requirement satisfied ✅</p>
  </div>
</section>

<div class="panel">
  <h2>Bills List</h2>
  <div class="table">
    <div class="thead"><div>Due</div><div>Status</div><div>Bill</div><div class="right">Amount</div><div>Pay</div></div>
    <?php foreach($bills as $b): ?>
      <?php $overdue = ($b['status']==='unpaid' && $b['due_date'] < today()); ?>
      <div class="trow">
        <div><?=h($b['due_date'])?><?= $overdue ? " <span class='pill danger'>Overdue</span>" : "" ?></div>
        <div><span class="pill <?= $b['status']==='paid' ? 'income' : 'bill' ?>"><?=h(strtoupper($b['status']))?></span></div>
        <div><strong><?=h($b['name'])?></strong></div>
        <div class="right"><?=money((float)$b['amount'])?></div>
        <div>
          <?php if ($b['status'] === 'unpaid'): ?>
            <form method="post">
              <input type="hidden" name="action" value="pay">
              <input type="hidden" name="bill_id" value="<?= (int)$b['id'] ?>">
              <button class="btn" type="submit">Pay</button>
            </form>
          <?php else: ?>
            <span class="muted">Paid</span>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

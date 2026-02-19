<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];

$sumIncome = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='income'")->fetchColumn();
$sumExpense = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='expense'")->fetchColumn();
$sumSaving = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='saving'")->fetchColumn();
$sumBillsPaid = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='bill'")->fetchColumn();
$walletBalance = $sumIncome - $sumExpense - $sumSaving - $sumBillsPaid;

$unpaidCount = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE user_id=$userId AND status='unpaid'")->fetchColumn();
$overdueCount = (int)$pdo->query("SELECT COUNT(*) FROM bills WHERE user_id=$userId AND status='unpaid' AND due_date < CURDATE()")->fetchColumn();

$pageTitle = "Dashboard";
include __DIR__ . '/includes/header.php';
?>
<h1>Welcome, <?=h($user['name'])?></h1>

<section class="grid cards">
  <div class="card">
    <div class="card-label">Wallet Balance</div>
    <div class="card-value <?= $walletBalance < 0 ? 'neg' : '' ?>"><?=money($walletBalance)?></div>
  </div>
  <div class="card">
    <div class="card-label">Total Income</div>
    <div class="card-value"><?=money($sumIncome)?></div>
  </div>
  <div class="card">
    <div class="card-label">Total Expenses</div>
    <div class="card-value"><?=money($sumExpense)?></div>
  </div>
  <div class="card">
    <div class="card-label">Total Savings</div>
    <div class="card-value"><?=money($sumSaving)?></div>
  </div>
</section>

<section class="grid two">
  <div class="panel">
    <h2>Bills Status</h2>
    <p><strong><?= $unpaidCount ?></strong> unpaid bills</p>
    <p><?= $overdueCount > 0 ? "<span style='color:var(--warn)'><strong>$overdueCount</strong> overdue</span>" : "No overdue bills" ?></p>
    <a class="btn" href="bills.php" style="display:inline-block;text-decoration:none;">Manage Bills</a>
  </div>

  <div class="panel">
    <h2>Quick Links</h2>
    <div class="grid" style="grid-template-columns:1fr; gap:10px;">
      <a class="btn" href="wallet.php" style="text-decoration:none;">Wallet (Add Income)</a>
      <a class="btn" href="transactions.php" style="text-decoration:none;">Expenses / Transactions</a>
      <a class="btn" href="savings.php" style="text-decoration:none;">Savings</a>
    </div>
  </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>


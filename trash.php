<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash  = null;

// ── POST actions ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $id     = (int)($_POST['id'] ?? 0);
  $tbl    = ($_POST['table'] ?? '') === 'bills' ? 'bills' : 'transactions';

  // Soft delete — set deleted_at timestamp
  if ($action === 'delete' && $id > 0) {
    $pdo->prepare("UPDATE $tbl SET deleted_at = NOW() WHERE id = ? AND user_id = ?")
        ->execute([$id, $userId]);
    $flash = ucfirst($tbl === 'bills' ? 'Bill' : 'Transaction') . " moved to trash.";
  }

  // Restore — clear deleted_at
  if ($action === 'restore' && $id > 0) {
    $pdo->prepare("UPDATE $tbl SET deleted_at = NULL WHERE id = ? AND user_id = ?")
        ->execute([$id, $userId]);
    $flash = "Record restored successfully!";
  }

  // Permanent delete
  if ($action === 'purge' && $id > 0) {
    $pdo->prepare("DELETE FROM $tbl WHERE id = ? AND user_id = ?")
        ->execute([$id, $userId]);
    $flash = "Record permanently deleted.";
  }
}

// ── Fetch trashed records ─────────────────────────────────────────────────────
$trashedTx = $pdo->prepare(
  "SELECT *, 'transactions' AS src FROM transactions
   WHERE user_id = ? AND deleted_at IS NOT NULL
   ORDER BY deleted_at DESC"
);
$trashedTx->execute([$userId]);
$trashedTransactions = $trashedTx->fetchAll(PDO::FETCH_ASSOC);

$trashedBills = $pdo->prepare(
  "SELECT *, 'bills' AS src FROM bills
   WHERE user_id = ? AND deleted_at IS NOT NULL
   ORDER BY deleted_at DESC"
);
$trashedBills->execute([$userId]);
$trashedBillsList = $trashedBills->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Trash";
include __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
  <h1 style="margin:0;">🗑️ Trash</h1>
  <p class="muted" style="margin:0;font-size:13px;">Deleted records are kept here. You can restore or permanently delete them.</p>
</div>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<!-- ── Trashed Transactions ── -->
<div class="panel" style="margin-bottom:16px;">
  <h2>Trashed Transactions (<?=count($trashedTransactions)?>)</h2>
  <?php if (empty($trashedTransactions)): ?>
    <p class="muted">No trashed transactions.</p>
  <?php else: ?>
  <div class="table">
    <div class="thead" style="grid-template-columns:100px 80px 120px 1fr 110px 160px;">
      <div>Date</div><div>Type</div><div>Category</div><div>Description</div><div class="right">Amount</div><div>Actions</div>
    </div>
    <?php foreach ($trashedTransactions as $t): ?>
    <div class="trow" style="grid-template-columns:100px 80px 120px 1fr 110px 160px;opacity:.75;">
      <div><?=h($t['tdate'])?></div>
      <div><span class="pill <?=h($t['type'])?>"><?=strtoupper($t['type'])?></span></div>
      <div><?=h($t['category'])?></div>
      <div class="muted" style="font-size:12px;"><?=h((string)$t['description'])?></div>
      <div class="right"><?=money((float)$t['amount'])?></div>
      <div style="display:flex;gap:6px;">
        <!-- Restore -->
        <form method="post" style="margin:0;">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="id" value="<?=(int)$t['id']?>">
          <input type="hidden" name="table" value="transactions">
          <button class="btn" type="submit" style="font-size:11px;padding:6px 10px;background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.3);">Restore</button>
        </form>
        <!-- Purge -->
        <form method="post" style="margin:0;" onsubmit="return confirm('Permanently delete this record?');">
          <input type="hidden" name="action" value="purge">
          <input type="hidden" name="id" value="<?=(int)$t['id']?>">
          <input type="hidden" name="table" value="transactions">
          <button class="btn" type="submit" style="font-size:11px;padding:6px 10px;background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.3);">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- ── Trashed Bills ── -->
<div class="panel">
  <h2>Trashed Bills (<?=count($trashedBillsList)?>)</h2>
  <?php if (empty($trashedBillsList)): ?>
    <p class="muted">No trashed bills.</p>
  <?php else: ?>
  <div class="table">
    <div class="thead" style="grid-template-columns:1fr 110px 100px 160px;">
      <div>Bill Name</div><div class="right">Amount</div><div>Due Date</div><div>Actions</div>
    </div>
    <?php foreach ($trashedBillsList as $b): ?>
    <div class="trow" style="grid-template-columns:1fr 110px 100px 160px;opacity:.75;">
      <div><strong><?=h($b['name'])?></strong></div>
      <div class="right"><?=money((float)$b['amount'])?></div>
      <div><?=h($b['due_date'])?></div>
      <div style="display:flex;gap:6px;">
        <form method="post" style="margin:0;">
          <input type="hidden" name="action" value="restore">
          <input type="hidden" name="id" value="<?=(int)$b['id']?>">
          <input type="hidden" name="table" value="bills">
          <button class="btn" type="submit" style="font-size:11px;padding:6px 10px;background:rgba(74,222,128,.12);border-color:rgba(74,222,128,.3);">Restore</button>
        </form>
        <form method="post" style="margin:0;" onsubmit="return confirm('Permanently delete this bill?');">
          <input type="hidden" name="action" value="purge">
          <input type="hidden" name="id" value="<?=(int)$b['id']?>">
          <input type="hidden" name="table" value="bills">
          <button class="btn" type="submit" style="font-size:11px;padding:6px 10px;background:rgba(251,113,133,.12);border-color:rgba(251,113,133,.3);">Delete</button>
        </form>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

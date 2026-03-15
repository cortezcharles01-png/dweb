<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];

// ── Filter ────────────────────────────────────────────────────────────────────
$filterAction = trim((string)($_GET['action_type'] ?? ''));
$filterFrom   = trim((string)($_GET['from'] ?? date('Y-m-01')));
$filterTo     = trim((string)($_GET['to']   ?? today()));

$sql    = "SELECT * FROM activity_logs WHERE user_id = ?
           AND DATE(created_at) >= ? AND DATE(created_at) <= ?";
$params = [$userId, $filterFrom, $filterTo];

if ($filterAction !== '') {
    $sql    .= " AND action_type = ?";
    $params[] = $filterAction;
}
$sql .= " ORDER BY created_at DESC LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Distinct action types for filter dropdown
$types = $pdo->prepare("SELECT DISTINCT action_type FROM activity_logs WHERE user_id = ? ORDER BY action_type");
$types->execute([$userId]);
$actionTypes = $types->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = "Activity Log";
include __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
  <h1 style="margin:0;">Activity Log</h1>
  <span class="muted" style="font-size:13px;">All actions you perform are recorded here automatically.</span>
</div>

<!-- Filters -->
<div class="panel" style="margin-bottom:16px;">
  <form method="get" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <div class="row"><label>From</label><input name="from" type="date" value="<?=h($filterFrom)?>"></div>
    <div class="row"><label>To</label><input name="to" type="date" value="<?=h($filterTo)?>"></div>
    <div class="row">
      <label>Action Type</label>
      <select name="action_type">
        <option value="">All</option>
        <?php foreach ($actionTypes as $t): ?>
          <option value="<?=h($t)?>" <?=$filterAction===$t?'selected':''?>><?=h(ucfirst($t))?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <button class="btn" type="submit">Filter</button>
    <a class="btn" href="activity_logs.php" style="text-decoration:none;background:rgba(255,255,255,.06);">Reset</a>
  </form>
</div>

<!-- Log Table -->
<div class="panel">
  <h2>Log Entries (<?=count($logs)?>)</h2>
  <?php if (empty($logs)): ?>
    <p class="muted">No activity found for this period.</p>
  <?php else: ?>
  <div class="table">
    <div class="thead" style="grid-template-columns:160px 110px 1fr;">
      <div>Timestamp</div>
      <div>Action</div>
      <div>Details</div>
    </div>
    <?php foreach ($logs as $log):
      $colors = [
        'income'    => '#4ade80',
        'expense'   => '#fb7185',
        'saving'    => '#6ee7ff',
        'bill'      => '#fbbf24',
        'challenge' => '#a78bfa',
        'login'     => '#94a3b8',
        'logout'    => '#94a3b8',
        'register'  => '#4ade80',
      ];
      $color = $colors[$log['action_type']] ?? '#e9ecf5';
    ?>
    <div class="trow" style="grid-template-columns:160px 110px 1fr;">
      <div class="muted" style="font-size:12px;"><?=h($log['created_at'])?></div>
      <div>
        <span class="pill" style="border-color:<?=$color?>33;color:<?=$color?>;font-size:11px;">
          <?=h(strtoupper($log['action_type']))?>
        </span>
      </div>
      <div style="font-size:13px;"><?=h($log['description'])?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

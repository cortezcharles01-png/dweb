<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];

// ── Build dynamic query from filters ─────────────────────────────────────────
$filterType    = $_GET['type']     ?? 'all';
$filterCat     = trim((string)($_GET['category']   ?? ''));
$filterFrom    = trim((string)($_GET['from']       ?? date('Y-m-01')));
$filterTo      = trim((string)($_GET['to']         ?? today()));
$filterAmtMin  = trim((string)($_GET['amt_min']    ?? ''));
$filterAmtMax  = trim((string)($_GET['amt_max']    ?? ''));
$sortCol       = $_GET['sort']     ?? 'tdate';
$sortDir       = strtoupper($_GET['dir'] ?? 'DESC') === 'ASC' ? 'ASC' : 'DESC';

// Whitelist sort columns to prevent SQL injection
$allowedSort = ['tdate', 'amount', 'category', 'type'];
if (!in_array($sortCol, $allowedSort, true)) $sortCol = 'tdate';

$sql    = "SELECT * FROM transactions WHERE user_id = ? AND tdate >= ? AND tdate <= ?";
$params = [$userId, $filterFrom, $filterTo];

if ($filterType !== 'all') {
  $sql .= " AND type = ?";
  $params[] = $filterType;
}
if ($filterCat !== '') {
  $sql .= " AND category LIKE ?";
  $params[] = "%$filterCat%";
}
if ($filterAmtMin !== '') {
  $sql .= " AND amount >= ?";
  $params[] = (float)$filterAmtMin;
}
if ($filterAmtMax !== '') {
  $sql .= " AND amount <= ?";
  $params[] = (float)$filterAmtMax;
}

// Safe to interpolate because $sortCol and $sortDir are whitelisted above
$sql .= " ORDER BY $sortCol $sortDir LIMIT 100";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Summary totals for filtered results
$totals = ['income' => 0, 'expense' => 0, 'saving' => 0, 'bill' => 0];
foreach ($results as $r) {
  $totals[$r['type']] = ($totals[$r['type']] ?? 0) + (float)$r['amount'];
}

// Helper: sort link toggle
function sortLink(string $col, string $current, string $dir): string {
  $newDir = ($current === $col && $dir === 'DESC') ? 'ASC' : 'DESC';
  $arrow  = $current === $col ? ($dir === 'ASC' ? ' ↑' : ' ↓') : '';
  $q = http_build_query(array_merge($_GET, ['sort' => $col, 'dir' => $newDir]));
  return "<a href='advanced_filter.php?$q' style='color:inherit;text-decoration:none;'>$col$arrow</a>";
}

$pageTitle = "Advanced Filter";
include __DIR__ . '/includes/header.php';
?>

<h1>Advanced Filter &amp; Sort</h1>
<p class="muted">Filter transactions by type, category, date range, and amount. Click column headers to sort.</p>

<!-- Filter Form -->
<div class="panel" style="margin-bottom:16px;">
  <form method="get" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px;align-items:flex-end;">
    <div class="row">
      <label>Type</label>
      <select name="type">
        <?php foreach (['all','income','expense','saving','bill'] as $t): ?>
          <option value="<?=$t?>" <?=$filterType===$t?'selected':''?>><?=ucfirst($t)?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="row"><label>Category</label><input name="category" value="<?=h($filterCat)?>" placeholder="e.g. Food"></div>
    <div class="row"><label>From</label><input name="from" type="date" value="<?=h($filterFrom)?>"></div>
    <div class="row"><label>To</label><input name="to" type="date" value="<?=h($filterTo)?>"></div>
    <div class="row"><label>Min Amount</label><input name="amt_min" type="number" step="0.01" value="<?=h($filterAmtMin)?>" placeholder="0"></div>
    <div class="row"><label>Max Amount</label><input name="amt_max" type="number" step="0.01" value="<?=h($filterAmtMax)?>" placeholder="any"></div>
    <div style="display:flex;gap:8px;align-items:flex-end;">
      <button class="btn" type="submit">Filter</button>
      <a class="btn" href="advanced_filter.php" style="text-decoration:none;background:rgba(255,255,255,.06);">Reset</a>
    </div>
  </form>
</div>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px;">
  <?php
  $cardInfo = [
    'income'  => ['Total Income',   '#4ade80'],
    'expense' => ['Total Expenses', '#fb7185'],
    'saving'  => ['Total Savings',  '#6ee7ff'],
    'bill'    => ['Bills Paid',     '#fbbf24'],
  ];
  foreach ($cardInfo as $type => [$lbl, $color]): ?>
    <div class="card">
      <div class="card-label"><?=$lbl?></div>
      <div class="card-value" style="font-size:20px;color:<?=$color?>;"><?=money($totals[$type])?></div>
    </div>
  <?php endforeach; ?>
</div>

<!-- Results Table -->
<div class="panel">
  <h2>Results (<?=count($results)?> records)</h2>
  <?php if (empty($results)): ?>
    <p class="muted">No records match the selected filters.</p>
  <?php else: ?>
  <div class="table">
    <div class="thead" style="grid-template-columns:120px 90px 130px 1fr 120px;">
      <div><?= sortLink('tdate',    $sortCol, $sortDir) ?></div>
      <div>Type</div>
      <div><?= sortLink('category', $sortCol, $sortDir) ?></div>
      <div>Description</div>
      <div class="right"><?= sortLink('amount', $sortCol, $sortDir) ?></div>
    </div>
    <?php foreach ($results as $r): ?>
    <div class="trow" style="grid-template-columns:120px 90px 130px 1fr 120px;">
      <div><?=h($r['tdate'])?></div>
      <div><span class="pill <?=h($r['type'])?>"><?=strtoupper($r['type'])?></span></div>
      <div><?=h($r['category'])?></div>
      <div class="muted" style="font-size:12px;"><?=h((string)$r['description'])?></div>
      <div class="right"><?=money((float)$r['amount'])?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

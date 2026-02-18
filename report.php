<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];

/* ── Filters ─────────────────────────────────────────────── */
$dateFrom = trim((string)($_GET['from'] ?? date('Y-m-01')));
$dateTo   = trim((string)($_GET['to']   ?? today()));
$typeFilter = $_GET['type'] ?? 'all';
$printMode  = isset($_GET['print']);

/* ── Query ───────────────────────────────────────────────── */
$typeWhere = '';
$params = [$userId, $dateFrom, $dateTo];

if ($typeFilter === 'expense') {
  $typeWhere = " AND type='expense'";
} elseif ($typeFilter === 'income') {
  $typeWhere = " AND type='income'";
} elseif ($typeFilter === 'saving') {
  $typeWhere = " AND type='saving'";
} elseif ($typeFilter === 'bill') {
  $typeWhere = " AND type='bill'";
}

$stmt = $pdo->prepare(
  "SELECT * FROM transactions
   WHERE user_id=? AND tdate>=? AND tdate<=?
   $typeWhere
   ORDER BY tdate ASC, id ASC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ── Summaries ────────────────────────────────────────────── */
$totals = ['income'=>0,'expense'=>0,'saving'=>0,'bill'=>0];
$byCategory = [];
$byDate = [];

foreach ($rows as $r) {
  $totals[$r['type']] = ($totals[$r['type']] ?? 0) + (float)$r['amount'];
  $byCategory[$r['type']][$r['category']] = ($byCategory[$r['type']][$r['category']] ?? 0) + (float)$r['amount'];
  $byDate[$r['tdate']][$r['type']] = ($byDate[$r['tdate']][$r['type']] ?? 0) + (float)$r['amount'];
}

$netFlow = $totals['income'] - $totals['expense'] - $totals['saving'] - $totals['bill'];

$pageTitle = "Expense Report";
if (!$printMode) include __DIR__ . '/includes/header.php';
?>

<?php if ($printMode): ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <title>Expense Report — <?=h($user['name'])?></title>
  <style>
    *{ box-sizing:border-box; }
    body{ font-family: Arial, sans-serif; padding:30px; color:#111; font-size:13px; }
    h1{ font-size:22px; margin:0 0 4px; }
    h2{ font-size:14px; margin:18px 0 8px; color:#333; border-bottom:1px solid #ddd; padding-bottom:4px; }
    .meta{ color:#555; font-size:12px; margin-bottom:18px; }
    .cards{ display:grid; grid-template-columns:repeat(4,1fr); gap:12px; margin-bottom:18px; }
    .card{ border:1px solid #ddd; border-radius:8px; padding:12px; }
    .card-label{ font-size:11px; color:#666; }
    .card-value{ font-size:20px; font-weight:900; margin-top:4px; }
    .neg{ color:#dc2626; }
    table{ width:100%; border-collapse:collapse; margin-bottom:18px; }
    th{ background:#f5f5f5; padding:8px; text-align:left; font-size:11px; }
    td{ padding:7px 8px; border-bottom:1px solid #eee; }
    .right{ text-align:right; }
    .pill{ font-size:10px; padding:2px 6px; border-radius:999px; border:1px solid #ccc; }
    .pill.income{ border-color:#16a34a; color:#16a34a; }
    .pill.expense{ border-color:#dc2626; color:#dc2626; }
    .pill.saving{ border-color:#2563eb; color:#2563eb; }
    .pill.bill{ border-color:#d97706; color:#d97706; }
    .footer{ margin-top:24px; font-size:11px; color:#777; text-align:center; }
    @media print{
      button{ display:none; }
      body{ padding:15px; }
    }
  </style>
</head>
<body>
<button onclick="window.print()" style="padding:8px 16px;margin-bottom:16px;cursor:pointer;">Print / Save PDF</button>
<h1>Expense Report — <?=h($user['name'])?></h1>
<div class="meta">Period: <?=h($dateFrom)?> to <?=h($dateTo)?> &nbsp;|&nbsp; Generated: <?=date('Y-m-d H:i')?></div>

<?php else: ?>

<style>
.report-filters{ display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end; }
.report-filters .row{ min-width:140px; }
.cat-bar-wrap{ height:8px;border-radius:999px;background:rgba(255,255,255,.08);overflow:hidden;display:inline-block;width:100px;vertical-align:middle; }
.cat-bar-fill{ height:100%;border-radius:999px;background:linear-gradient(90deg,#6ee7ff,#4ade80); }
</style>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:10px;">
  <h1 style="margin:0;">Expense Report</h1>
  <a href="report.php?from=<?=h($dateFrom)?>&to=<?=h($dateTo)?>&type=<?=h($typeFilter)?>&print=1"
     target="_blank"
     class="btn" style="text-decoration:none;font-size:12px;">Print / Export PDF</a>
</div>

<div class="panel" style="margin-bottom:16px;">
  <form method="get" class="report-filters">
    <div class="row"><label>From</label><input name="from" type="date" value="<?=h($dateFrom)?>"></div>
    <div class="row"><label>To</label><input name="to" type="date" value="<?=h($dateTo)?>"></div>
    <div class="row">
      <label>Type</label>
      <select name="type">
        <option value="all"     <?=$typeFilter==='all'     ?'selected':''?>>All</option>
        <option value="expense" <?=$typeFilter==='expense' ?'selected':''?>>Expenses</option>
        <option value="income"  <?=$typeFilter==='income'  ?'selected':''?>>Income</option>
        <option value="saving"  <?=$typeFilter==='saving'  ?'selected':''?>>Savings</option>
        <option value="bill"    <?=$typeFilter==='bill'    ?'selected':''?>>Bills</option>
      </select>
    </div>
    <button class="btn" type="submit">Generate</button>
  </form>
</div>

<?php endif; // end non-print header ?>

<!-- Summary Cards -->
<div style="display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:16px;" class="cards">
  <div class="card">
    <div class="card-label">Total Income</div>
    <div class="card-value" style="color:#4ade80;"><?=money($totals['income'])?></div>
  </div>
  <div class="card">
    <div class="card-label">Total Expenses</div>
    <div class="card-value" style="color:#fb7185;"><?=money($totals['expense'])?></div>
  </div>
  <div class="card">
    <div class="card-label">Total Savings</div>
    <div class="card-value" style="color:#6ee7ff;"><?=money($totals['saving'])?></div>
  </div>
  <div class="card">
    <div class="card-label">Bills Paid</div>
    <div class="card-value" style="color:#fbbf24;"><?=money($totals['bill'])?></div>
  </div>
</div>

<?php if (!$printMode): ?>
<div class="panel" style="margin-bottom:16px;">
  <strong>Net Flow (Income – Expenses – Savings – Bills): </strong>
  <span style="font-size:20px;font-weight:900;color:<?=$netFlow>=0?'var(--good)':'var(--bad)'?>;">
    <?= ($netFlow >= 0 ? '+' : '') . money($netFlow) ?>
  </span>
</div>
<?php else: ?>
<div class="card" style="margin-bottom:16px;">
  <strong>Net Flow:</strong>
  <span class="card-value <?=$netFlow<0?'neg':''?>"><?= ($netFlow>=0?'+':'') . money($netFlow) ?></span>
</div>
<?php endif; ?>

<!-- Category Breakdown -->
<?php if (!empty($byCategory)): ?>
<?php if (!$printMode): ?><div class="panel" style="margin-bottom:16px;"><?php else: ?><div><?php endif; ?>
  <h2>Category Breakdown</h2>
  <table>
    <thead>
      <tr>
        <th>Type</th>
        <th>Category</th>
        <th class="right">Amount</th>
        <?php if (!$printMode): ?><th>Bar</th><?php endif; ?>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($byCategory as $type => $cats):
      arsort($cats);
      $typeTotal = array_sum($cats);
      foreach ($cats as $cat => $amt):
        $pct = $typeTotal > 0 ? round($amt/$typeTotal*100) : 0;
    ?>
      <tr>
        <td><span class="pill <?=h($type)?>"><?=strtoupper($type)?></span></td>
        <td><?=h($cat)?></td>
        <td class="right"><?=money($amt)?></td>
        <?php if (!$printMode): ?>
        <td>
          <div class="cat-bar-wrap">
            <div class="cat-bar-fill" style="width:<?=$pct?>%"></div>
          </div>
          <span style="font-size:11px;color:var(--muted);margin-left:5px;"><?=$pct?>%</span>
        </td>
        <?php endif; ?>
      </tr>
    <?php endforeach; endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<!-- Transaction Table -->
<?php if (!$printMode): ?><div class="panel"><?php else: ?><div><?php endif; ?>
  <h2>All Transactions (<?=count($rows)?>)</h2>
  <?php if (empty($rows)): ?>
    <p class="muted">No transactions found for this period.</p>
  <?php else: ?>
  <table>
    <thead>
      <tr>
        <th>Date</th>
        <th>Type</th>
        <th>Category</th>
        <th>Description</th>
        <th class="right">Amount</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?=h($r['tdate'])?></td>
        <td><span class="pill <?=h($r['type'])?>"><?=strtoupper($r['type'])?></span></td>
        <td><?=h($r['category'])?></td>
        <td class="muted" style="font-size:12px;"><?=h((string)$r['description'])?></td>
        <td class="right"><?=money((float)$r['amount'])?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="4">Total Records: <?=count($rows)?></th>
        <th class="right"><?=money(array_sum(array_column($rows,'amount')))?></th>
      </tr>
    </tfoot>
  </table>
  <?php endif; ?>
</div>

<?php if ($printMode): ?>
<div class="footer">Student Savings Website &bull; Generated <?=date('Y-m-d H:i')?></div>
</body>
</html>
<?php else:
  include __DIR__ . '/includes/footer.php';
endif; ?>

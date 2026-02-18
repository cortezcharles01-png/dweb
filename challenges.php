<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];
$errors = [];
$flash  = null;

/* ── Helpers ─────────────────────────────────────────────── */
function challengeProgress(PDO $pdo, int $userId, string $createdAt, string $deadline): float {
  $stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount),0) FROM transactions
     WHERE user_id=? AND type='saving' AND tdate >= ? AND tdate <= ?"
  );
  $stmt->execute([$userId, substr($createdAt, 0, 10), $deadline]);
  return (float)$stmt->fetchColumn();
}

function harderTarget(float $target, int $difficulty): float {
  // Each failure multiplies the target by 1.5x per difficulty level
  return round($target * (1.5 ** $difficulty), 2);
}

function harderDeadline(string $deadline, int $difficulty): string {
  // Deadline shrinks by 20% each failure (in days from now)
  $days = max(3, (int)(14 / (1 + $difficulty * 0.3)));
  return date('Y-m-d', strtotime("+{$days} days"));
}

/* ── Auto-evaluate active challenges ─────────────────────── */
$activeStmt = $pdo->prepare("SELECT * FROM challenges WHERE user_id=? AND status='active'");
$activeStmt->execute([$userId]);
$activeList = $activeStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($activeList as $ch) {
  $saved    = challengeProgress($pdo, $userId, $ch['created_at'], $ch['deadline']);
  $isPassed = $ch['deadline'] < today();

  if ($saved >= (float)$ch['target_amount']) {
    // Completed!
    $pdo->prepare("UPDATE challenges SET status='completed' WHERE id=?")->execute([$ch['id']]);
  } elseif ($isPassed) {
    // Failed — auto-create harder retry
    $pdo->prepare("UPDATE challenges SET status='failed' WHERE id=?")->execute([$ch['id']]);
    $newTarget   = harderTarget((float)$ch['target_amount'], (int)$ch['difficulty']);
    $newDeadline = harderDeadline($ch['deadline'], (int)$ch['difficulty']);
    $newDiff     = (int)$ch['difficulty'] + 1;
    $pdo->prepare(
      "INSERT INTO challenges(user_id,title,description,target_amount,reward,penalty,deadline,status,difficulty,retry_of,created_at)
       VALUES(?,?,?,?,?,?,?,?,?,?,?)"
    )->execute([
      $userId,
      $ch['title'] . ' (Retry ' . $newDiff . ')',
      $ch['description'],
      $newTarget,
      $ch['reward'],
      $ch['penalty'],
      $newDeadline,
      'active',
      $newDiff,
      $ch['id'],
      date('Y-m-d H:i:s')
    ]);
  }
}

/* ── POST: Add Challenge ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
  $title    = trim((string)($_POST['title'] ?? ''));
  $desc     = trim((string)($_POST['description'] ?? ''));
  $target   = (float)($_POST['target_amount'] ?? 0);
  $reward   = trim((string)($_POST['reward'] ?? ''));
  $penalty  = trim((string)($_POST['penalty'] ?? ''));
  $deadline = trim((string)($_POST['deadline'] ?? ''));

  if ($title   === '') $errors[] = 'Challenge title required.';
  if ($target  <= 0)   $errors[] = 'Target amount must be > 0.';
  if ($reward  === '') $errors[] = 'Reward is required.';
  if ($penalty === '') $errors[] = 'Penalty/consequence is required.';
  if ($deadline === '' || $deadline <= today()) $errors[] = 'Deadline must be a future date.';

  if (!$errors) {
    $pdo->prepare(
      "INSERT INTO challenges(user_id,title,description,target_amount,reward,penalty,deadline,status,difficulty,created_at)
       VALUES(?,?,?,?,?,?,?,?,?,?)"
    )->execute([$userId, $title, $desc, $target, $reward, $penalty, $deadline, 'active', 1, date('Y-m-d H:i:s')]);
    $flash = "Challenge created! Go crush it! 💪";
    header("Location: challenges.php");
    exit;
  }
}

/* ── Fetch all challenges ─────────────────────────────────── */
$stmt = $pdo->prepare("SELECT * FROM challenges WHERE user_id=? ORDER BY status ASC, created_at DESC");
$stmt->execute([$userId]);
$challenges = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Challenges";
include __DIR__ . '/includes/header.php';
?>

<style>
.challenge-card{
  background: linear-gradient(180deg,rgba(255,255,255,.07),rgba(255,255,255,.03));
  border:1px solid var(--border);
  border-radius:18px;
  padding:18px;
  display:flex;
  flex-direction:column;
  gap:10px;
}
.challenge-card.completed{ border-color:rgba(74,222,128,.45); background:linear-gradient(160deg,rgba(74,222,128,.08),rgba(255,255,255,.03)); }
.challenge-card.failed   { border-color:rgba(251,113,133,.35); background:linear-gradient(160deg,rgba(251,113,133,.06),rgba(255,255,255,.03)); }
.challenge-card.active   { border-color:rgba(110,231,255,.35); }

.progress-bar-wrap{
  height:10px; border-radius:999px;
  background:rgba(255,255,255,.08);
  overflow:hidden;
}
.progress-bar-fill{
  height:100%; border-radius:999px;
  background: linear-gradient(90deg,#6ee7ff,#4ade80);
  transition: width .5s ease;
}
.badge{
  display:inline-flex;align-items:center;gap:5px;
  font-size:11px;font-weight:700;
  padding:4px 10px;border-radius:999px;
}
.badge.active   { background:rgba(110,231,255,.15);border:1px solid rgba(110,231,255,.4);color:#6ee7ff; }
.badge.completed{ background:rgba(74,222,128,.15);border:1px solid rgba(74,222,128,.4);color:#4ade80; }
.badge.failed   { background:rgba(251,113,133,.12);border:1px solid rgba(251,113,133,.4);color:#fb7185; }
.badge.retry    { background:rgba(251,191,36,.12);border:1px solid rgba(251,191,36,.4);color:#fbbf24; }

.reward-box{
  font-size:13px;padding:8px 12px;border-radius:10px;
  background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.25);
}
.penalty-box{
  font-size:13px;padding:8px 12px;border-radius:10px;
  background:rgba(251,113,133,.08);border:1px solid rgba(251,113,133,.25);
}
.diff-stars{ color:#fbbf24; }
.celebration{
  text-align:center;font-size:28px;
  animation: pop .5s ease;
}
@keyframes pop{
  0%{ transform:scale(.7);opacity:0; }
  80%{ transform:scale(1.1); }
  100%{ transform:scale(1);opacity:1; }
}
.challenge-grid{
  display:grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap:14px;
  margin-top:14px;
}
</style>

<h1>Challenges</h1>
<p class="muted">Set a savings challenge. Complete it to earn your reward — or face a harder retry if you fail.</p>

<?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
<?php if ($errors): ?><div class="toast bad"><ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="grid two" style="margin-bottom:18px;">
  <div class="panel">
    <h2>New Challenge</h2>
    <form method="post" class="form">
      <input type="hidden" name="action" value="add">
      <div class="row">
        <label>Challenge Title</label>
        <input name="title" placeholder='e.g. "Save ₱1,000 this month"' required>
      </div>
      <div class="row">
        <label>Description (optional)</label>
        <input name="description" placeholder="What's your plan?">
      </div>
      <div class="row">
        <label>Savings Target (₱)</label>
        <input name="target_amount" type="number" step="0.01" min="1" required>
      </div>
      <div class="row">
        <label>Deadline</label>
        <input name="deadline" type="date" min="<?=h(date('Y-m-d', strtotime('+1 day')))?>" required>
      </div>
      <div class="row">
        <label>Reward (if you succeed)</label>
        <input name="reward" placeholder='e.g. "Treat yourself to milk tea!"' required>
      </div>
      <div class="row">
        <label>Penalty / Consequence (if you fail)</label>
        <input name="penalty" placeholder='e.g. "No social media for 3 days"' required>
      </div>
      <button class="btn" type="submit">Create Challenge</button>
    </form>
  </div>

  <div class="panel" style="display:flex;flex-direction:column;gap:10px;">
    <h2>Stats</h2>
    <?php
      $cTotal     = count($challenges);
      $cActive    = count(array_filter($challenges, fn($c)=>$c['status']==='active'));
      $cCompleted = count(array_filter($challenges, fn($c)=>$c['status']==='completed'));
      $cFailed    = count(array_filter($challenges, fn($c)=>$c['status']==='failed'));
    ?>
    <div class="card" style="padding:12px;">
      <div class="card-label">Total Challenges</div>
      <div class="card-value"><?=$cTotal?></div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;">
      <div class="card" style="padding:10px;">
        <div class="card-label" style="color:#6ee7ff;">Active</div>
        <div style="font-size:22px;font-weight:900;"><?=$cActive?></div>
      </div>
      <div class="card" style="padding:10px;">
        <div class="card-label" style="color:#4ade80;">Won</div>
        <div style="font-size:22px;font-weight:900;"><?=$cCompleted?></div>
      </div>
      <div class="card" style="padding:10px;">
        <div class="card-label" style="color:#fb7185;">Lost</div>
        <div style="font-size:22px;font-weight:900;"><?=$cFailed?></div>
      </div>
    </div>
    <p class="muted" style="font-size:12px;">Tip: Savings you add via the <a href="savings.php" style="color:var(--accent);">Savings page</a> count toward active challenges automatically.</p>
  </div>
</section>

<?php if (empty($challenges)): ?>
  <div class="panel" style="text-align:center;padding:40px;">
    <div style="font-size:48px;">🎯</div>
    <p>No challenges yet. Create one above and start saving!</p>
  </div>
<?php else: ?>

  <?php
  // Group by status
  $groups = ['active'=>[],'completed'=>[],'failed'=>[]];
  foreach ($challenges as $ch) $groups[$ch['status']][] = $ch;
  $labels = ['active'=>'Active','completed'=>'Completed','failed'=>'Failed / Retried'];
  ?>

  <?php foreach (['active','completed','failed'] as $status): ?>
    <?php if (empty($groups[$status])) continue; ?>
    <h2 style="margin:18px 0 4px;"><?=$labels[$status]?></h2>
    <div class="challenge-grid">
    <?php foreach ($groups[$status] as $ch):
      $saved    = challengeProgress($pdo, $userId, $ch['created_at'], $ch['deadline']);
      $target   = (float)$ch['target_amount'];
      $pct      = min(100, $target > 0 ? round($saved / $target * 100, 1) : 0);
      $daysLeft = (int)ceil((strtotime($ch['deadline']) - time()) / 86400);
      $diff     = (int)$ch['difficulty'];
      $stars    = str_repeat('★', min($diff,5)) . str_repeat('☆', max(0,5-$diff));
    ?>
    <div class="challenge-card <?=h($ch['status'])?>">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
        <div>
          <strong style="font-size:15px;"><?=h($ch['title'])?></strong>
          <?php if (!empty($ch['description'])): ?>
            <div class="muted" style="font-size:12px;margin-top:2px;"><?=h($ch['description'])?></div>
          <?php endif; ?>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:4px;flex-shrink:0;">
          <span class="badge <?=h($ch['status'])?>"><?=strtoupper($ch['status'])?></span>
          <?php if ($ch['retry_of']): ?><span class="badge retry">RETRY</span><?php endif; ?>
        </div>
      </div>

      <?php if ($diff > 1): ?>
        <div class="diff-stars" title="Difficulty level <?=$diff?>"><?=$stars?> Difficulty <?=$diff?></div>
      <?php endif; ?>

      <?php if ($ch['status'] === 'completed'): ?>
        <div class="celebration">🎉🏆🎉</div>
      <?php elseif ($ch['status'] === 'failed'): ?>
        <div style="text-align:center;font-size:22px;">😔</div>
      <?php endif; ?>

      <div>
        <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:5px;">
          <span class="muted">Progress</span>
          <span><?=money($saved)?> / <?=money($target)?> (<?=$pct?>%)</span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width:<?=$pct?>%"></div>
        </div>
      </div>

      <div style="display:flex;justify-content:space-between;font-size:12px;">
        <span class="muted">Deadline: <?=h($ch['deadline'])?></span>
        <?php if ($ch['status']==='active'): ?>
          <span style="color:<?=$daysLeft<=3?'var(--bad)':'var(--muted)'?>">
            <?=$daysLeft > 0 ? $daysLeft.' day'.($daysLeft!==1?'s':'').' left' : 'Ends today'?>
          </span>
        <?php endif; ?>
      </div>

      <div class="reward-box"><strong>Reward:</strong> <?=h($ch['reward'])?></div>
      <div class="penalty-box"><strong>If failed:</strong> <?=h($ch['penalty'])?></div>
    </div>
    <?php endforeach; ?>
    </div>
  <?php endforeach; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

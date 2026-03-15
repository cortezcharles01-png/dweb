<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
require __DIR__ . '/includes/auth.php';

$userId = (int)$user['id'];

// ── Auto-generate notifications ───────────────────────────────────────────────
// Runs every page load — checks conditions and inserts alerts if not yet created today.

function insertNotif(PDO $pdo, int $userId, string $type, string $message): void {
  // Avoid duplicate notifications for the same message on the same day
  $check = $pdo->prepare(
    "SELECT id FROM notifications
     WHERE user_id = ? AND type = ? AND message = ? AND DATE(created_at) = CURDATE()"
  );
  $check->execute([$userId, $type, $message]);
  if ($check->fetch()) return; // already notified today

  $pdo->prepare(
    "INSERT INTO notifications (user_id, type, message, is_read, created_at)
     VALUES (?, ?, ?, 0, NOW())"
  )->execute([$userId, $type, $message]);
}

// 1. Overdue bills
$overdueBills = $pdo->prepare(
  "SELECT name, due_date FROM bills
   WHERE user_id = ? AND status = 'unpaid' AND due_date < CURDATE()"
);
$overdueBills->execute([$userId]);
foreach ($overdueBills->fetchAll() as $bill) {
  insertNotif($pdo, $userId, 'overdue_bill',
    "Bill \"{$bill['name']}\" was due on {$bill['due_date']} and is still unpaid.");
}

// 2. Bills due within 3 days
$upcomingBills = $pdo->prepare(
  "SELECT name, due_date FROM bills
   WHERE user_id = ? AND status = 'unpaid'
   AND due_date >= CURDATE() AND due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
);
$upcomingBills->execute([$userId]);
foreach ($upcomingBills->fetchAll() as $bill) {
  insertNotif($pdo, $userId, 'upcoming_bill',
    "Bill \"{$bill['name']}\" is due on {$bill['due_date']} — only 3 days away.");
}

// 3. Challenge deadline within 3 days
$nearChallenges = $pdo->prepare(
  "SELECT title, deadline FROM challenges
   WHERE user_id = ? AND status = 'active'
   AND deadline >= CURDATE() AND deadline <= DATE_ADD(CURDATE(), INTERVAL 3 DAY)"
);
$nearChallenges->execute([$userId]);
foreach ($nearChallenges->fetchAll() as $ch) {
  insertNotif($pdo, $userId, 'challenge_deadline',
    "Challenge \"{$ch['title']}\" deadline is on {$ch['deadline']} — finish strong!");
}

// 4. Low wallet balance (below ₱100)
$income   = (float)$pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='income'")->execute([$userId]) ? $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='income'")->fetchColumn() : 0;
$expense  = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='expense'")->fetchColumn();
$saving   = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='saving'")->fetchColumn();
$billpaid = (float)$pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=$userId AND type='bill'")->fetchColumn();
$balance  = $income - $expense - $saving - $billpaid;

if ($balance < 100 && $balance >= 0) {
  insertNotif($pdo, $userId, 'low_balance',
    "Your wallet balance is low: " . money($balance) . ". Consider adding income or reducing expenses.");
}
if ($balance < 0) {
  insertNotif($pdo, $userId, 'negative_balance',
    "Warning: Your wallet balance is negative (" . money($balance) . "). Review your expenses.");
}

// ── Mark as read ──────────────────────────────────────────────────────────────
if (isset($_GET['mark_read'])) {
  $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
  header("Location: notifications.php");
  exit;
}

// ── Fetch notifications ───────────────────────────────────────────────────────
$showUnread = ($_GET['filter'] ?? 'all') === 'unread';

$sql = "SELECT * FROM notifications WHERE user_id = ?";
if ($showUnread) $sql .= " AND is_read = 0";
$sql .= " ORDER BY created_at DESC LIMIT 50";

$stmt = $pdo->prepare($sql);
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = (int)$pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0")->execute([$userId]) ?
  $pdo->query("SELECT COUNT(*) FROM notifications WHERE user_id=$userId AND is_read=0")->fetchColumn() : 0;

$pageTitle = "Notifications";
include __DIR__ . '/includes/header.php';
?>

<div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
  <h1 style="margin:0;">
    Notifications
    <?php if ($unreadCount > 0): ?>
      <span style="font-size:14px;background:rgba(251,113,133,.2);border:1px solid rgba(251,113,133,.4);color:#fb7185;padding:3px 10px;border-radius:999px;margin-left:8px;"><?=$unreadCount?> unread</span>
    <?php endif; ?>
  </h1>
  <div style="display:flex;gap:8px;">
    <a class="btn" href="notifications.php?filter=<?=$showUnread?'all':'unread'?>" style="text-decoration:none;font-size:12px;">
      <?=$showUnread ? 'Show All' : 'Unread Only'?>
    </a>
    <?php if ($unreadCount > 0): ?>
      <a class="btn" href="notifications.php?mark_read=1" style="text-decoration:none;font-size:12px;background:rgba(74,222,128,.1);border-color:rgba(74,222,128,.3);">
        Mark All Read
      </a>
    <?php endif; ?>
  </div>
</div>
<p class="muted" style="margin-bottom:16px;">Alerts are automatically generated for overdue bills, upcoming deadlines, and low balance.</p>

<?php if (empty($notifications)): ?>
  <div class="panel" style="text-align:center;padding:40px;">
    <div style="font-size:40px;">🔔</div>
    <p class="muted">No notifications<?=$showUnread?' unread':''?>. You're all caught up!</p>
  </div>
<?php else: ?>
  <div style="display:flex;flex-direction:column;gap:10px;">
    <?php
    $typeConfig = [
      'overdue_bill'     => ['🔴', '#fb7185', 'rgba(251,113,133,.12)', 'rgba(251,113,133,.35)'],
      'upcoming_bill'    => ['🟡', '#fbbf24', 'rgba(251,191,36,.10)',  'rgba(251,191,36,.35)'],
      'challenge_deadline'=>['🔵','#6ee7ff', 'rgba(110,231,255,.10)', 'rgba(110,231,255,.35)'],
      'low_balance'      => ['🟠', '#fb923c', 'rgba(251,146,60,.10)',  'rgba(251,146,60,.35)'],
      'negative_balance' => ['🔴', '#fb7185', 'rgba(251,113,133,.15)', 'rgba(251,113,133,.45)'],
    ];
    foreach ($notifications as $n):
      [$icon, $textColor, $bg, $borderColor] = $typeConfig[$n['type']] ?? ['⚪', '#e9ecf5', 'rgba(255,255,255,.05)', 'rgba(255,255,255,.15)'];
      $unreadStyle = $n['is_read'] ? 'opacity:.65;' : '';
    ?>
    <div style="<?=$unreadStyle?>padding:14px 16px;border-radius:14px;background:<?=$bg?>;border:1px solid <?=$borderColor?>;display:flex;gap:12px;align-items:flex-start;">
      <span style="font-size:20px;line-height:1.4;"><?=$icon?></span>
      <div style="flex:1;">
        <div style="font-size:14px;color:<?=$textColor?>;margin-bottom:3px;">
          <?=h($n['message'])?>
        </div>
        <div class="muted" style="font-size:11px;"><?=h($n['created_at'])?></div>
      </div>
      <?php if (!$n['is_read']): ?>
        <span style="width:8px;height:8px;border-radius:50%;background:<?=$textColor?>;flex-shrink:0;margin-top:6px;"></span>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

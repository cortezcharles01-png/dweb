<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
session_start();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = trim((string)($_POST['email'] ?? ''));
  $pass  = (string)($_POST['password'] ?? '');

  $stmt = $pdo->prepare("SELECT * FROM users WHERE email=?");
  $stmt->execute([$email]);
  $u = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$u || !password_verify($pass, (string)$u['password_hash'])) {
    $errors[] = "Invalid login.";
  } else {
    $_SESSION['user'] = ['id'=>(int)$u['id'], 'name'=>$u['name'], 'email'=>$u['email']];
    header("Location: index.php");
    exit;
  }
}

$pageTitle = "Login";
include __DIR__ . '/includes/header.php';
?>
<div class="panel" style="max-width:520px;margin:0 auto;">
  <h1>Login</h1>

  <?php if ($errors): ?>
    <div class="toast bad">
      <ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" class="form">
    <div class="row"><label>Email</label><input name="email" type="email" required></div>
    <div class="row"><label>Password</label><input name="password" type="password" required></div>
    <button class="btn" type="submit">Login</button>
  </form>

  <p class="muted" style="margin-top:10px;">
    No account yet? <a href="register.php" style="color:var(--accent);">Register</a>
  </p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

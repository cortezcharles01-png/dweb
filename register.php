<?php
declare(strict_types=1);
require __DIR__ . '/config/db.php';
session_start();

$errors = [];
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $name = trim((string)($_POST['name'] ?? ''));
  $email = trim((string)($_POST['email'] ?? ''));
  $pass = (string)($_POST['password'] ?? '');

  if ($name === '') $errors[] = "Name required.";
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Valid email required.";
  if (strlen($pass) < 6) $errors[] = "Password must be at least 6 characters.";

  if (!$errors) {
    try {
      $stmt = $pdo->prepare("INSERT INTO users(name,email,password_hash,created_at) VALUES(?,?,?,?)");
      $stmt->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT), date('Y-m-d H:i:s')]);
      $flash = "Account created. You can login now.";
    } catch (Throwable $e) {
      $errors[] = "Email already used.";
    }
  }
}

$pageTitle = "Register";
include __DIR__ . '/includes/header.php';
?>
<div class="panel" style="max-width:520px;margin:0 auto;">
  <h1>Create Account</h1>

  <?php if ($flash): ?><div class="toast ok"><?=h($flash)?></div><?php endif; ?>
  <?php if ($errors): ?>
    <div class="toast bad">
      <ul><?php foreach($errors as $e): ?><li><?=h($e)?></li><?php endforeach; ?></ul>
    </div>
  <?php endif; ?>

  <form method="post" class="form">
    <div class="row"><label>Name</label><input name="name" required></div>
    <div class="row"><label>Email</label><input name="email" type="email" required></div>
    <div class="row"><label>Password</label><input name="password" type="password" required></div>
    <button class="btn" type="submit">Register</button>
  </form>

  <p class="muted" style="margin-top:10px;">
    Already have an account? <a href="login.php" style="color:var(--accent);">Login</a>
  </p>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
declare(strict_types=1);

// If db.php was required before header, h() exists.
// But to be safe, fall back if header is opened alone.
if (!function_exists('h')) {
  function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
}

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$isLoggedIn = isset($_SESSION['user']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title><?= isset($pageTitle) ? h($pageTitle) : 'Student Savings' ?></title>
  <link rel="stylesheet" href="css/style.css"/>
</head>
<body>
<header class="topbar">
  <div class="wrap topbar-inner">
    <div class="brand">
      <div class="logo">₱</div>
      <div>
        <div class="title">Student Savings</div>
        <div class="subtitle">Wallet • Expenses • Savings • Bills</div>
      </div>
    </div>

    <nav class="nav">
      <?php if ($isLoggedIn): ?>
        <a href="index.php">Dashboard</a>
        <a href="wallet.php">Wallet</a>
        <a href="transactions.php">Transactions</a>
        <a href="savings.php">Savings</a>
        <a href="bills.php">Bills</a>
        <a class="danger" href="logout.php">Logout</a>
      <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

<main class="wrap main">

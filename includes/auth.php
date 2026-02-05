<?php
declare(strict_types=1);
session_start();

if (!isset($_SESSION['user'])) {
  header("Location: login.php");
  exit;
}

$user = $_SESSION['user']; // ['id','name','email']

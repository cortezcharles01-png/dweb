<?php
declare(strict_types=1);

$baseDir = dirname(__DIR__);
$dataDir = $baseDir . DIRECTORY_SEPARATOR . 'data';
$dbPath  = $dataDir . DIRECTORY_SEPARATOR . 'app.db';

if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

try {
  $pdo = new PDO('sqlite:' . $dbPath);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->exec('PRAGMA foreign_keys = ON;');
} catch (Throwable $e) {
  http_response_code(500);
  die("DB Error: " . htmlspecialchars($e->getMessage()));
}

/* Tables */
$pdo->exec("
CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS transactions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  type TEXT NOT NULL CHECK(type IN ('income','expense','saving','bill')),
  category TEXT NOT NULL,
  description TEXT,
  amount REAL NOT NULL CHECK(amount > 0),
  tdate TEXT NOT NULL,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS bills (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  amount REAL NOT NULL CHECK(amount > 0),
  due_date TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'unpaid' CHECK(status IN ('unpaid','paid')),
  paid_at TEXT,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS challenges (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  title TEXT NOT NULL,
  description TEXT,
  target_amount REAL NOT NULL CHECK(target_amount > 0),
  reward TEXT NOT NULL,
  penalty TEXT NOT NULL,
  deadline TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'active' CHECK(status IN ('active','completed','failed')),
  difficulty INTEGER NOT NULL DEFAULT 1,
  retry_of INTEGER,
  created_at TEXT NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(retry_of) REFERENCES challenges(id)
);
");

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return '₱' . number_format($v, 2); }
function today(): string { return date('Y-m-d'); }

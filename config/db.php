<?php
declare(strict_types=1);

// ============================================================
//  Database Configuration — MySQL
//  Edit the four constants below to match your environment.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'student_savings');
define('DB_USER', 'root');       // change to your MySQL username
define('DB_PASS', '');           // change to your MySQL password

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES   => false,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  die("DB Error: " . htmlspecialchars($e->getMessage()));
}

function h(string $s): string  { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return '₱' . number_format($v, 2); }
function today(): string        { return date('Y-m-d'); }

<?php
declare(strict_types=1);

// ============================================================
//  Database Configuration — MySQL
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'student_savings');
define('DB_USER', 'root');
define('DB_PASS', '');

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

function h(string $s): string   { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function money(float $v): string { return '₱' . number_format($v, 2); }
function today(): string         { return date('Y-m-d'); }

/**
 * GABRIEL — Audit Trail Feature
 * Logs any user action into the activity_logs table.
 * Call this after every significant database operation.
 *
 * @param PDO    $pdo
 * @param int    $userId
 * @param string $actionType  e.g. 'income', 'expense', 'bill', 'login', 'saving', 'challenge'
 * @param string $description Human-readable description of what happened
 */
function logActivity(PDO $pdo, int $userId, string $actionType, string $description): void {
  $stmt = $pdo->prepare(
    "INSERT INTO activity_logs (user_id, action_type, description, created_at)
     VALUES (?, ?, ?, ?)"
  );
  $stmt->execute([$userId, $actionType, $description, date('Y-m-d H:i:s')]);
}


-- ============================================================
--  Student Savings Website — MySQL Database Schema
--  Includes all original tables + individual feature additions
--  Run once: mysql -u root -p < database.sql
-- ============================================================

CREATE DATABASE IF NOT EXISTS student_savings
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE student_savings;

-- ------------------------------------------------------------
-- Table: users
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  name          VARCHAR(150)    NOT NULL,
  email         VARCHAR(255)    NOT NULL UNIQUE,
  password_hash VARCHAR(255)    NOT NULL,
  created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: transactions
-- deleted_at added by JENSON — Soft Delete feature
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS transactions (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  type        ENUM('income','expense','saving','bill') NOT NULL,
  category    VARCHAR(150)    NOT NULL,
  description VARCHAR(500)    DEFAULT NULL,
  amount      DECIMAL(12,2)   NOT NULL CHECK (amount > 0),
  tdate       DATE            NOT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at  DATETIME        DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: bills
-- deleted_at added by JENSON — Soft Delete feature
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bills (
  id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED    NOT NULL,
  name        VARCHAR(255)    NOT NULL,
  amount      DECIMAL(12,2)   NOT NULL CHECK (amount > 0),
  due_date    DATE            NOT NULL,
  status      ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
  paid_at     DATETIME        DEFAULT NULL,
  created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  deleted_at  DATETIME        DEFAULT NULL,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: challenges
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS challenges (
  id             INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED     NOT NULL,
  title          VARCHAR(255)     NOT NULL,
  description    TEXT             DEFAULT NULL,
  target_amount  DECIMAL(12,2)    NOT NULL CHECK (target_amount > 0),
  reward         VARCHAR(500)     NOT NULL,
  penalty        VARCHAR(500)     NOT NULL,
  deadline       DATE             NOT NULL,
  status         ENUM('active','completed','failed') NOT NULL DEFAULT 'active',
  difficulty     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  retry_of       INT UNSIGNED     DEFAULT NULL,
  created_at     DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id)  REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (retry_of) REFERENCES challenges(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: activity_logs — GABRIEL: Audit Trail Feature
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_logs (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NOT NULL,
  action_type VARCHAR(50)   NOT NULL,
  description TEXT          NOT NULL,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: notifications — YURIES: Notification Simulation
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  user_id     INT UNSIGNED  NOT NULL,
  type        VARCHAR(50)   NOT NULL,
  message     TEXT          NOT NULL,
  is_read     TINYINT(1)    NOT NULL DEFAULT 0,
  created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Stored Procedure: GetFinancialSummary
-- Returns full financial summary for a given user
-- Usage: CALL GetFinancialSummary(1);
-- ------------------------------------------------------------
DROP PROCEDURE IF EXISTS GetFinancialSummary;

DELIMITER $$

CREATE PROCEDURE GetFinancialSummary(IN p_user_id INT)
BEGIN
  DECLARE v_income    DECIMAL(12,2) DEFAULT 0;
  DECLARE v_expense   DECIMAL(12,2) DEFAULT 0;
  DECLARE v_saving    DECIMAL(12,2) DEFAULT 0;
  DECLARE v_bills     DECIMAL(12,2) DEFAULT 0;
  DECLARE v_unpaid    INT DEFAULT 0;

  SELECT COALESCE(SUM(amount), 0) INTO v_income
  FROM transactions
  WHERE user_id = p_user_id AND type = 'income' AND deleted_at IS NULL;

  SELECT COALESCE(SUM(amount), 0) INTO v_expense
  FROM transactions
  WHERE user_id = p_user_id AND type = 'expense' AND deleted_at IS NULL;

  SELECT COALESCE(SUM(amount), 0) INTO v_saving
  FROM transactions
  WHERE user_id = p_user_id AND type = 'saving' AND deleted_at IS NULL;

  SELECT COALESCE(SUM(amount), 0) INTO v_bills
  FROM transactions
  WHERE user_id = p_user_id AND type = 'bill' AND deleted_at IS NULL;

  SELECT COUNT(*) INTO v_unpaid
  FROM bills
  WHERE user_id = p_user_id AND status = 'unpaid' AND deleted_at IS NULL;

  SELECT
    v_income                                    AS total_income,
    v_expense                                   AS total_expenses,
    v_saving                                    AS total_savings,
    v_bills                                     AS total_bills_paid,
    (v_income - v_expense - v_saving - v_bills) AS wallet_balance,
    v_unpaid                                    AS unpaid_bills;
END$$

DELIMITER ;
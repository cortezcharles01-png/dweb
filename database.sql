-- ============================================================
--  Student Savings Website — MySQL Database Schema
--  Run this file once to set up the database.
--  Command: mysql -u root -p < database.sql
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
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: bills
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
  PRIMARY KEY (id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Table: challenges
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS challenges (
  id             INT UNSIGNED    NOT NULL AUTO_INCREMENT,
  user_id        INT UNSIGNED    NOT NULL,
  title          VARCHAR(255)    NOT NULL,
  description    TEXT            DEFAULT NULL,
  target_amount  DECIMAL(12,2)  NOT NULL CHECK (target_amount > 0),
  reward         VARCHAR(500)    NOT NULL,
  penalty        VARCHAR(500)    NOT NULL,
  deadline       DATE            NOT NULL,
  status         ENUM('active','completed','failed') NOT NULL DEFAULT 'active',
  difficulty     TINYINT UNSIGNED NOT NULL DEFAULT 1,
  retry_of       INT UNSIGNED    DEFAULT NULL,
  created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  FOREIGN KEY (user_id)  REFERENCES users(id)       ON DELETE CASCADE,
  FOREIGN KEY (retry_of) REFERENCES challenges(id)  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

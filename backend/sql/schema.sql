-- Schema for PHP API (MySQL)
-- Charset: utf8mb4, Engine: InnoDB
-- Default admin user password: Admin@123 (bcrypt)

SET NAMES utf8mb4;
SET time_zone = '+00:00';

DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS manual_balances;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS periods;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  role VARCHAR(50) NOT NULL DEFAULT 'admin',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password, role) VALUES
('Admin', 'admin@example.com', '$2y$10$Hx2LBosJ9w2a6fL/XftgFunmsU.lXGm12LmEd6u4UpUkpcTgBr0f.', 'admin');
-- غيّر البريد/كلمة المرور بعد الاستيراد: UPDATE users SET password = '<hash-from-password_hash>';

CREATE TABLE periods (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(50) NOT NULL,
  start_date DATE DEFAULT NULL,
  end_date DATE DEFAULT NULL,
  is_closed TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE transactions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  transaction_type_id INT UNSIGNED DEFAULT NULL,
  period_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  type VARCHAR(50) NOT NULL,
  source VARCHAR(50) NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  description TEXT DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  category_id INT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (id),
  KEY idx_transactions_period (period_id),
  KEY idx_transactions_type (type),
  KEY idx_transactions_source (source),
  KEY idx_transactions_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE manual_balances (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  period_id INT UNSIGNED NOT NULL,
  manual_company_balance DECIMAL(12,2) NOT NULL,
  note TEXT DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_manual_period (period_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  description TEXT DEFAULT NULL,
  entity_type VARCHAR(100) DEFAULT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_logs_action (action),
  KEY idx_logs_user (user_id),
  KEY idx_logs_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ملاحظة: أضف قيود FOREIGN KEY إذا لزم الأمر بعد التأكد من تطابق البيانات الموجودة.

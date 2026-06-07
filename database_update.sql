-- ============================================================
--  Richiamo Coffee — Database Updates
--  Run this in phpMyAdmin after the original database.sql
--  Safe to run multiple times (uses IF NOT EXISTS checks)
-- ============================================================

USE richiamo_coffee;

-- ── Add google_id to users (for Google Sign-In) ───────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS google_id VARCHAR(50) DEFAULT NULL AFTER phone,
  ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) DEFAULT NULL AFTER google_id;

-- ── Add index on google_id ────────────────────────────────────
ALTER TABLE users
  ADD INDEX IF NOT EXISTS idx_google_id (google_id);

-- ── Add 'reset' to user_sessions role ENUM ───────────────────
-- Note: user_sessions.role is VARCHAR(20), so 'reset' already works.
-- No change needed.

-- ── Add notes to order_items ──────────────────────────────────
ALTER TABLE order_items
  ADD COLUMN IF NOT EXISTS notes VARCHAR(255) DEFAULT NULL AFTER quantity;

-- ── Ensure categories have all needed columns ─────────────────
ALTER TABLE categories
  ADD COLUMN IF NOT EXISTS description VARCHAR(255) DEFAULT NULL AFTER name;

-- ── Add promo_code support to orders ─────────────────────────
ALTER TABLE orders
  ADD COLUMN IF NOT EXISTS promo_code VARCHAR(30) DEFAULT NULL AFTER notes,
  ADD COLUMN IF NOT EXISTS discount_amount DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER promo_code;

-- ── Create promo_codes table ──────────────────────────────────
CREATE TABLE IF NOT EXISTS promo_codes (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code         VARCHAR(30)   NOT NULL UNIQUE,
  description  VARCHAR(150)  DEFAULT NULL,
  discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(8,2) NOT NULL,
  min_order    DECIMAL(8,2)  NOT NULL DEFAULT 0,
  max_uses     INT           DEFAULT NULL,
  uses_count   INT           NOT NULL DEFAULT 0,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  expires_at   DATETIME      DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Sample promo codes ────────────────────────────────────────
INSERT IGNORE INTO promo_codes (code, description, discount_type, discount_value, min_order, max_uses, is_active) VALUES
  ('WELCOME10',  '10% off your first order', 'percent', 10.00, 10.00, 100, 1),
  ('FLAT5',      'RM5 off orders above RM30', 'fixed',   5.00,  30.00, 50,  1);

-- ── Create activity_log table for audit trail ─────────────────
CREATE TABLE IF NOT EXISTS activity_log (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED  DEFAULT NULL,
  action       VARCHAR(100)  NOT NULL,
  description  TEXT          DEFAULT NULL,
  ip_address   VARCHAR(45)   DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user   (user_id),
  INDEX idx_action (action),
  INDEX idx_time   (created_at)
) ENGINE=InnoDB;

-- ── Create table_numbers table ────────────────────────────────
CREATE TABLE IF NOT EXISTS tables (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  table_number VARCHAR(10)   NOT NULL UNIQUE,
  capacity     INT           NOT NULL DEFAULT 4,
  is_active    TINYINT(1)    NOT NULL DEFAULT 1,
  qr_token     VARCHAR(64)   DEFAULT NULL,
  created_at   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── Insert default tables ─────────────────────────────────────
INSERT IGNORE INTO tables (table_number, capacity) VALUES
  ('A1', 2), ('A2', 2), ('A3', 4), ('A4', 4),
  ('B1', 4), ('B2', 4), ('B3', 6), ('B4', 6),
  ('C1', 8), ('C2', 8);

-- ── Add last_seen to users ────────────────────────────────────
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS last_seen DATETIME DEFAULT NULL AFTER last_login;

SELECT 'Database update completed successfully!' AS status;

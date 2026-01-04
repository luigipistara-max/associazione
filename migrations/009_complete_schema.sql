-- Migration 009: Complete Schema Update
-- Date: 2026-01-04
-- Description: Update existing installations to add missing tables/columns from recent features
-- This migration combines all features added in recent PRs for a complete upgrade

-- ============================================================================
-- NEWS TABLES
-- ============================================================================

-- News/Blog posts table
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    cover_image VARCHAR(500),
    author_id INT NOT NULL,
    target_type ENUM('all', 'groups') DEFAULT 'all',
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at DATETIME NULL,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_author (author_id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News groups junction table (for targeting specific groups)
CREATE TABLE IF NOT EXISTS news_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_news_group (news_id, group_id),
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2FA AND PASSWORD EXPIRY COLUMNS
-- ============================================================================

-- Add 2FA columns to users table (ignore if exists)
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname1 = 'two_factor_secret';
SET @columnname2 = 'password_changed_at';
SET @preparedStatement1 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname1)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname1, ' VARCHAR(32) NULL AFTER email')
));
PREPARE alterIfNotExists1 FROM @preparedStatement1;
EXECUTE alterIfNotExists1;
DEALLOCATE PREPARE alterIfNotExists1;

SET @preparedStatement2 = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname2)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname2, ' DATETIME NULL AFTER two_factor_secret')
));
PREPARE alterIfNotExists2 FROM @preparedStatement2;
EXECUTE alterIfNotExists2;
DEALLOCATE PREPARE alterIfNotExists2;

-- Add password_changed_at to members table (ignore if exists)
SET @tablename = 'members';
SET @columnname = 'password_changed_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATETIME NULL AFTER portal_token_expires')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- EVENT APPROVAL COLUMNS
-- ============================================================================

-- Add registration_status column to event_responses (ignore if exists)
SET @tablename = 'event_responses';
SET @columnname = 'registration_status';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' ENUM(''pending'', ''approved'', ''rejected'') DEFAULT ''pending'' AFTER response')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add approved_by column to event_responses (ignore if exists)
SET @columnname = 'approved_by';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT NULL AFTER registration_status')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add approved_at column to event_responses (ignore if exists)
SET @columnname = 'approved_at';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' DATETIME NULL AFTER approved_by')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add rejection_reason column to event_responses (ignore if exists)
SET @columnname = 'rejection_reason';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (table_name = @tablename)
      AND (table_schema = @dbname)
      AND (column_name = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) NULL AFTER approved_at')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add foreign key for approved_by if not exists
SET @tablename = 'event_responses';
SET @fkname = 'fk_event_responses_approved_by';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE
      CONSTRAINT_NAME = @fkname
      AND TABLE_SCHEMA = @dbname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @fkname, ' FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ============================================================================
-- SECURITY SETTINGS
-- ============================================================================

-- Security settings for reCAPTCHA, 2FA, password expiry
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('recaptcha_enabled', '0', 'security'),
('recaptcha_site_key', '', 'security'),
('recaptcha_secret_key', '', 'security'),
('2fa_enabled', '0', 'security'),
('2fa_required_for', 'none', 'security'),
('password_expiry_users', '0', 'security'),
('password_expiry_members', '0', 'security');

-- ============================================================================
-- SMTP SETTINGS
-- ============================================================================

-- SMTP configuration for external email servers
INSERT IGNORE INTO settings (setting_key, setting_value, setting_group) VALUES
('smtp_enabled', '0', 'email'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_security', 'tls', 'email'),
('smtp_username', '', 'email'),
('smtp_password', '', 'email'),
('smtp_from_email', '', 'email'),
('smtp_from_name', '', 'email');

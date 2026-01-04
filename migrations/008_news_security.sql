-- Migration: News System & Security Features
-- Date: 2026-01-04
-- Description: Adds news/blog system and advanced security settings

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
-- SECURITY SETTINGS
-- ============================================================================

-- Security settings for reCAPTCHA, 2FA, password expiry
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('recaptcha_enabled', '0', 'security'),
('recaptcha_site_key', '', 'security'),
('recaptcha_secret_key', '', 'security'),
('2fa_enabled', '0', 'security'),
('2fa_required_for', 'none', 'security'),
('password_expiry_users', '0', 'security'),
('password_expiry_members', '0', 'security')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

-- ============================================================================
-- ALTER EXISTING TABLES FOR SECURITY FEATURES
-- ============================================================================

-- Add two_factor_secret column to users table
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) NULL AFTER email,
ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER two_factor_secret;

-- Add password_changed_at column to members table
ALTER TABLE members 
ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER portal_token_expires;

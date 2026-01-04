-- =============================================================================
-- Migration: Portal Soci - Parte 1
-- Description: Adds member portal with authentication and profile features
-- Date: 2026-01-04
-- =============================================================================

-- IMPORTANT: Run this migration ONLY if you are upgrading from a previous version
-- If you are doing a fresh installation, use schema.sql instead

-- =============================================================================
-- 1. Add portal columns to members table
-- =============================================================================

ALTER TABLE members 
ADD COLUMN portal_password VARCHAR(255) NULL AFTER notes,
ADD COLUMN portal_token VARCHAR(64) NULL AFTER portal_password,
ADD COLUMN portal_token_expires DATETIME NULL AFTER portal_token,
ADD COLUMN photo_url VARCHAR(500) NULL AFTER portal_token_expires,
ADD COLUMN last_portal_login DATETIME NULL AFTER photo_url,
ADD INDEX idx_portal_token (portal_token);

-- =============================================================================
-- 2. Add flags to member_groups table
-- =============================================================================

ALTER TABLE member_groups 
ADD COLUMN is_hidden BOOLEAN DEFAULT FALSE AFTER is_active,
ADD COLUMN is_restricted BOOLEAN DEFAULT FALSE AFTER is_hidden;

-- =============================================================================
-- 3. Add payment tracking columns to member_fees table
-- =============================================================================

ALTER TABLE member_fees 
ADD COLUMN payment_pending BOOLEAN DEFAULT FALSE AFTER status,
ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_pending,
ADD COLUMN paypal_transaction_id VARCHAR(100) NULL AFTER payment_reference,
ADD COLUMN payment_confirmed_by INT NULL AFTER paypal_confirmed_by,
ADD COLUMN payment_confirmed_at DATETIME NULL AFTER payment_confirmed_by;

-- =============================================================================
-- 4. Create member_group_requests table
-- =============================================================================

CREATE TABLE IF NOT EXISTS member_group_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    admin_notes TEXT,
    UNIQUE KEY unique_pending_request (member_id, group_id, status),
    INDEX idx_member (member_id),
    INDEX idx_group (group_id),
    INDEX idx_status (status),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Migration completed successfully!
-- =============================================================================
-- 
-- Next steps:
-- 1. Configure API keys in Settings > API tab (ImgBB for photo uploads)
-- 2. Use the "Send Activation" button in member edit to invite members
-- 3. Members will receive email with link to set their portal password
-- 4. Portal is accessible at: /portal/login.php
-- 
-- =============================================================================

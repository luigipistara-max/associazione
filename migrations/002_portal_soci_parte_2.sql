-- =============================================================================
-- Migration: Portal Soci - Parte 2
-- Description: Adds event responses, enhanced group requests, and payment features
-- Date: 2026-01-04
-- =============================================================================

-- IMPORTANT: Run this migration ONLY if you are upgrading from a previous version
-- If you are doing a fresh installation, use schema.sql instead

-- =============================================================================
-- 1. Create event_responses table
-- =============================================================================

CREATE TABLE IF NOT EXISTS event_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    response ENUM('yes', 'no', 'maybe') NOT NULL,
    notes TEXT,
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_response (event_id, member_id),
    INDEX idx_event (event_id),
    INDEX idx_member (member_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- Migration completed successfully!
-- =============================================================================
-- 
-- Next steps:
-- 1. Configure PayPal credentials in Settings > Payments (if using PayPal)
-- 2. Configure bank account details in Settings > Payments (for bank transfers)
-- 3. Members can now:
--    - View and respond to events (Events page)
--    - Request to join groups (Groups page)
--    - Pay membership fees online or offline (Payments page)
--    - View their receipts (Receipts page)
-- 4. Admins can:
--    - Approve/reject group requests (Group Requests page)
--    - Confirm offline payments (Confirm Payments page)
--    - See event responses in event detail view
-- 
-- =============================================================================

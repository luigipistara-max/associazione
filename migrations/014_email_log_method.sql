-- Migration: Add method column to email_log table
-- Description: Track which method was used to send emails (smtp, mail, or altervista)

-- Add method column to email_log table
ALTER TABLE email_log 
ADD COLUMN method VARCHAR(20) DEFAULT 'mail' AFTER status,
ADD INDEX idx_method (method);

-- Update existing records to have 'mail' as default method
UPDATE email_log SET method = 'mail' WHERE method IS NULL;

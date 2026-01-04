-- Migration: Add registration_status to event_responses
-- Date: 2026-01-04
-- Description: Adds approval workflow for event registrations

-- Add registration_status column
ALTER TABLE event_responses 
ADD COLUMN registration_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER response;

-- Add approved_by column (user who approved/rejected)
ALTER TABLE event_responses 
ADD COLUMN approved_by INT NULL AFTER registration_status;

-- Add approved_at column
ALTER TABLE event_responses 
ADD COLUMN approved_at DATETIME NULL AFTER approved_by;

-- Add rejection_reason column
ALTER TABLE event_responses 
ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER approved_at;

-- Add foreign key for approved_by
ALTER TABLE event_responses 
ADD CONSTRAINT fk_event_responses_approved_by 
FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL;

-- Update existing responses with 'yes' to 'approved' (retrocompatibilità)
-- UPDATE event_responses SET registration_status = 'approved' WHERE response = 'yes';

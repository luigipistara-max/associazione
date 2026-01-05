-- Migration: Add 'revoked' status to event_responses registration_status
-- Date: 2026-01-05
-- Description: Adds 'revoked' status to allow admins to revoke previously approved registrations

-- Modify registration_status enum to include 'revoked'
ALTER TABLE event_responses 
MODIFY COLUMN registration_status ENUM('pending', 'approved', 'rejected', 'revoked') DEFAULT 'pending';

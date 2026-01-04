-- Migration: Add UNIQUE constraint to category names
-- Description: Prevents duplicate category names in income and expense categories
-- Date: 2026-01-04

-- Note: MySQL doesn't support IF NOT EXISTS for constraints in ALTER TABLE
-- We'll use a safe approach that ignores errors if constraint already exists

-- Add UNIQUE constraint to income_categories
-- Skip this if the constraint already exists (handled by migration runner or manual check)
ALTER TABLE income_categories 
ADD UNIQUE KEY unique_name (name);

-- Add UNIQUE constraint to expense_categories  
-- Skip this if the constraint already exists (handled by migration runner or manual check)
ALTER TABLE expense_categories 
ADD UNIQUE KEY unique_name (name);

-- Note: If running manually and constraint already exists, you'll see:
-- ERROR 1061: Duplicate key name 'unique_name'
-- This is safe to ignore - it means the constraint is already in place.

-- Migration: Add UNIQUE constraint to category names
-- Description: Prevents duplicate category names in income and expense categories
-- Date: 2026-01-04

-- Add UNIQUE constraint to income_categories (if not exists)
ALTER TABLE {prefix}income_categories 
ADD UNIQUE KEY unique_name (name);

-- Add UNIQUE constraint to expense_categories (if not exists)
ALTER TABLE {prefix}expense_categories 
ADD UNIQUE KEY unique_name (name);

-- Migration: Add birth_province and residence_province to members table
-- This adds province fields for birth location and residence location

-- Add birth_province column after birth_place
ALTER TABLE members ADD COLUMN birth_province VARCHAR(2) NULL AFTER birth_place;

-- Note: The 'province' field already exists and will be used as residence_province
-- No need to add a separate residence_province column

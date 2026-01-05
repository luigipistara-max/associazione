-- Migration: Add birth_province and residence_province to members table
-- This adds province fields for birth location and residence location

-- Add birth_province column after birth_place
ALTER TABLE members ADD COLUMN birth_province VARCHAR(2) NULL AFTER birth_place;

-- Add residence_province column after city  
ALTER TABLE members ADD COLUMN residence_province VARCHAR(2) NULL AFTER city;

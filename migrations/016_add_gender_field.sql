-- Migration: Add gender field to members table
-- Date: 2026-01-05
-- Description: Add gender column to members table for fiscal code calculation

ALTER TABLE members ADD COLUMN gender ENUM('M', 'F') NULL AFTER birth_province;

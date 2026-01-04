-- Migration: Add receipts table
-- Date: 2026-01-04
-- Description: Add receipts table for automatic receipt generation with YYYY/NNNN numbering

-- Create receipts table
CREATE TABLE IF NOT EXISTS {prefix}receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    receipt_number VARCHAR(20) NOT NULL UNIQUE,
    member_id INT NOT NULL,
    member_fee_id INT NULL,
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    payment_method ENUM('cash', 'bank_transfer', 'card', 'paypal', 'other') DEFAULT 'cash',
    payment_method_details VARCHAR(255) DEFAULT 'In contanti presso la sede sociale',
    issue_date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    INDEX idx_member (member_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_issue_date (issue_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

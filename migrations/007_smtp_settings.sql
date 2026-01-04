-- Migration: Add SMTP settings
-- Date: 2026-01-04
-- Description: Adds SMTP configuration options for external email services

-- SMTP Settings
INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description) VALUES
('smtp_enabled', '0', 'email', 'boolean', 'Usa SMTP esterno', 'Abilita invio email tramite server SMTP esterno invece di mail() nativo'),
('smtp_host', '', 'email', 'text', 'Server SMTP', 'Indirizzo del server SMTP (es: smtp.gmail.com)'),
('smtp_port', '587', 'email', 'number', 'Porta SMTP', 'Porta del server SMTP (25, 465 per SSL, 587 per TLS)'),
('smtp_security', 'tls', 'email', 'select', 'Sicurezza', 'Tipo di connessione sicura (none, ssl, tls)'),
('smtp_username', '', 'email', 'text', 'Username SMTP', 'Username per autenticazione SMTP (solitamente l''email)'),
('smtp_password', '', 'email', 'password', 'Password SMTP', 'Password per autenticazione SMTP'),
('smtp_from_email', '', 'email', 'email', 'Email Mittente', 'Indirizzo email del mittente'),
('smtp_from_name', '', 'email', 'text', 'Nome Mittente', 'Nome visualizzato come mittente')
ON DUPLICATE KEY UPDATE setting_key = setting_key;

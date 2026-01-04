-- AssoLife Database Schema
-- Table names WITHOUT prefix (installer will add prefix)

-- Users table (admin/operator accounts)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    two_factor_secret VARCHAR(32) NULL,
    password_changed_at DATETIME NULL,
    role ENUM('admin', 'operatore') DEFAULT 'operatore',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Password resets table
CREATE TABLE IF NOT EXISTS password_resets (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Members table (association members)
CREATE TABLE IF NOT EXISTS members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    membership_number VARCHAR(50) UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    fiscal_code VARCHAR(16) UNIQUE NOT NULL,
    birth_date DATE,
    birth_place VARCHAR(100),
    email VARCHAR(100),
    phone VARCHAR(20),
    address VARCHAR(255),
    city VARCHAR(100),
    province VARCHAR(2),
    postal_code VARCHAR(10),
    registration_date DATE NOT NULL,
    status ENUM('attivo', 'sospeso', 'cessato') DEFAULT 'attivo',
    notes TEXT,
    card_token VARCHAR(64) NULL UNIQUE,
    card_generated_at DATETIME NULL,
    portal_password VARCHAR(255) NULL,
    portal_token VARCHAR(64) NULL,
    portal_token_expires DATETIME NULL,
    password_changed_at DATETIME NULL,
    photo_url VARCHAR(500) NULL,
    last_portal_login DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_fiscal_code (fiscal_code),
    INDEX idx_card_token (card_token),
    INDEX idx_portal_token (portal_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Social years table
CREATE TABLE IF NOT EXISTS social_years (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    fee_amount DECIMAL(10,2) DEFAULT 0,
    is_current BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_is_current (is_current)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Income categories
CREATE TABLE IF NOT EXISTS income_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense categories
CREATE TABLE IF NOT EXISTS expense_categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Income movements
CREATE TABLE IF NOT EXISTS income (
    id INT PRIMARY KEY AUTO_INCREMENT,
    social_year_id INT NOT NULL,
    category_id INT NOT NULL,
    member_id INT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50),
    transaction_date DATE NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_social_year (social_year_id),
    INDEX idx_category (category_id),
    INDEX idx_member (member_id),
    INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Expense movements
CREATE TABLE IF NOT EXISTS expenses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    social_year_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50),
    transaction_date DATE NOT NULL,
    description VARCHAR(255),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_social_year (social_year_id),
    INDEX idx_category (category_id),
    INDEX idx_transaction_date (transaction_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member fees table
CREATE TABLE IF NOT EXISTS member_fees (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    social_year_id INT NOT NULL,
    fee_type VARCHAR(50) DEFAULT 'quota_associativa',
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    payment_method VARCHAR(50),
    receipt_number VARCHAR(50),
    receipt_generated_at DATETIME NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    payment_pending BOOLEAN DEFAULT FALSE,
    payment_reference VARCHAR(100) NULL,
    paypal_transaction_id VARCHAR(100) NULL,
    payment_confirmed_by INT NULL,
    payment_confirmed_at DATETIME NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_member (member_id),
    INDEX idx_social_year (social_year_id),
    INDEX idx_status (status),
    INDEX idx_due_date (due_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Receipts table
CREATE TABLE IF NOT EXISTS receipts (
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

-- Insert default income categories
INSERT INTO income_categories (name, sort_order) VALUES
('Quote associative', 1),
('Liberalità', 2),
('Contributi', 3),
('Sponsorizzazioni', 4),
('Altre entrate', 5);

-- Insert default expense categories
INSERT INTO expense_categories (name, sort_order) VALUES
('Affitto', 1),
('Utenze', 2),
('Materiali', 3),
('Servizi', 4),
('Personale', 5),
('Eventi', 6),
('Imposte', 7),
('Altre uscite', 8);

-- Email templates table
CREATE TABLE IF NOT EXISTS email_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    variables TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email queue table
CREATE TABLE IF NOT EXISTS email_queue (
    id INT PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(255) NOT NULL,
    to_name VARCHAR(100),
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT,
    status ENUM('pending','processing','sent','failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    max_attempts INT DEFAULT 3,
    error_message TEXT,
    scheduled_at DATETIME,
    sent_at DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_scheduled (scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email log table
CREATE TABLE IF NOT EXISTS email_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    status ENUM('sent','failed') NOT NULL,
    error_message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_to_email (to_email),
    INDEX idx_sent_at (sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audit log table
CREATE TABLE IF NOT EXISTS audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    username VARCHAR(50),
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    entity_name VARCHAR(255),
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default email templates
INSERT INTO email_templates (code, name, subject, body_html, body_text, variables) VALUES
('password_reset', 'Reset Password', 'Recupero Password - {app_name}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Hai richiesto il reset della password per il tuo account.</p><p>Clicca sul seguente link per reimpostare la password:</p><p><a href="{link}">{link}</a></p><p>Il link è valido per 1 ora.</p><p>Se non hai richiesto questo reset, ignora questa email.</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nHai richiesto il reset della password per il tuo account.\n\nClicca sul seguente link per reimpostare la password:\n{link}\n\nIl link è valido per 1 ora.\n\nSe non hai richiesto questo reset, ignora questa email.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "link", "app_name"]'),

('welcome_member', 'Benvenuto Nuovo Socio', 'Benvenuto in {app_name}!', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Benvenuto/a nella nostra associazione!</p><p>Il tuo numero di tessera è: <strong>{numero_tessera}</strong></p><p>Grazie per aver scelto di far parte della nostra comunità.</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nBenvenuto/a nella nostra associazione!\n\nIl tuo numero di tessera è: {numero_tessera}\n\nGrazie per aver scelto di far parte della nostra comunità.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "numero_tessera", "app_name"]'),

('fee_reminder', 'Sollecito Quota in Scadenza', 'Sollecito Quota Associativa - {app_name}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Ti ricordiamo che la tua quota associativa per l\\'anno <strong>{anno}</strong> è in scadenza.</p><ul><li><strong>Importo:</strong> {importo}</li><li><strong>Scadenza:</strong> {scadenza}</li></ul><p>Ti invitiamo a provvedere al pagamento quanto prima.</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nTi ricordiamo che la tua quota associativa per l\\'anno {anno} è in scadenza.\n\nImporto: {importo}\nScadenza: {scadenza}\n\nTi invitiamo a provvedere al pagamento quanto prima.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "anno", "importo", "scadenza", "app_name"]'),

('fee_overdue', 'Quota Scaduta', 'Quota Associativa Scaduta - {app_name}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>La tua quota associativa per l\\'anno <strong>{anno}</strong> è <span style="color: red;">scaduta</span>.</p><ul><li><strong>Importo:</strong> {importo}</li><li><strong>Scadenza:</strong> {scadenza}</li></ul><p>Ti invitiamo a regolarizzare la tua posizione al più presto.</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nLa tua quota associativa per l\\'anno {anno} è scaduta.\n\nImporto: {importo}\nScadenza: {scadenza}\n\nTi invitiamo a regolarizzare la tua posizione al più presto.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "anno", "importo", "scadenza", "app_name"]'),

('fee_receipt', 'Conferma Pagamento Quota', 'Conferma Pagamento - {app_name}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Confermiamo il ricevimento del pagamento della tua quota associativa.</p><ul><li><strong>Importo:</strong> {importo}</li><li><strong>Data Pagamento:</strong> {data_pagamento}</li><li><strong>Metodo:</strong> {metodo_pagamento}</li></ul><p>Grazie per il tuo contributo!</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nConfermiamo il ricevimento del pagamento della tua quota associativa.\n\nImporto: {importo}\nData Pagamento: {data_pagamento}\nMetodo: {metodo_pagamento}\n\nGrazie per il tuo contributo!\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "importo", "data_pagamento", "metodo_pagamento", "app_name"]'),

('new_fee_notification', 'Nuova Quota Associativa', 'Nuova Quota Associativa {anno}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>È stata generata la quota associativa per l\\'anno <strong>{anno}</strong>.</p><ul><li><strong>Importo:</strong> {importo}</li><li><strong>Scadenza:</strong> {scadenza}</li></ul><p>Ti invitiamo a provvedere al pagamento entro la scadenza indicata.</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\nÈ stata generata la quota associativa per l\\'anno {anno}.\n\nImporto: {importo}\nScadenza: {scadenza}\n\nTi invitiamo a provvedere al pagamento entro la scadenza indicata.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "anno", "importo", "scadenza", "app_name"]'),

('generic_notification', 'Notifica Generica', '{subject}', 
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>{message}</p><p>Cordiali saluti,<br>{app_name}</p>', 
'Gentile {nome} {cognome},\n\n{message}\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "subject", "message", "app_name"]'),

('event_registration', 'Conferma Iscrizione Evento', 'Conferma Iscrizione: {titolo}',
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>La tua iscrizione all\\'evento <strong>{titolo}</strong> è confermata!</p><p><strong>Data:</strong> {data}<br><strong>Ora:</strong> {ora}</p>{dettagli_modalita}<p>Ci vediamo all\\'evento!</p><p>Cordiali saluti,<br>{app_name}</p>',
'Gentile {nome} {cognome},\n\nLa tua iscrizione all\\'evento {titolo} è confermata!\n\nData: {data}\nOra: {ora}\n{dettagli_modalita}\n\nCi vediamo all\\'evento!\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "titolo", "data", "ora", "dettagli_modalita", "app_name"]'),

('event_reminder', 'Promemoria Evento', 'Promemoria: {titolo}',
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Ti ricordiamo che l\\'evento <strong>{titolo}</strong> si terrà:</p><p><strong>Data:</strong> {data}<br><strong>Ora:</strong> {ora}</p>{dettagli_modalita}<p>Ti aspettiamo!</p><p>Cordiali saluti,<br>{app_name}</p>',
'Gentile {nome} {cognome},\n\nTi ricordiamo che l\\'evento {titolo} si terrà:\n\nData: {data}\nOra: {ora}\n{dettagli_modalita}\n\nTi aspettiamo!\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "titolo", "data", "ora", "dettagli_modalita", "app_name"]'),

('event_online_link', 'Link Evento Online', 'Link per partecipare: {titolo}',
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Ecco le informazioni per partecipare all\\'evento online <strong>{titolo}</strong>:</p><p><strong>Data:</strong> {data}<br><strong>Ora:</strong> {ora}<br><strong>Piattaforma:</strong> {piattaforma}</p><p><strong>Link di accesso:</strong><br><a href="{link}">{link}</a></p>{password_info}{istruzioni}<p>Ti aspettiamo online!</p><p>Cordiali saluti,<br>{app_name}</p>',
'Gentile {nome} {cognome},\n\nEcco le informazioni per partecipare all\\'evento online {titolo}:\n\nData: {data}\nOra: {ora}\nPiattaforma: {piattaforma}\n\nLink di accesso:\n{link}\n{password_info}{istruzioni}\n\nTi aspettiamo online!\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "titolo", "data", "ora", "piattaforma", "link", "password_info", "istruzioni", "app_name"]'),

('event_cancelled', 'Evento Annullato', 'Annullamento Evento: {titolo}',
'<p>Gentile <strong>{nome} {cognome}</strong>,</p><p>Siamo spiacenti di informarti che l\\'evento <strong>{titolo}</strong> previsto per il <strong>{data}</strong> è stato annullato.</p><p>{motivo}</p><p>Ci scusiamo per l\\'inconveniente.</p><p>Cordiali saluti,<br>{app_name}</p>',
'Gentile {nome} {cognome},\n\nSiamo spiacenti di informarti che l\\'evento {titolo} previsto per il {data} è stato annullato.\n\n{motivo}\n\nCi scusiamo per l\\'inconveniente.\n\nCordiali saluti,\n{app_name}',
'["nome", "cognome", "titolo", "data", "motivo", "app_name"]');

-- Events table
CREATE TABLE IF NOT EXISTS events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    event_date DATE NOT NULL,
    event_time TIME,
    end_date DATE,
    end_time TIME,
    
    -- Event mode: in person, online or hybrid
    event_mode ENUM('in_person', 'online', 'hybrid') DEFAULT 'in_person',
    
    -- Fields for IN PERSON events
    location VARCHAR(255),
    address VARCHAR(255),
    city VARCHAR(100),
    
    -- Fields for ONLINE events
    online_link VARCHAR(500),
    online_platform VARCHAR(100),
    online_instructions TEXT,
    online_password VARCHAR(100),
    
    max_participants INT DEFAULT 0,  -- 0 = unlimited
    registration_deadline DATE,
    cost DECIMAL(10,2) DEFAULT 0,
    status ENUM('draft', 'published', 'cancelled', 'completed') DEFAULT 'draft',
    target_type ENUM('all', 'groups') DEFAULT 'all',  -- all = all members, groups = specific groups
    
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_event_date (event_date),
    INDEX idx_status (status),
    INDEX idx_event_mode (event_mode),
    INDEX idx_target_type (target_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event registrations table
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    registered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payment_status ENUM('pending', 'paid', 'refunded', 'not_required') DEFAULT 'pending',
    attendance_status ENUM('registered', 'confirmed', 'attended', 'absent', 'waitlist') DEFAULT 'registered',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_registration (event_id, member_id),
    INDEX idx_event (event_id),
    INDEX idx_member (member_id),
    INDEX idx_attendance (attendance_status),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mass email batches table
CREATE TABLE IF NOT EXISTS mass_email_batches (
    id INT PRIMARY KEY AUTO_INCREMENT,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    filter_type VARCHAR(50) NOT NULL,
    filter_params TEXT,
    total_recipients INT DEFAULT 0,
    sent_count INT DEFAULT 0,
    failed_count INT DEFAULT 0,
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member groups table
CREATE TABLE IF NOT EXISTS member_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    color VARCHAR(7) DEFAULT '#6c757d',
    is_active BOOLEAN DEFAULT TRUE,
    is_hidden BOOLEAN DEFAULT FALSE,
    is_restricted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member group members table (N:N relationship)
CREATE TABLE IF NOT EXISTS member_group_members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_group_member (group_id, member_id),
    INDEX idx_group (group_id),
    INDEX idx_member (member_id),
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Member group requests table
CREATE TABLE IF NOT EXISTS member_group_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    member_id INT NOT NULL,
    group_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    message TEXT,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    admin_notes TEXT,
    UNIQUE KEY unique_pending_request (member_id, group_id, status),
    INDEX idx_member (member_id),
    INDEX idx_group (group_id),
    INDEX idx_status (status),
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event target groups table (N:N relationship)
CREATE TABLE IF NOT EXISTS event_target_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    group_id INT NOT NULL,
    UNIQUE KEY unique_event_group (event_id, group_id),
    INDEX idx_event (event_id),
    INDEX idx_group (group_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Event responses table (member availability for events)
CREATE TABLE IF NOT EXISTS event_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    response ENUM('yes', 'no', 'maybe') NOT NULL,
    registration_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    approved_by INT NULL,
    approved_at DATETIME NULL,
    rejection_reason VARCHAR(255) NULL,
    notes TEXT,
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_response (event_id, member_id),
    INDEX idx_event (event_id),
    INDEX idx_member (member_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News/Blog posts table
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(191) NOT NULL UNIQUE,
    content LONGTEXT NOT NULL,
    excerpt TEXT,
    cover_image VARCHAR(500),
    author_id INT NOT NULL,
    target_type ENUM('all', 'groups') DEFAULT 'all',
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    published_at DATETIME NULL,
    views_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status),
    INDEX idx_published_at (published_at),
    INDEX idx_author (author_id),
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- News groups junction table (for targeting specific groups)
CREATE TABLE IF NOT EXISTS news_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_news_group (news_id, group_id),
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Settings table (key-value storage for association configuration)
CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_group VARCHAR(50) DEFAULT 'general',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key),
    INDEX idx_group (setting_group)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- DEFAULT SETTINGS
-- ============================================================================

-- Security settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('recaptcha_enabled', '0', 'security'),
('recaptcha_site_key', '', 'security'),
('recaptcha_secret_key', '', 'security'),
('2fa_enabled', '0', 'security'),
('2fa_required_for', 'none', 'security'),
('password_expiry_users', '0', 'security'),
('password_expiry_members', '0', 'security'),
('cron_token', '', 'security');

-- SMTP settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('smtp_enabled', '0', 'email'),
('smtp_host', '', 'email'),
('smtp_port', '587', 'email'),
('smtp_security', 'tls', 'email'),
('smtp_username', '', 'email'),
('smtp_password', '', 'email'),
('smtp_from_email', '', 'email'),
('smtp_from_name', '', 'email'),
('email_send_mode', 'direct', 'email');

-- API/Integration settings
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('tinymce_api_key', '', 'api');

-- ============================================================================
-- MIGRATION: Add fee_amount column to social_years (for existing installations)
-- Run this if upgrading from a previous version
-- ============================================================================
-- ALTER TABLE social_years ADD COLUMN fee_amount DECIMAL(10,2) DEFAULT 0 AFTER end_date;

-- ============================================================================
-- MIGRATION: Portal Soci - Part 1 (Member Portal)
-- Run this if upgrading from a previous version
-- ============================================================================

-- Add portal columns to members table
-- ALTER TABLE members ADD COLUMN portal_password VARCHAR(255) NULL AFTER notes;
-- ALTER TABLE members ADD COLUMN portal_token VARCHAR(64) NULL AFTER portal_password;
-- ALTER TABLE members ADD COLUMN portal_token_expires DATETIME NULL AFTER portal_token;
-- ALTER TABLE members ADD COLUMN photo_url VARCHAR(500) NULL AFTER portal_token_expires;
-- ALTER TABLE members ADD COLUMN last_portal_login DATETIME NULL AFTER photo_url;
-- ALTER TABLE members ADD INDEX idx_portal_token (portal_token);

-- Add flags to member_groups table
-- ALTER TABLE member_groups ADD COLUMN is_hidden BOOLEAN DEFAULT FALSE AFTER is_active;
-- ALTER TABLE member_groups ADD COLUMN is_restricted BOOLEAN DEFAULT FALSE AFTER is_hidden;

-- Add payment tracking columns to member_fees table
-- ALTER TABLE member_fees ADD COLUMN payment_pending BOOLEAN DEFAULT FALSE AFTER status;
-- ALTER TABLE member_fees ADD COLUMN payment_reference VARCHAR(100) NULL AFTER payment_pending;
-- ALTER TABLE member_fees ADD COLUMN paypal_transaction_id VARCHAR(100) NULL AFTER payment_reference;
-- ALTER TABLE member_fees ADD COLUMN payment_confirmed_by INT NULL AFTER paypal_transaction_id;
-- ALTER TABLE member_fees ADD COLUMN payment_confirmed_at DATETIME NULL AFTER payment_confirmed_by;

-- Create member_group_requests table (only if it doesn't exist)
-- Note: This table is already created above in the main schema
-- The following is kept as a comment for reference only
-- CREATE TABLE IF NOT EXISTS member_group_requests (
--     id INT PRIMARY KEY AUTO_INCREMENT,
--     member_id INT NOT NULL,
--     group_id INT NOT NULL,
--     status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
--     message TEXT,
--     requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
--     processed_at TIMESTAMP NULL,
--     processed_by INT NULL,
--     admin_notes TEXT,
--     UNIQUE KEY unique_pending_request (member_id, group_id, status),
--     INDEX idx_member (member_id),
--     INDEX idx_group (group_id),
--     INDEX idx_status (status),
--     FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
--     FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE,
--     FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
-- ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

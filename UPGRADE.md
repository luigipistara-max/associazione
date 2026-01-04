# Guida Upgrade AssoLife

## Upgrade SMTP Settings (v1.x.x)

### Prerequisiti
- Backup del database prima di procedere
- Accesso al database MySQL/MariaDB

### Procedura

#### 1. Backup Database
```bash
mysqldump -u [utente] -p [database] > backup_pre_upgrade.sql
```

#### 2. Eseguire Migrazione Database
Eseguire il file SQL di migrazione:

**Opzione A: Da phpMyAdmin**
1. Accedi a phpMyAdmin
2. Seleziona il database dell'associazione
3. Vai su "SQL"
4. Copia e incolla il contenuto di `migrations/007_smtp_settings.sql`
5. Clicca "Esegui"

**Opzione B: Da linea di comando**
```bash
mysql -u [utente] -p [database] < migrations/007_smtp_settings.sql
```

**Opzione C: Su AlterVista**
1. Accedi al pannello AlterVista
2. Vai su "Gestione Database" > "phpMyAdmin"
3. Seleziona il tuo database
4. Clicca su "SQL"
5. Incolla ed esegui:

```sql
INSERT INTO settings (setting_key, setting_value, setting_group, setting_type, setting_label, setting_description) VALUES
('smtp_enabled', '0', 'email', 'boolean', 'Usa SMTP esterno', 'Abilita invio email tramite server SMTP esterno'),
('smtp_host', '', 'email', 'text', 'Server SMTP', 'Indirizzo del server SMTP'),
('smtp_port', '587', 'email', 'number', 'Porta SMTP', 'Porta del server SMTP'),
('smtp_security', 'tls', 'email', 'select', 'Sicurezza', 'Tipo di connessione sicura'),
('smtp_username', '', 'email', 'text', 'Username SMTP', 'Username per autenticazione SMTP'),
('smtp_password', '', 'email', 'password', 'Password SMTP', 'Password per autenticazione SMTP'),
('smtp_from_email', '', 'email', 'email', 'Email Mittente', 'Indirizzo email del mittente'),
('smtp_from_name', '', 'email', 'text', 'Nome Mittente', 'Nome visualizzato come mittente')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

#### 3. Configurazione
1. Vai su Impostazioni > Email / SMTP
2. Seleziona "SMTP esterno"
3. Scegli il tuo provider (Gmail, Libero, ecc.)
4. Inserisci le credenziali
5. Clicca "Invia Email di Test"

### Note per Gmail
Per Gmail devi usare una **"Password per le app"**, non la password normale:
1. Attiva la [Verifica in 2 passaggi](https://myaccount.google.com/security)
2. Vai su [Password per le app](https://myaccount.google.com/apppasswords)
3. Crea una nuova password per "Posta"
4. Usa quella password nelle impostazioni SMTP

---

## Upgrade alla versione con Approvazione Iscrizioni Eventi

### Prerequisiti
- Backup del database prima di procedere
- Accesso al database MySQL/MariaDB

### Procedura

#### 1. Backup Database
```bash
mysqldump -u [utente] -p [database] > backup_pre_upgrade.sql
```

#### 2. Eseguire Migrazione
Eseguire il file SQL di migrazione:

**Opzione A: Da phpMyAdmin**
1. Accedi a phpMyAdmin
2. Seleziona il database dell'associazione
3. Vai su "SQL"
4. Copia e incolla il contenuto di `migrations/006_event_registration_status.sql`
5. Clicca "Esegui"

**Opzione B: Da linea di comando**
```bash
mysql -u [utente] -p [database] < migrations/006_event_registration_status.sql
```

**Opzione C: Su AlterVista**
1. Accedi al pannello AlterVista
2. Vai su "Gestione Database" > "phpMyAdmin"
3. Seleziona il tuo database
4. Clicca su "SQL"
5. Incolla ed esegui:

```sql
ALTER TABLE event_responses 
ADD COLUMN registration_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending' AFTER response;

ALTER TABLE event_responses 
ADD COLUMN approved_by INT NULL AFTER registration_status;

ALTER TABLE event_responses 
ADD COLUMN approved_at DATETIME NULL AFTER approved_by;

ALTER TABLE event_responses 
ADD COLUMN rejection_reason VARCHAR(255) NULL AFTER approved_at;
```

#### 3. Carica i file aggiornati
Carica i file PHP aggiornati sul server via FTP.

#### 4. Verifica
1. Accedi come admin
2. Vai su un evento
3. Verifica che compaia la sezione "Disponibilità in attesa"
4. Verifica i pulsanti Approva/Rifiuta

### Retrocompatibilità
Le risposte esistenti avranno `registration_status = 'pending'`.
Se vuoi che le risposte "Sì" esistenti siano automaticamente approvate, esegui:

```sql
UPDATE event_responses SET registration_status = 'approved', approved_at = NOW() WHERE response = 'yes';
```

### Rollback (in caso di problemi)
```sql
ALTER TABLE event_responses DROP COLUMN registration_status;
ALTER TABLE event_responses DROP COLUMN approved_by;
ALTER TABLE event_responses DROP COLUMN approved_at;
ALTER TABLE event_responses DROP COLUMN rejection_reason;
```

---

## Upgrade News System e Sicurezza Avanzata (v2.0.0)

### Prerequisiti
- Backup del database prima di procedere
- Accesso al database MySQL/MariaDB
- PHP 7.4 o superiore

### Novità in questa versione
1. **Sistema Notizie/Blog**
   - Gestione completa notizie per admin
   - Pubblicazione notizie per soci
   - Target per gruppi specifici
   - Editor WYSIWYG con TinyMCE

2. **Sicurezza Avanzata**
   - Google reCAPTCHA v2 per login
   - Autenticazione a Due Fattori (2FA) con Google Authenticator
   - Scadenza password configurabile

### Procedura

#### 1. Backup Database
```bash
mysqldump -u [utente] -p [database] > backup_pre_upgrade_v2.sql
```

#### 2. Eseguire Migrazione Database

**Opzione A: Da phpMyAdmin**
1. Accedi a phpMyAdmin
2. Seleziona il database dell'associazione
3. Vai su "SQL"
4. Copia e incolla il contenuto di `migrations/008_news_security.sql`
5. Clicca "Esegui"

**Opzione B: Da linea di comando**
```bash
mysql -u [utente] -p [database] < migrations/008_news_security.sql
```

**Opzione C: Su AlterVista - Eseguire i seguenti comandi SQL uno alla volta**

Prima parte - Tabelle News:
```sql
CREATE TABLE IF NOT EXISTS news (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL UNIQUE,
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

CREATE TABLE IF NOT EXISTS news_groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    news_id INT NOT NULL,
    group_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_news_group (news_id, group_id),
    FOREIGN KEY (news_id) REFERENCES news(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES member_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Seconda parte - Impostazioni Sicurezza:
```sql
INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
('recaptcha_enabled', '0', 'security'),
('recaptcha_site_key', '', 'security'),
('recaptcha_secret_key', '', 'security'),
('2fa_enabled', '0', 'security'),
('2fa_required_for', 'none', 'security'),
('password_expiry_users', '0', 'security'),
('password_expiry_members', '0', 'security')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
```

Terza parte - Colonne Sicurezza:
```sql
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS two_factor_secret VARCHAR(32) NULL AFTER email,
ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER two_factor_secret;

ALTER TABLE members 
ADD COLUMN IF NOT EXISTS password_changed_at DATETIME NULL AFTER portal_token_expires;
```

**Nota AlterVista**: Se ricevi errori con `IF NOT EXISTS`, esegui senza quella parte:
```sql
ALTER TABLE users 
ADD COLUMN two_factor_secret VARCHAR(32) NULL AFTER email,
ADD COLUMN password_changed_at DATETIME NULL AFTER two_factor_secret;
```

#### 3. Carica i file aggiornati
Carica i nuovi file PHP sul server via FTP:
- `public/settings.php` (aggiornato)
- `public/login.php` (aggiornato)
- `public/news.php` (nuovo)
- `public/news_edit.php` (nuovo)
- `public/portal/news.php` (nuovo)
- `public/portal/news_view.php` (nuovo)
- `src/functions.php` (aggiornato)
- `src/2fa.php` (nuovo)

#### 4. Configurazione reCAPTCHA (Opzionale)

Se vuoi abilitare reCAPTCHA:
1. Vai su [Google reCAPTCHA Admin](https://www.google.com/recaptcha/admin)
2. Registra un nuovo sito (scegli reCAPTCHA v2 "Non sono un robot")
3. Inserisci il dominio del tuo sito
4. Copia le chiavi Site Key e Secret Key
5. Vai su Impostazioni > Sicurezza
6. Abilita reCAPTCHA e inserisci le chiavi

#### 5. Configurazione 2FA (Opzionale)

Per abilitare l'autenticazione a due fattori:
1. Vai su Impostazioni > Sicurezza
2. Abilita "2FA con Google Authenticator"
3. Scegli se renderlo obbligatorio (nessuno/admin/staff/tutti)
4. Gli utenti potranno configurare 2FA dal loro profilo

#### 6. Verifica

**Sistema Notizie:**
1. Accedi come admin
2. Vai su "Notizie" nel menu
3. Crea una nuova notizia di test
4. Pubblica la notizia
5. Accedi al portale soci e verifica che la notizia sia visibile

**Sicurezza:**
1. Controlla che il tab "Sicurezza" sia visibile in Impostazioni
2. Verifica le nuove opzioni di sicurezza
3. Se abilitato reCAPTCHA, esci e prova a fare login (dovrebbe apparire il captcha)

### Note Importanti

#### reCAPTCHA
- Gratuito fino a 1 milione di richieste/mese
- Richiede dominio valido (non funziona su localhost)
- Chiavi diverse per test (localhost) e produzione

#### 2FA
- Richiede app come Google Authenticator, Authy o Microsoft Authenticator
- Una volta abilitato e configurato, il codice sarà richiesto a ogni login
- Conservare i backup codes in un luogo sicuro

#### Password Expiry
- 0 = disabilitato
- Valori consigliati: 90-180 giorni per admin, 180-365 giorni per soci
- Gli utenti riceveranno notifica prima della scadenza

### Troubleshooting

**Errore "Table already exists" durante migrazione:**
- Normale se hai già eseguito la migrazione
- Puoi ignorare l'errore

**reCAPTCHA non appare:**
- Verifica che le chiavi siano inserite correttamente
- Controlla che il dominio sia registrato su Google reCAPTCHA
- Svuota cache del browser

**News non visibili nel portale:**
- Verifica che lo stato sia "Pubblicata"
- Controlla che la data di pubblicazione non sia nel futuro
- Se targetizzata a gruppi, verifica che il socio sia nel gruppo corretto

### Rollback (in caso di problemi)

**Rimuovere tabelle news:**
```sql
DROP TABLE IF EXISTS news_groups;
DROP TABLE IF EXISTS news;
```

**Rimuovere impostazioni sicurezza:**
```sql
DELETE FROM settings WHERE setting_key IN (
    'recaptcha_enabled', 'recaptcha_site_key', 'recaptcha_secret_key',
    '2fa_enabled', '2fa_required_for', 
    'password_expiry_users', 'password_expiry_members'
);
```

**Rimuovere colonne sicurezza:**
```sql
ALTER TABLE users DROP COLUMN two_factor_secret, DROP COLUMN password_changed_at;
ALTER TABLE members DROP COLUMN password_changed_at;
```

Poi ripristina il backup:
```bash
mysql -u [utente] -p [database] < backup_pre_upgrade_v2.sql
```

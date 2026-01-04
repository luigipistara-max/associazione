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

# Guida Upgrade AssoLife

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

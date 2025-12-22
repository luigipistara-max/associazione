# Gestione Associazione

Sistema completo per la gestione di un'associazione con funzionalità di gestione soci, movimenti finanziari, rendiconti e import/export dati.

## Caratteristiche

### Autenticazione e Sicurezza
- Login con password hashing sicuro (bcrypt)
- Due ruoli: **admin** (accesso completo) e **operatore** (gestione soci e movimenti)
- Protezione CSRF su tutti i form
- Sessioni sicure (httponly, samesite)

### Anagrafica Soci
- Gestione completa dei dati dei soci
- Validazione del codice fiscale italiano
- Ricerca e filtri per stato (attivo/sospeso/cessato)
- Import massivo da CSV

### Anni Sociali
- Creazione e gestione degli anni sociali
- Possibilità di impostare l'anno corrente
- Collegamento automatico dei movimenti

### Gestione Finanziaria
- **Entrate**: quote associative, liberalità, contributi, sponsorizzazioni, altre entrate
- **Uscite**: affitto, utenze, materiali, servizi, personale, eventi, imposte, altre uscite
- Collegamento opzionale entrata-socio
- Metodo pagamento, numero ricevuta, note
- Filtri per tipo, anno sociale e categoria

### Categorie Configurabili
- Categorie entrate e uscite gestibili dall'admin
- Ordinamento personalizzabile
- Attivazione/disattivazione

### Rendiconto
- Report economico/finanziario per anno sociale
- Totali entrate e uscite per categoria
- Calcolo saldo/risultato d'esercizio
- Visualizzazione grafica con percentuali
- Funzione di stampa

### Import/Export
- Import soci da CSV
- Import movimenti da CSV
- Export movimenti in formato Excel (.xls)
- Supporto separatori `;` e `,`

### Interfaccia
- Tema Bootstrap 5 responsive
- Icone Bootstrap Icons
- Dashboard con statistiche e ultimi movimenti
- Navigazione intuitiva

## Requisiti

- **PHP**: 7.4 o superiore (consigliato PHP 8.x)
- **Database**: MySQL 5.7+ o MariaDB 10.3+
- **Web Server**: Apache, Nginx o compatibile
- **Estensioni PHP richieste**: PDO, pdo_mysql, mbstring

## Installazione

### 1. Preparazione

1. Scarica o clona il repository
2. Carica i file sul tuo server web
3. Assicurati che la cartella `public/` sia la document root (o configura il web server di conseguenza)

### 2. Installazione Guidata

1. Apri il browser e vai a: `http://tuosito.it/install.php`
2. Segui la procedura guidata:
   - **Step 1**: Benvenuto e requisiti
   - **Step 2**: Configurazione database
   - **Step 3**: Creazione utente amministratore
   - **Step 4**: Completamento

### 3. Configurazione Database

Durante l'installazione ti verrà chiesto di inserire:

- **Host Database**: di solito `localhost`
- **Nome Database**: nome del tuo database MySQL
- **Username Database**: utente MySQL con permessi sul database
- **Password Database**: password dell'utente MySQL

### 4. Primo Accesso

Dopo l'installazione:

1. Vai a `http://tuosito.it/login.php`
2. Accedi con le credenziali create durante l'installazione
3. Inizia a configurare il sistema

## Installazione su AlterVista

AlterVista è un servizio di hosting gratuito italiano. Per installare su AlterVista:

1. **Crea un account** su [AlterVista.org](https://www.altervista.org)

2. **Accedi al pannello di controllo** e crea un database MySQL

3. **Carica i file** tramite FTP nella cartella del tuo sito

4. **Avvia l'installazione** visitando `http://tuosito.altervista.org/install.php`

5. **Configurazione Database**:
   - Host: `localhost`
   - Nome DB: `my_nomeutente` (il nome è visibile nel pannello di controllo)
   - Username: fornito da AlterVista
   - Password: fornita da AlterVista

## Struttura Directory

```
/
├── public/                 # Document root
│   ├── index.php          # Dashboard
│   ├── install.php        # Installazione guidata
│   ├── login.php          # Login
│   ├── logout.php         # Logout
│   ├── members.php        # Gestione soci
│   ├── member_edit.php    # Modifica socio
│   ├── users.php          # Gestione utenti (admin)
│   ├── years.php          # Anni sociali
│   ├── categories.php     # Categorie entrate/uscite
│   ├── finance.php        # Movimenti entrate/uscite
│   ├── reports.php        # Rendiconto
│   ├── import_members.php # Import soci CSV
│   ├── import_movements.php # Import movimenti CSV
│   ├── export_excel.php   # Export Excel
│   └── inc/
│       ├── header.php     # Header comune
│       └── footer.php     # Footer comune
├── src/
│   ├── config.php         # Configurazione DB
│   ├── config_local.php   # Configurazione locale (creata durante l'installazione)
│   ├── db.php             # Connessione PDO
│   ├── auth.php           # Autenticazione
│   └── functions.php      # Funzioni utilità
├── schema.sql             # Schema database
└── README.md              # Documentazione
```

## Tracciati CSV

### Import Soci

Formato CSV con separatore `;` o `,`:

```csv
first_name;last_name;tax_code;birth_date;birth_place;email;phone;address;city;postal_code;notes
Mario;Rossi;RSSMRA80A01H501U;1980-01-01;Roma;mario.rossi@email.it;3331234567;Via Roma 1;Roma;00100;Note varie
```

**Campi obbligatori**: first_name, last_name, tax_code

### Import Movimenti

Formato CSV con separatore `;` o `,`:

```csv
type;paid_at;category;description;amount;member_tax_code
income;2024-01-15;Quote associative;Quota associativa 2024;50.00;RSSMRA80A01H501U
expense;2024-01-20;Utenze;Bolletta elettrica gennaio;150.00;
```

**Campi obbligatori**: type (income/expense), category (deve esistere), description, amount

**Nota**: member_tax_code è opzionale e utilizzato solo per le entrate.

## Guida Utilizzo

### Gestione Soci

1. Vai su **Soci** nel menu laterale
2. Clicca **Nuovo Socio** per aggiungere un socio
3. Compila i dati anagrafici (nome, cognome e codice fiscale sono obbligatori)
4. Il codice fiscale viene validato automaticamente
5. Usa i filtri per cercare soci per stato o dati anagrafici

### Gestione Movimenti

1. Vai su **Movimenti** nel menu laterale
2. Clicca **Nuovo Movimento**
3. Seleziona tipo (Entrata/Uscita)
4. Scegli la categoria appropriata
5. Inserisci importo, data e descrizione
6. Opzionalmente collega a un socio e/o anno sociale

### Rendiconto

1. Vai su **Rendiconto** nel menu laterale
2. Seleziona l'anno sociale (o visualizza tutti)
3. Visualizza il report con totali per categoria
4. Usa il pulsante **Stampa** per stampare il rendiconto
5. Usa **Esporta Excel** per scaricare i dati

### Gestione Categorie (Admin)

1. Vai su **Categorie** nel menu laterale
2. Gestisci separatamente categorie entrate e uscite
3. Modifica l'ordine di visualizzazione
4. Disattiva categorie non più utilizzate (non possono essere eliminate se hanno movimenti collegati)

### Gestione Utenti (Admin)

1. Vai su **Utenti** nel menu laterale
2. Crea nuovi utenti con ruolo Admin o Operatore
3. Gli operatori possono gestire soci e movimenti ma non utenti e categorie

## Sicurezza

- Password hashate con `password_hash()` di PHP (bcrypt)
- Prepared statements PDO per prevenire SQL injection
- Token CSRF su tutti i form
- Validazione input lato server
- Output escaped con `htmlspecialchars()`
- Sessioni sicure con httponly e samesite

## Backup

### Backup Database

Esporta regolarmente il database MySQL:

```bash
mysqldump -u username -p database_name > backup.sql
```

### Backup File

Effettua backup periodici della cartella del progetto, in particolare:
- File di configurazione `src/config_local.php`
- Eventuali file caricati dagli utenti

## Supporto

Per problemi o domande:

1. Controlla che i requisiti siano soddisfatti
2. Verifica i log del web server per errori
3. Assicurati che le credenziali del database siano corrette
4. Verifica i permessi delle cartelle

## Licenza

Questo progetto è distribuito sotto licenza MIT. Puoi utilizzarlo, modificarlo e distribuirlo liberamente.

## Crediti

- **Framework CSS**: Bootstrap 5
- **Icone**: Bootstrap Icons
- **Database**: MySQL/MariaDB
- **Linguaggio**: PHP

---

**Versione**: 1.0.0  
**Data**: 2024

# AssoLife - Sistema Gestione Associazione

**AssoLife** è un sistema completo per la gestione di associazioni, sviluppato da **Luigi Pistarà**.

## Caratteristiche Principali

### 🔐 Autenticazione e Sicurezza
- Sistema di login sicuro con password hashing (bcrypt)
- Due ruoli utente: **Admin** (accesso completo) e **Operatore** (gestione soci e movimenti)
- Protezione CSRF su tutti i form
- Sessioni sicure (httponly, samesite)
- Supporto redirect HTTPS opzionale

### 👥 Gestione Soci
- Anagrafica completa con validazione codice fiscale italiano
- Numero tessera, dati anagrafici, contatti e indirizzo
- Stati: Attivo, Sospeso, Cessato
- Ricerca e filtri avanzati
- Import massivo da CSV

### 📅 Anni Sociali
- Gestione periodi contabili
- Impostazione anno corrente
- Collegamento con movimenti finanziari

### 💰 Gestione Finanziaria
- Registrazione entrate e uscite
- Categorie personalizzabili (entrate e uscite)
- Collegamento entrate-soci opzionale
- Metodi di pagamento e numeri ricevuta
- Filtri per anno sociale

### 📊 Rendiconto e Report
- Report economico/finanziario per anno sociale
- Totali per categoria con percentuali
- Grafici a barre visuali
- Calcolo saldo/risultato d'esercizio
- Export in formato Excel (CSV)

### 📥 Import/Export
- Import soci da CSV
- Import movimenti da CSV
- Export rendiconto in Excel
- Supporto separatori ; e ,

## Installazione

### Requisiti
- PHP 7.4 o superiore
- MySQL 5.7 o superiore / MariaDB 10.2+
- Server web (Apache/Nginx)
- Modulo PHP PDO MySQL

### Procedura di Installazione

1. **Carica i file sul server**
   - Carica tutti i file nella directory del tuo hosting
   - Assicurati che la directory `src/` sia scrivibile

2. **Avvia l'installer**
   - Naviga su `http://tuosito.com/public/install.php`
   - Compila il form di installazione:

#### Configurazione Database
- **Host**: localhost (o l'host del tuo database)
- **Nome Database**: nome del database MySQL
- **Utente**: utente MySQL
- **Password**: password MySQL
- **Prefisso Tabelle**: opzionale (es: `assolife_`)

#### Configurazione Sito
- **Nome del Sito**: il nome della tua associazione
- **Path di Installazione**: rilevato automaticamente, ma modificabile
- **Forza HTTPS**: abilita redirect HTTPS se disponibile

#### Account Amministratore
- **Username**: username per il primo admin
- **Password**: almeno 8 caratteri
- **Nome Completo**: nome dell'amministratore
- **Email**: email valida

3. **Completa l'installazione**
   - Clicca su "Installa AssoLife"
   - L'installer creerà le tabelle e configurerà il sistema
   - Al termine, accedi con le credenziali amministratore

## Struttura del Progetto

```
/
├── public/                     # File pubblici
│   ├── index.php              # Dashboard
│   ├── install.php            # Installer
│   ├── login.php              # Login
│   ├── logout.php             # Logout
│   ├── members.php            # Lista soci
│   ├── member_edit.php        # Modifica/nuovo socio
│   ├── users.php              # Gestione utenti (admin)
│   ├── years.php              # Anni sociali (admin)
│   ├── categories.php         # Categorie (admin)
│   ├── finance.php            # Movimenti finanziari
│   ├── reports.php            # Rendiconto
│   ├── import_members.php     # Import soci
│   ├── import_movements.php   # Import movimenti
│   ├── export_excel.php       # Export Excel
│   └── inc/
│       ├── header.php         # Header comune
│       └── footer.php         # Footer comune
├── src/                       # File sorgente
│   ├── config.php            # Configurazione (generato)
│   ├── db.php                # Connessione database
│   ├── auth.php              # Autenticazione
│   └── functions.php         # Funzioni utility
├── schema.sql                # Schema database
├── .htaccess                 # Configurazione Apache
├── .gitignore                # File da ignorare
└── README.md                 # Questo file
```

## Configurazione

Il file `src/config.php` viene generato automaticamente dall'installer e contiene:

```php
<?php
return [
    'db' => [
        'host'     => 'localhost',
        'dbname'   => 'nome_db',
        'username' => 'utente',
        'password' => 'password',
        'charset'  => 'utf8mb4',
        'prefix'   => 'assolife_',  // Prefisso tabelle
    ],
    'app' => [
        'name'         => 'Nome Associazione',
        'version'      => '1.0.0',
        'base_path'    => '/public/',
        'force_https'  => false,
        'session_name' => 'assolife_session',
        'timezone'     => 'Europe/Rome',
    ],
];
```

## Utilizzo

### Primo Accesso
1. Accedi con le credenziali amministratore create durante l'installazione
2. Configura gli anni sociali in **Impostazioni → Anni Sociali**
3. Verifica le categorie predefinite in **Impostazioni → Categorie**
4. Inizia ad aggiungere soci dalla sezione **Soci**

### Gestione Quotidiana
- **Dashboard**: panoramica con statistiche principali
- **Soci**: gestione anagrafica membri
- **Movimenti**: registrazione entrate e uscite
- **Rendiconto**: consultazione bilanci per anno sociale

### Import CSV

#### Import Soci
Formato CSV (con intestazione):
```
Tessera;Nome;Cognome;CF;DataNascita;LuogoNascita;Email;Telefono;Indirizzo;Città;Provincia;CAP;DataIscrizione;Stato
001;Mario;Rossi;RSSMRA80A01H501U;1980-01-01;Roma;mario@example.com;1234567890;Via Roma 1;Roma;RM;00100;2024-01-01;attivo
```

#### Import Movimenti
Formato CSV Entrate:
```
AnnoSocialeID;CategoriaID;CFFiscaleSocio;Importo;Metodo;Ricevuta;Data;Note
1;1;RSSMRA80A01H501U;100.00;Bonifico;RIC001;2024-01-15;Quota associativa
```

Formato CSV Uscite:
```
AnnoSocialeID;CategoriaID;Importo;Metodo;Ricevuta;Data;Note
1;1;50.00;Bonifico;PAG001;2024-01-20;Affitto sede
```

## Compatibilità AlterVista

AssoLife è progettato per essere compatibile con hosting gratuiti come AlterVista:

- ✅ HTTPS opzionale (funziona anche senza certificato SSL)
- ✅ Nessuna dipendenza da Composer
- ✅ PDO MySQL standard
- ✅ Supporto installazione in sottocartelle
- ✅ Gestione path relativa per sessioni e cookie

## Sicurezza

- Password hashate con bcrypt
- Protezione CSRF su tutti i form
- Prepared statements per tutte le query
- Validazione input lato server
- Sanitizzazione output con `htmlspecialchars()`
- File `.htaccess` con regole di sicurezza
- File di configurazione protetto da accesso web

## Supporto e Contributi

**AssoLife** è sviluppato da **Luigi Pistarà**

Per segnalare problemi o richiedere funzionalità, contatta l'autore.

## Licenza

Sistema sviluppato per la gestione di associazioni.
Tutti i diritti riservati a Luigi Pistarà.

---

**Powered with AssoLife by Luigi Pistarà**

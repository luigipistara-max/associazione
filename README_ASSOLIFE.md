# AssoLife - Sistema di Gestione Associativa

Sistema completo per la gestione di associazioni, con supporto per soci, movimenti finanziari, rendiconti e molto altro.

## 🌟 Caratteristiche Principali

### Sistema Core
- ✅ **Prefisso Tabelle** - Supporto completo per prefissi database tramite funzione `table()`
- ✅ **Autenticazione Sicura** - Sistema di login con protezione CSRF
- ✅ **Ruoli Utente** - Amministratore e Operatore
- ✅ **Sessioni Personalizzate** - Nome sessione configurabile
- ✅ **Validazione Codice Fiscale** - Algoritmo completo con check digit per CF italiano

### Funzionalità
- 📊 **Dashboard** - Statistiche in tempo reale su soci e movimenti
- 👥 **Gestione Soci** - Anagrafica completa con validazione CF
- 💰 **Movimenti Finanziari** - Entrate e uscite separate
- 📈 **Rendiconto** - Report per anno sociale con grafici
- 👤 **Gestione Utenti** - CRUD completo per admin
- 📅 **Anni Sociali** - Gestione periodi contabili
- 🏷️ **Categorie** - Categorie entrate/uscite personalizzabili
- 📥 **Import/Export** - CSV per soci e movimenti, Excel export

### Design & UX
- 🎨 **Design Moderno** - Bootstrap 5.3.3
- 💜 **Login Gradiente** - Design viola/blu professionale
- 📱 **Responsive** - Funziona su tutti i dispositivi
- 🎯 **Branding** - Footer con "Powered with **AssoLife** by Luigi Pistarà"

### Sicurezza
- 🔒 **CSRF Protection** - Token su tutti i form
- 🛡️ **.htaccess** - Protezione file sensibili
- 🔐 **Password Hash** - Bcrypt per le password
- 🚫 **SQL Injection** - Prepared statements ovunque

## 📦 Installazione

### Requisiti
- PHP 7.4 o superiore
- MySQL 5.7+ o MariaDB 10.2+
- Apache con mod_rewrite (opzionale)

### Installazione in 3 Step

1. **Carica i file** sul tuo server
2. **Naviga su** `http://tuosito.it/public/install.php`
3. **Segui il wizard**:
   - Step 1: Configura database (host, nome, user, password, prefisso tabelle)
   - Step 2: Configura sito (nome, percorso base, HTTPS)
   - Step 3: Crea account amministratore

### Compatibilità AlterVista
Il sistema è completamente compatibile con AlterVista:
- ✅ Nessuna dipendenza Composer
- ✅ HTTPS opzionale
- ✅ Funziona con database condivisi (usa prefisso tabelle)

## 🗃️ Struttura Database

### 7 Tabelle Principali

1. **users** - Utenti del sistema (admin/operatore)
2. **members** - Soci dell'associazione
3. **social_years** - Anni sociali
4. **income_categories** - Categorie entrate
5. **expense_categories** - Categorie uscite
6. **income** - Movimenti di entrata
7. **expenses** - Movimenti di uscita

Tutte le tabelle supportano il prefisso configurabile durante l'installazione.

## 🛠️ Configurazione

### File di Configurazione

**src/config.php** - Configurazione base (con fallback)
**src/config.generated.php** - Generato dall'installer (NON commitare!)

### Configurazione Database
```php
'db' => [
    'host'     => 'localhost',
    'dbname'   => 'associazione',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
    'prefix'   => '',  // Prefisso tabelle (es: 'asso_')
]
```

### Configurazione Applicazione
```php
'app' => [
    'name'         => 'Associazione',      // Nome visualizzato
    'version'      => '1.0.0',
    'base_path'    => '/public/',          // Percorso base
    'force_https'  => false,               // Forza HTTPS
    'session_name' => 'assolife_session',
    'timezone'     => 'Europe/Rome',
]
```

## 📚 Funzioni Principali

### Autenticazione (src/auth.php)
- `isLoggedIn()` - Verifica se utente è loggato
- `isAdmin()` - Verifica se utente è admin
- `requireLogin()` - Richiede login o redirect
- `requireAdmin()` - Richiede permessi admin
- `generateCsrfToken()` - Genera token CSRF
- `verifyCsrfToken($token)` - Verifica token CSRF
- `csrfField()` - Output campo hidden CSRF
- `checkCsrf()` - Verifica CSRF da POST

### Utility (src/functions.php)
- `h($string)` - Sanitize output HTML
- `validateFiscalCode($cf)` - Valida CF italiano completo
- `formatDate($date)` - Formatta data IT (dd/mm/yyyy)
- `formatCurrency($amount)` - Formatta importo (1.234,56 €)
- `setFlash($msg, $type)` - Imposta messaggio flash
- `displayFlash()` - Mostra messaggio flash
- `parseCsvFile($path)` - Parse file CSV
- `exportCsv($filename, $data)` - Esporta CSV

### Database (src/db.php)
- `table($name)` - Restituisce nome tabella con prefisso
- `$pdo` - Connessione PDO globale

## 🎨 Personalizzazione

### Cambiare Nome Sito
Modifica `src/config.generated.php` dopo installazione o reinstalla.

### Aggiungere Categorie
Admin → Categorie → Aggiungi nuove categorie entrate/uscite

### Personalizzare Branding
Il footer mostra sempre "Powered with **AssoLife** by Luigi Pistarà"
Per modificare: `public/inc/footer.php`

## 🔐 Sicurezza

### File Protetti
- `.htaccess` blocca accesso a:
  - `src/config.php`
  - `src/config.generated.php`
  - `src/config_local.php`
  - `schema.sql`
  - File `.git`

### Best Practices
1. Elimina `public/install.php` dopo l'installazione
2. Usa password forti (min 8 caratteri)
3. Abilita HTTPS in produzione
4. Backup regolari del database
5. Non committare `config.generated.php`

## 📖 Utilizzo

### Primo Accesso
1. Completa l'installazione
2. Login con credenziali admin create
3. Configura anno sociale corrente
4. Aggiungi categorie personalizzate (opzionale)
5. Inizia ad aggiungere soci

### Workflow Tipico
1. **Gestione Soci** - Aggiungi/modifica soci
2. **Movimenti** - Registra entrate/uscite
3. **Rendiconto** - Visualizza report per anno sociale
4. **Export** - Esporta dati per contabilità

## 🐛 Troubleshooting

### Errore Connessione Database
- Verifica credenziali in `src/config.generated.php`
- Controlla che il database esista
- Verifica permessi utente database

### Login Non Funziona
- Controlla che le sessioni PHP funzionino
- Verifica `session_name` in config
- Pulisci cookie del browser

### Tabelle Non Trovate
- Verifica che l'installer abbia completato
- Controlla prefisso tabelle in `config.generated.php`
- Verifica che tutte le 7 tabelle esistano

## 📄 Licenza

Sistema sviluppato da **Luigi Pistarà**

## 🤝 Supporto

Per assistenza o segnalazioni:
- Apri una issue su GitHub
- Contatta lo sviluppatore

---

**Powered with ❤️ by Luigi Pistarà**

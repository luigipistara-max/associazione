# AssoLife - Sistema Completo Gestione Associazione

**AssoLife** è un sistema completo e moderno per la gestione di associazioni, sviluppato da **Luigi Pistarà**.

---

## 🌟 Caratteristiche Principali

### 🔐 Autenticazione e Sicurezza
- Sistema di login sicuro con password hashing (bcrypt)
- Due ruoli utente: **Admin** (accesso completo) e **Operatore** (gestione operativa)
- Protezione CSRF, XSS, SQL Injection
- Sessioni sicure con configurazione avanzata (httponly, samesite)
- Password reset sicuro con token temporanei
- Timeout sessione configurabile

### 👥 Gestione Soci
- Anagrafica completa con validazione codice fiscale italiano
- Stati: Attivo, Sospeso, Cessato
- Numero tessera univoco
- Import massivo da CSV
- Generazione tesserino digitale con QR code
- Tracciamento stato account portale
- Export soci attivi

### 🎫 Portale Soci (Sistema Completo)
Il portale soci è un'area dedicata dove i membri possono gestire autonomamente il proprio profilo e interagire con l'associazione.

#### **Autenticazione Portale**
- **Registrazione dedicata** tramite link di attivazione inviato dall'admin
- **Login separato** con email e password
- **Recupero password** tramite email con token sicuro
- **Reset password** con link temporaneo
- Sessione separata dall'area admin

#### **Profilo Personale**
- **Visualizzazione** dati anagrafici completi
- **Modifica** email, telefono, indirizzo
- **Cambio password** autonomo
- Visualizzazione stato socio e numero tessera

#### **Fototessera**
- **Upload foto** tramite integrazione ImgBB
- Validazione tipo file (JPG, PNG, max 2MB)
- **Rimozione foto** quando necessario
- Foto visualizzata nel tesserino digitale

#### **Tesserino Digitale**
- **Generazione automatica** tesserino con dati socio
- **QR Code** unico per verifica
- Include foto, nome, numero tessera, anno sociale
- **Stampabile** in formato digitale
- **Verifica pubblica** tramite scansione QR code

#### **Eventi**
- **Visualizzazione eventi** disponibili (pubblici + gruppi di appartenenza)
- **Dare disponibilità**: Sì / Forse / No
- **Modifica disponibilità** in qualsiasi momento
- Visualizzazione **link online** (Zoom, Meet, ecc.) solo per iscritti
- **Contatori in tempo reale** (partecipanti, posti rimanenti)
- Badge gruppo per eventi riservati

#### **Gruppi**
- **Visualizzazione gruppi** di appartenenza
- Lista **gruppi pubblici** disponibili
- **Richiesta partecipazione** a gruppi non ristretti
- Tracciamento **stato richieste** (in attesa, approvata, rifiutata)
- Gruppi nascosti non visibili nel portale
- Gruppi ristretti visibili ma non richiedibili

#### **Pagamenti**
- **Visualizzazione quote** da pagare, pagate, in attesa conferma
- **Pagamento online** tramite PayPal Smart Buttons integrati
- **Pagamento offline** tramite bonifico con dichiarazione
- Visualizzazione coordinate bancarie per bonifico
- Tracciamento **stato pagamenti** (pending, paid, pending_confirmation)
- **Ricevute automatiche** per pagamenti confermati

#### **Ricevute**
- Lista **tutte le ricevute** disponibili
- **Download PDF** ricevute con token sicuro
- Ricevute generate automaticamente per pagamenti online
- Ricevute generate dall'admin per pagamenti offline confermati

### 👥 Gruppi Soci
Sistema completo di gestione gruppi con controlli avanzati.

- **Creazione gruppi** con nome, descrizione, colore personalizzato
- **Assegnazione soci** ai gruppi
- **Flag avanzati**:
  - `is_hidden`: Gruppo nascosto dal portale soci
  - `is_restricted`: Gruppo non richiedibile (solo admin può aggiungere)
- **Richieste partecipazione** dai soci con flusso di approvazione
- **Gestione richieste**: approvazione o rifiuto da parte admin
- **Visualizzazione membri** di ogni gruppo
- **Aggiunta manuale** membri da parte admin
- **Rimozione membri** da gruppo
- Utilizzo gruppi come **target eventi** (visibilità selettiva)

### 📅 Eventi
Sistema completo di gestione eventi con modalità multiple.

- **Creazione eventi** con dettagli completi
- **Modalità evento**:
  - 🏢 **Di Persona**: con indirizzo e luogo fisico
  - 💻 **Online**: con piattaforma e link videochiamata
  - 🔄 **Ibrido**: combinazione presenza fisica e online
- **Stati evento**: Bozza, Pubblicato, Annullato, Completato
- **Target flessibile**:
  - Tutti i soci
  - Gruppi specifici (uno o più gruppi)
- **Gestione iscrizioni**:
  - Raccolta disponibilità (Sì / Forse / No)
  - Limite massimo partecipanti (opzionale)
  - Scadenza iscrizioni
  - Costo partecipazione (opzionale)
- **Link online** visibili solo agli iscritti confermati
- **Contatori real-time** partecipanti
- **Report iscrizioni** con export
- **Rimozione iscrizioni** da parte admin

### 💰 Gestione Finanziaria
Sistema completo di contabilità associativa.

- **Quote associative**:
  - Creazione quote singole per socio
  - Rinnovo massivo per anno sociale
  - Scadenze e stati (pending, paid, overdue)
  - Collegamento a anni sociali
- **Entrate e uscite**:
  - Registrazione movimenti con categorie
  - Collegamento entrate a soci (opzionale)
  - Metodi di pagamento multipli
  - Numeri ricevuta/fattura
- **Categorie personalizzabili**:
  - Categorie entrate (quote, donazioni, eventi, ecc.)
  - Categorie uscite (affitto, utenze, materiali, ecc.)
  - Ordinamento e attivazione/disattivazione
- **Anni sociali**:
  - Gestione periodi contabili
  - Impostazione anno corrente
  - Filtri automatici per anno
- **Report economico dettagliato**:
  - Totali per categoria con percentuali
  - Calcolo saldo/risultato d'esercizio
  - Grafici a barre visuali
  - Export Excel/CSV

### 💳 Pagamenti Online e Offline
Doppio sistema di pagamento per massima flessibilità.

#### **Pagamento Bonifico Bancario**
- Socio visualizza coordinate bancarie
- Socio dichiara di aver effettuato bonifico
- Admin riceve notifica (opzionale)
- Admin conferma pagamento da pannello dedicato
- Generazione ricevuta automatica

#### **Pagamento PayPal**
- Integrazione **PayPal Smart Buttons**
- Pagamento diretto dal portale socio
- Conferma automatica via webhook
- Transaction ID tracciato
- Ricevuta generata automaticamente
- Configurazione sandbox/production

#### **Gestione Ricevute**
- Generazione **PDF automatica** con TCPDF
- Numerazione progressiva
- Token sicuro per accesso
- Download da portale socio e admin
- Dati completi: associazione, socio, importo, data

### 📧 Sistema Email
Gestione completa comunicazioni email.

- **Invio email singole** a soci specifici
- **Email massive** con selezione destinatari
- **Coda email** (compatibile limiti AlterVista)
- **Template personalizzabili**:
  - Attivazione portale
  - Reset password
  - Conferma pagamento
  - Eventi
  - Notifiche generiche
- **Tracking invii**:
  - Log email inviati
  - Batch email massive
  - Stati invio
- **Notifiche automatiche** (configurabili):
  - Nuova quota da pagare
  - Pagamento confermato
  - Richiesta gruppo approvata/rifiutata
  - Nuovo evento pubblicato
- **Configurazione SMTP** o mail() nativa
- **Personalizzazione firma** e footer email

### 📊 Report e Export
Strumenti di analisi e export dati.

- **Dashboard** con statistiche principali:
  - Totale soci (attivi, sospesi, cessati)
  - Quote incassate vs attese
  - Eventi prossimi
  - Saldo economico anno corrente
- **Report economico/finanziario**:
  - Per anno sociale
  - Dettaglio categorie entrate/uscite
  - Percentuali su totale
  - Grafici visuali
- **Export dati**:
  - Soci in CSV
  - Soci attivi per email massive
  - Movimenti finanziari in Excel
  - Lista morosi
  - Lista iscritti evento
- **Audit log** operazioni (opzionale):
  - Tracciamento modifiche
  - Operazioni sensibili
  - User e timestamp

### 🔍 Verifica Tessera Pubblica
Sistema di verifica tessere per eventi e controlli.

- **Scansione QR code** da tesserino
- **Verifica pubblica** (no login richiesto)
- Visualizzazione dati socio:
  - Nome e cognome
  - Numero tessera
  - Stato (Attivo/Sospeso/Cessato)
  - Validità tessera
- Token univoco non indovinabile
- Utilizzo per controllo accessi eventi

---

## 📁 Struttura del Progetto

```
/
├── public/                      # File pubblici
│   ├── index.php               # Dashboard admin
│   ├── install.php             # Installer
│   ├── login.php               # Login admin
│   ├── logout.php              # Logout
│   ├── forgot_password.php     # Recupero password admin
│   ├── reset_password.php      # Reset password admin
│   │
│   ├── members.php             # Lista soci
│   ├── member_edit.php         # Modifica/nuovo socio
│   ├── member_fees.php         # Quote socio
│   ├── member_card.php         # Tesserino socio (admin view)
│   │
│   ├── member_groups.php       # Gestione gruppi
│   ├── member_group_members.php # Membri di un gruppo (NUOVO)
│   ├── group_requests.php      # Richieste partecipazione gruppi
│   │
│   ├── events.php              # Lista eventi
│   ├── event_edit.php          # Modifica/nuovo evento
│   ├── event_view.php          # Dettaglio evento e iscrizioni
│   ├── event_registrations.php # (Deprecato, usa event_view.php)
│   │
│   ├── bulk_fees.php           # Rinnovo massivo quote
│   ├── finance.php             # Movimenti finanziari
│   ├── reports.php             # Rendiconto economico
│   ├── payment_confirm.php     # Conferma pagamenti offline
│   ├── receipt.php             # Visualizza/scarica ricevuta
│   │
│   ├── mass_email.php          # Email massive
│   ├── admin_email_templates.php # Template email
│   │
│   ├── users.php               # Gestione utenti (admin only)
│   ├── years.php               # Anni sociali (admin only)
│   ├── categories.php          # Categorie (admin only)
│   ├── settings.php            # Impostazioni (admin only)
│   ├── audit_log.php           # Log operazioni (admin only)
│   │
│   ├── import_members.php      # Import soci da CSV
│   ├── import_movements.php    # Import movimenti da CSV
│   ├── export_excel.php        # Export rendiconto Excel
│   ├── export_active_members.php # Export soci attivi
│   │
│   ├── verify_member.php       # Verifica tessera (pubblico)
│   ├── portal_check.php        # Verifica configurazione portale
│   │
│   ├── portal/                 # PORTALE SOCI
│   │   ├── login.php           # Login soci
│   │   ├── logout.php          # Logout soci
│   │   ├── register.php        # Attivazione account
│   │   ├── forgot_password.php # Recupero password soci
│   │   ├── reset_password.php  # Reset password soci
│   │   │
│   │   ├── index.php           # Dashboard socio
│   │   ├── profile.php         # Profilo e modifica dati
│   │   ├── photo.php           # Upload/rimozione fototessera
│   │   ├── card.php            # Tesserino digitale
│   │   │
│   │   ├── events.php          # Eventi e disponibilità
│   │   ├── groups.php          # Gruppi e richieste
│   │   ├── payments.php        # Quote e pagamenti
│   │   ├── receipts.php        # Ricevute
│   │   │
│   │   ├── api/                # API endpoints portale
│   │   │   └── paypal_confirm.php # Conferma pagamento PayPal
│   │   │
│   │   └── inc/                # Include portale
│   │       ├── auth.php        # Autenticazione soci
│   │       ├── header.php      # Header portale
│   │       └── footer.php      # Footer portale
│   │
│   ├── api/                    # API generali
│   │   ├── paypal_webhook.php  # Webhook PayPal
│   │   ├── dashboard_stats.php # Statistiche dashboard
│   │   └── count_email_recipients.php # Conteggio destinatari email
│   │
│   ├── inc/                    # Include comuni admin
│   │   ├── header.php          # Header admin
│   │   └── footer.php          # Footer admin
│   │
│   └── uploads/                # Upload files (logo, ecc.)
│       ├── .htaccess           # Protezione directory
│       └── .gitkeep
│
├── src/                        # Sorgenti PHP (protetti da .htaccess)
│   ├── config.php              # Configurazione (generato da installer)
│   ├── db.php                  # Connessione database
│   ├── auth.php                # Autenticazione admin
│   ├── functions.php           # Funzioni utility
│   ├── email.php               # Gestione email
│   ├── pdf.php                 # Generazione PDF (TCPDF)
│   ├── audit.php               # Audit log
│   └── .htaccess               # Blocca accesso HTTP diretto
│
├── migrations/                 # Migrazioni database
│   ├── 001_portal_soci_parte_1.sql
│   ├── 002_portal_soci_parte_2.sql
│   ├── 003_events_enhanced.sql
│   ├── 004_member_groups.sql
│   └── 005_payments_and_receipts.sql
│
├── schema.sql                  # Schema database completo
├── .htaccess                   # Configurazione Apache root
├── .gitignore                  # File da ignorare in Git
├── index.php                   # Redirect a /public/
│
├── README.md                   # Questa documentazione
├── TESTING.md                  # Test plan completo
└── SECURITY.md                 # Analisi di sicurezza
```

---

## 🚀 Installazione

### Requisiti
- **PHP** 7.4 o superiore
- **MySQL** 5.7+ / MariaDB 10.2+
- **Server web** Apache o Nginx
- **Estensioni PHP**:
  - PDO (PDO_MYSQL)
  - cURL (per API esterne)
  - mbstring
  - GD o Imagick (opzionale, per manipolazione immagini)

### Procedura di Installazione

#### 1. Carica i File
Carica tutti i file nella directory del tuo hosting (es: `/htdocs/`, `/public_html/`)

#### 2. Crea il Database
Crea un database MySQL/MariaDB vuoto tramite il pannello di controllo del tuo hosting.

#### 3. Avvia l'Installer
Naviga su: `http://tuosito.com/public/install.php`

#### 4. Compila il Form di Installazione

**Step 1 - Configurazione Database**:
- **Host**: `localhost` (o l'host fornito dal tuo hosting)
- **Nome Database**: nome del database creato
- **Utente**: utente MySQL
- **Password**: password MySQL
- **Prefisso Tabelle**: opzionale (es: `assolife_`) - utile se condividi il database

**Step 2 - Configurazione Sito**:
- **Nome Associazione**: nome completo della tua associazione
- **Path di Installazione**: rilevato automaticamente, modificabile se necessario
- **Forza HTTPS**: abilita redirect HTTPS se hai un certificato SSL

**Step 3 - Account Amministratore**:
- **Username**: username per il primo admin
- **Password**: almeno 8 caratteri (usa password sicura!)
- **Nome Completo**: nome dell'amministratore
- **Email**: email valida (per recupero password)

#### 5. Completa l'Installazione
- Clicca "**Installa AssoLife**"
- L'installer creerà le tabelle e configurerà il sistema
- Al termine, verrai reindirizzato al login

#### 6. Primo Accesso
- Accedi con le credenziali admin create
- Vai in **Impostazioni** per configurare:
  - Dati associazione
  - Coordinate bancarie
  - API keys (ImgBB, PayPal)
  - Template email

---

## ⚙️ Configurazione

### Impostazioni Generali

Accedi a `/public/settings.php` (solo admin) per configurare:

#### **Dati Associazione**
- Nome associazione
- Nome completo
- Slogan
- Logo (upload immagine)

#### **Legale Rappresentante**
- Nome e ruolo
- Codice fiscale

#### **Indirizzo e Contatti**
- Sede: via, CAP, città, provincia
- Telefono, email, PEC
- Sito web

#### **Dati Fiscali**
- Partita IVA
- Codice fiscale
- REA (registro imprese)
- Iscrizione registri

#### **Coordinate Bancarie**
- IBAN
- Intestatario
- Banca
- BIC/SWIFT

#### **PayPal**
- Email PayPal
- Link PayPal.me (opzionale)

---

### Impostazioni API

#### **ImgBB** (Upload Fototessere)

1. Registrati su [ImgBB](https://imgbb.com/)
2. Genera una API key
3. Inserisci la chiave in **Impostazioni → API → ImgBB API Key**

**Perché ImgBB?**
- Upload esterno, nessun file sul server
- Sicuro contro upload malware
- Gratuito (fino a 32 MB)

#### **PayPal** (Pagamenti Online)

1. Crea un account business PayPal
2. Vai su [PayPal Developer](https://developer.paypal.com/)
3. Crea un'app per ottenere:
   - Client ID
   - Client Secret
4. Configura in **Impostazioni → API → PayPal**:
   - Modalità: Sandbox (test) o Production (live)
   - Client ID
   - Client Secret
   - Webhook ID (opzionale, per conferme automatiche)

**Configurazione Webhook** (opzionale):
- URL webhook: `https://tuosito.com/public/api/paypal_webhook.php`
- Eventi da ascoltare: `PAYMENT.CAPTURE.COMPLETED`

---

### Impostazioni Email

#### **SMTP** (Raccomandato)
```php
// In settings.php o configurazione email
'smtp_host' => 'smtp.example.com',
'smtp_port' => 587,
'smtp_user' => 'noreply@tuaassociazione.it',
'smtp_pass' => 'password',
'smtp_secure' => 'tls', // 'tls' o 'ssl'
```

#### **mail() Nativa**
Usa la funzione `mail()` di PHP (default). Funziona su molti hosting ma può finire in spam.

#### **Template Email**
Personalizza i template email da **Email → Template**:
- Attivazione portale soci
- Reset password
- Conferma pagamento
- Notifica evento
- Email generica

**Variabili disponibili**:
- `{member_name}` - Nome socio
- `{member_email}` - Email socio
- `{activation_link}` - Link attivazione
- `{event_title}` - Titolo evento
- `{amount}` - Importo
- ecc.

---

## 📱 Portale Soci - Guida Utilizzo

### Per Admin: Attivare un Socio

1. Vai su **Soci** → Seleziona socio
2. Clicca "**Invia Email Attivazione Portale**"
3. Il socio riceverà una email con link di attivazione (valido 24h)

### Per Socio: Primo Accesso

1. Ricevi email di attivazione
2. Clicca sul link nella email
3. Imposta la tua password (min 8 caratteri)
4. Clicca "Attiva Account"
5. Accedi su `/public/portal/login.php`

### Funzionalità Portale Soci

#### **Dashboard**
Panoramica personale con:
- Stato socio e tessera
- Prossimi eventi
- Quote da pagare
- Gruppi di appartenenza

#### **Profilo**
- Visualizza tutti i tuoi dati
- Modifica: email, telefono, indirizzo
- Cambia password

#### **Fototessera**
- Carica una tua foto (max 2MB, JPG/PNG)
- Appare nel tesserino digitale
- Rimuovi/sostituisci quando vuoi

#### **Tesserino Digitale**
- Mostra il tuo tesserino con foto e QR code
- Stampabile o salvabile come immagine
- Usa il QR per verificare la tessera agli eventi

#### **Eventi**
- Vedi eventi aperti a te (pubblici + tuoi gruppi)
- Dai disponibilità: Sì / Forse / No
- Modifica disponibilità fino alla scadenza iscrizioni
- Se evento online e sei iscritto: vedi link Zoom/Meet

#### **Gruppi**
- Vedi i tuoi gruppi attuali
- Richiedi partecipazione a gruppi pubblici
- Monitora lo stato delle richieste

#### **Pagamenti**
- Vedi quote da pagare con scadenze
- **Paga online** con PayPal (istantaneo)
- **Paga offline** con bonifico (dichiari l'avvenuto pagamento)
- Visualizza quote in attesa conferma admin

#### **Ricevute**
- Scarica tutte le tue ricevute in PDF
- Ricevute generate automaticamente per pagamenti confermati

---

## 🔒 Sicurezza

AssoLife implementa le migliori pratiche di sicurezza:

### Autenticazione
- ✅ Password hashate con **bcrypt** (PASSWORD_DEFAULT)
- ✅ Sessioni sicure (httponly, samesite strict)
- ✅ Timeout sessione configurabile
- ✅ Protezione contro **session fixation**
- ✅ Reset password con token temporanei

### Protezione Dati
- ✅ **CSRF** protection su tutti i form POST
- ✅ **SQL Injection** prevenuta con prepared statements
- ✅ **XSS** prevenuta con sanitizzazione output (`htmlspecialchars`)
- ✅ Controllo accessi per ruolo (admin/operatore)
- ✅ Verifica appartenenza per risorse socio

### File e Upload
- ✅ Validazione MIME type e dimensione file
- ✅ Upload fototessere su **ImgBB** (esterno, nessun file sul server)
- ✅ Nessun rischio di esecuzione script malevoli
- ✅ File `.htaccess` protegge directory sensibili

### Configurazione Server
- ✅ File config protetti da accesso HTTP diretto
- ✅ Directory `src/` non accessibile via web
- ✅ HTTPS opzionale (redirect configurabile)
- ✅ Supporto hosting condivisi (AlterVista, Aruba, ecc.)

### Token e Sicurezza Risorse
- ✅ Token sicuri per ricevute (64 caratteri, random_bytes)
- ✅ Token attivazione/reset con scadenza
- ✅ QR code tessere con token univoco

### Raccomandazioni Aggiuntive
Per maggiore sicurezza, considera:
- 🔹 Rate limiting su login (previene brute force)
- 🔹 Logging tentativi falliti
- 🔹 2FA per account admin
- 🔹 Audit log completo per operazioni sensibili
- 🔹 Backup automatici database

Vedi **SECURITY.md** per analisi completa.

---

## 📖 Guide d'Uso

### Gestione Soci

#### Aggiungere un Socio
1. **Soci** → **Nuovo Socio**
2. Compila:
   - Nome, cognome, codice fiscale
   - Data e luogo di nascita
   - Contatti (email, telefono)
   - Indirizzo completo
3. Assegna numero tessera (o lascia auto-generare)
4. Imposta stato: Attivo
5. Salva

#### Import Massivo da CSV
1. **Soci** → **Import CSV**
2. Prepara file CSV con colonne:
   ```
   Tessera;Nome;Cognome;CF;DataNascita;LuogoNascita;Email;Telefono;Indirizzo;Città;Provincia;CAP;DataIscrizione;Stato
   ```
3. Carica file
4. Verifica anteprima
5. Conferma import

#### Attivare Portale per Socio
1. Vai su scheda socio
2. Clicca "**Invia Email Attivazione Portale**"
3. Il socio riceverà email con link
4. Il socio completerà l'attivazione

---

### Gestione Quote

#### Creare Quote Singole
1. **Soci** → Seleziona socio → **Quote**
2. Clicca "**Nuova Quota**"
3. Seleziona anno sociale
4. Inserisci importo e scadenza
5. Salva

#### Rinnovo Massivo
1. **Quote** → **Rinnovo Massivo**
2. Seleziona anno sociale
3. Imposta importo standard
4. Seleziona soci (tutti attivi / filtrati)
5. Clicca "**Crea Quote**"
6. Quote create per tutti i soci selezionati

#### Confermare Pagamento Offline
1. **Pagamenti** → **Conferma Pagamenti**
2. Vedi lista pagamenti dichiarati dai soci (bonifico)
3. Verifica dettagli bonifico
4. Clicca "**Conferma**"
5. Ricevuta generata automaticamente

---

### Gestione Eventi

#### Creare un Evento
1. **Eventi** → **Nuovo Evento**
2. Inserisci titolo e descrizione
3. Imposta data/ora inizio e fine
4. Scegli modalità:
   - **Di Persona**: inserisci luogo e indirizzo
   - **Online**: inserisci piattaforma (Zoom, Meet, ecc.) e link
   - **Ibrido**: inserisci entrambi
5. Imposta target:
   - Tutti i soci
   - Gruppi specifici (seleziona gruppi)
6. Imposta limite partecipanti (opzionale)
7. Salva come **Bozza** o **Pubblica** subito

#### Gestire Iscrizioni
1. **Eventi** → Seleziona evento → **Iscritti**
2. Vedi lista per stato (Sì / Forse / No)
3. Rimuovi iscrizioni se necessario
4. Export lista partecipanti

---

### Gestione Gruppi

#### Creare un Gruppo
1. **Gruppi** → **Nuovo Gruppo**
2. Inserisci nome e descrizione
3. Scegli colore badge
4. Imposta flag:
   - **Nascosto**: gruppo non visibile nel portale soci
   - **Ristretto**: gruppo non richiedibile, solo admin può aggiungere
5. Salva

#### Gestire Richieste Partecipazione
1. **Gruppi** → **Richieste**
2. Vedi richieste pendenti
3. **Approva** o **Rifiuta** con motivazione
4. Socio riceve notifica (opzionale)

#### Aggiungere Membri Manualmente
1. **Gruppi** → Seleziona gruppo → **Membri**
2. Clicca "**Aggiungi Membro**"
3. Seleziona socio dal dropdown
4. Conferma

---

### Email Massive

1. **Comunicazioni** → **Email Massive**
2. Seleziona destinatari:
   - Tutti i soci attivi
   - Filtrati per gruppo
   - Filtrati per stato
3. Scrivi oggetto e corpo email
4. Usa variabili personalizzate: `{member_name}`, ecc.
5. Clicca "**Invia**"
6. Email accodate e inviate progressivamente (compatibile limiti hosting)

---

## 🛠️ Manutenzione e Backup

### Backup Database
Esegui backup regolari del database MySQL:

**Via phpMyAdmin**:
1. Accedi a phpMyAdmin
2. Seleziona database
3. **Export** → SQL → **Esegui**

**Via command line**:
```bash
mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
```

### Backup Files
Scarica via FTP:
- `/src/config.php` (configurazione)
- `/public/uploads/` (logo e file caricati)
- Opzionale: tutto il progetto

### Aggiornamenti
Per aggiornare AssoLife:
1. **Backup** completo database e file
2. Scarica nuova versione
3. Sovrascrivi file (mantieni `/src/config.php`)
4. Esegui eventuali migration SQL in `/migrations/`
5. Verifica funzionamento

---

## 🧪 Testing

AssoLife include un test plan completo in **TESTING.md** che copre:

- ✅ Autenticazione admin e soci
- ✅ Gestione soci e anagrafica
- ✅ Portale soci (profilo, eventi, gruppi, pagamenti)
- ✅ Sistema quote e pagamenti
- ✅ Gestione eventi e iscrizioni
- ✅ Gestione gruppi e richieste
- ✅ Sistema finanziario
- ✅ Email e notifiche

Vedi **TESTING.md** per test case dettagliati.

---

## 📄 Licenza e Crediti

**AssoLife** è sviluppato da **Luigi Pistarà**

Tutti i diritti riservati.

### Librerie Utilizzate
- **Bootstrap** 5.3.3 (MIT License)
- **TCPDF** (LGPL) - Generazione PDF
- **Chart.js** (MIT License) - Grafici
- **PayPal SDK** - Pagamenti online

---

## 📞 Supporto

Per supporto, segnalazione bug o richieste di funzionalità, contatta:

**Luigi Pistarà**  
Sviluppatore AssoLife

---

## 🔄 Changelog

### v1.0.0 - Sistema Completo
- ✅ Sistema base: soci, finanza, report
- ✅ Portale soci completo
- ✅ Eventi con modalità multiple
- ✅ Gruppi con flag avanzati
- ✅ Pagamenti online (PayPal) e offline (bonifico)
- ✅ Ricevute PDF automatiche
- ✅ Email massive con coda
- ✅ Tesserino digitale con QR code
- ✅ Verifica tessera pubblica
- ✅ Documentazione completa (README, TESTING, SECURITY)

---

**Powered with ❤️ by Luigi Pistarà**

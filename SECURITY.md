# AssoLife - Analisi di Sicurezza

Documento di security review per il sistema AssoLife.  
Analisi delle misure di sicurezza implementate e raccomandazioni.

---

## 1. Autenticazione e Sessioni

### 1.1 Password Hashing ✅
**Implementazione**: Le password sono hashate utilizzando bcrypt (PHP `PASSWORD_DEFAULT`).

**Ubicazione**:
- `/public/users.php` - Creazione utenti admin
- `/public/install.php` - Creazione primo admin
- `/public/reset_password.php` - Reset password admin
- `/public/portal/inc/auth.php` - Autenticazione soci
- `/public/portal/register.php` - Attivazione account soci

**Codice di esempio**:
```php
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
// Verifica
if (password_verify($inputPassword, $hashedPassword)) {
    // Password corretta
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Algoritmo bcrypt con cost factor automatico
- Resistente a brute force
- Salt automatico generato

---

### 1.2 Sessioni Sicure ✅
**Implementazione**: Configurazione sessioni con parametri di sicurezza avanzati.

**Ubicazione**:
- `/src/auth.php` - Configurazione sessioni admin
- `/public/portal/inc/auth.php` - Configurazione sessioni portale soci

**Parametri configurati**:
```php
session_name('assolife_session'); // Nome personalizzato
// Impostazioni cookie sicure
session_set_cookie_params([
    'httponly' => true,      // Protezione XSS
    'samesite' => 'Strict',  // Protezione CSRF
    'secure' => true         // Solo HTTPS (se disponibile)
]);
```

**Livello di Sicurezza**: ✅ **SICURO**
- HTTPOnly previene accesso JavaScript ai cookie
- SameSite Strict previene CSRF
- Secure flag quando HTTPS disponibile

---

### 1.3 Timeout Sessione ✅
**Implementazione**: Timeout configurabile per inattività.

**Ubicazione**:
- `/src/config.php` - Configurazione timeout

**Comportamento**:
- Default: 30 minuti di inattività
- Logout automatico dopo timeout
- Rigenerazione ID sessione al login

**Livello di Sicurezza**: ✅ **SICURO**
- Previene accesso non autorizzato da sessioni abbandonate
- Timeout ragionevole per usabilità

---

### 1.4 Protezione Session Fixation ✅
**Implementazione**: Rigenerazione ID sessione dopo login.

**Ubicazione**:
- `/src/auth.php` - Funzione `login()`
- `/public/portal/inc/auth.php` - Login portale

**Codice**:
```php
if (password_verify($password, $user['password'])) {
    session_regenerate_id(true); // Rigenera ID
    $_SESSION['user_id'] = $user['id'];
    // ...
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Previene attacchi session fixation
- ID sessione nuovo ad ogni login

---

## 2. Protezione CSRF

### 2.1 Token CSRF su Tutti i Form POST ✅
**Implementazione**: Ogni form POST include un token CSRF univoco.

**Ubicazione**:
- `/src/auth.php` - Funzioni `generateCsrfToken()` e `csrfField()`
- Tutti i form nei file `/public/*.php`
- Tutti i form nel portale `/public/portal/*.php`

**Utilizzo**:
```php
<form method="POST">
    <?php echo csrfField(); ?>
    <!-- Altri campi -->
</form>
```

**Livello di Sicurezza**: ✅ **SICURO**
- Token generato con `random_bytes(32)`
- Token diverso per ogni sessione

---

### 2.2 Verifica Token Server-Side ✅
**Implementazione**: Controllo token prima di processare richieste POST.

**Ubicazione**:
- `/src/auth.php` - Funzioni `verifyCsrfToken()` e `checkCsrf()`
- Verificato in tutte le azioni POST

**Codice**:
```php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf(); // Interrompe se token invalido
    // Processa richiesta
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Utilizzo di `hash_equals()` per confronto timing-safe
- Protezione contro attacchi CSRF

---

### 2.3 Rigenerazione Token Dopo Uso ❓
**Implementazione**: Token sessione rimane costante.

**Comportamento Attuale**:
- Un token CSRF per sessione
- Token non rigenerato dopo ogni request

**Livello di Sicurezza**: ⚠️ **ACCETTABILE**
- Standard per la maggior parte delle applicazioni
- Token legato alla sessione (protetto)

**Raccomandazione**: 
- Per sicurezza massima, considerare rigenerazione token dopo azioni critiche (pagamenti, cambio password)

---

## 3. SQL Injection

### 3.1 Prepared Statements in Tutte le Query ✅
**Implementazione**: Utilizzo sistematico di prepared statements PDO.

**Ubicazione**: Tutte le query nel progetto

**Pattern utilizzato**:
```php
// Buono - Prepared statement
$stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
$stmt->execute([$id]);

// Buono - Named parameters
$stmt = $pdo->prepare("INSERT INTO " . table('members') . " (name, email) VALUES (:name, :email)");
$stmt->execute(['name' => $name, 'email' => $email]);
```

**Livello di Sicurezza**: ✅ **SICURO**
- Nessuna concatenazione diretta di input utente
- Tutti i parametri passati tramite binding
- Protezione completa contro SQL injection

---

### 3.2 Parametri Bound Correttamente ✅
**Implementazione**: Parametri sempre passati via `execute()`.

**Verifica**:
- Nessun uso di variabili in stringhe SQL
- Input utente sempre parametrizzato
- Nomi tabelle gestiti tramite funzione `table()`

**Livello di Sicurezza**: ✅ **SICURO**

---

### 3.3 Nessuna Concatenazione Diretta Input Utente ✅
**Implementazione**: Input utente mai concatenato direttamente in query SQL.

**Esempi sicuri**:
```php
// ✅ SICURO
$stmt = $pdo->prepare("SELECT * FROM " . table('users') . " WHERE email = ?");
$stmt->execute([$email]);

// ❌ NON PRESENTE nel codice
// $query = "SELECT * FROM users WHERE email = '$email'"; // VULNERABILE
```

**Livello di Sicurezza**: ✅ **SICURO**
- Codice verificato: nessuna vulnerabilità SQL injection trovata

---

## 4. XSS (Cross-Site Scripting)

### 4.1 Output Sanitizzato con `h()` ✅
**Implementazione**: Funzione helper per escape HTML.

**Ubicazione**:
- `/src/functions.php` - Definizione `h()` e `e()`
- Utilizzata in tutti i file di output

**Codice**:
```php
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

// Utilizzo
echo h($user['name']); // Output sicuro
```

**Livello di Sicurezza**: ✅ **SICURO**
- Escape di caratteri speciali HTML
- Flag ENT_QUOTES per proteggere attributi
- Gestione valori NULL

---

### 4.2 Header Content-Type Appropriati ✅
**Implementazione**: Header corretti per tipo di risposta.

**Ubicazione**:
- File PHP: `Content-Type: text/html; charset=UTF-8` (default)
- API JSON: `Content-Type: application/json`
- PDF: `Content-Type: application/pdf`

**Livello di Sicurezza**: ✅ **SICURO**
- Previene interpretazione errata del contenuto
- Charset UTF-8 esplicito

---

### 4.3 Escape Attributi HTML ✅
**Implementazione**: Sanitizzazione anche in attributi HTML.

**Esempi**:
```php
<input type="text" value="<?php echo h($data['value']); ?>">
<a href="/member.php?id=<?php echo h($id); ?>">Link</a>
```

**Livello di Sicurezza**: ✅ **SICURO**
- Protezione contro XSS anche in attributi
- Uso consistente di `h()` / `e()`

---

## 5. File Upload

### 5.1 Validazione MIME Type ✅
**Implementazione**: Controllo tipo MIME per upload file.

**Ubicazione**:
- `/public/settings.php` - Upload logo associazione
- `/public/portal/photo.php` - Upload fototessera

**Codice**:
```php
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
$fileType = $_FILES['file']['type'];
if (!in_array($fileType, $allowedTypes)) {
    die('Tipo file non consentito');
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Whitelist di tipi consentiti
- Solo immagini accettate

---

### 5.2 Limite Dimensione File ✅
**Implementazione**: Controllo dimensione massima file.

**Ubicazione**:
- `/public/settings.php` - Max 2MB per logo
- `/public/portal/photo.php` - Max 2MB per fototessera

**Codice**:
```php
$maxSize = 2 * 1024 * 1024; // 2MB
if ($_FILES['file']['size'] > $maxSize) {
    die('File troppo grande');
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Previene upload file enormi
- Limite ragionevole per immagini

---

### 5.3 Upload Esterno via ImgBB ✅
**Implementazione**: Fototessere caricate su servizio esterno.

**Ubicazione**:
- `/public/portal/photo.php` - Upload via API ImgBB

**Vantaggi**:
- Nessun file caricato direttamente sul server
- Nessun rischio di esecuzione script
- Gestione storage delegata
- URL sicuri restituiti da ImgBB

**Livello di Sicurezza**: ✅ **MOLTO SICURO**
- Elimina rischio di esecuzione file malevoli
- Separazione storage/applicazione

---

## 6. Controllo Accessi

### 6.1 Verifica Ruolo per Ogni Pagina Admin ✅
**Implementazione**: Controllo accesso basato su ruolo.

**Ubicazione**:
- `/src/auth.php` - Funzioni `requireLogin()`, `requireAdmin()`
- Tutte le pagine admin chiamano `requireAdmin()`

**Codice**:
```php
requireLogin();  // Verifica login
requireAdmin(); // Verifica ruolo admin

// Pagine riservate solo admin
// - settings.php
// - users.php
// - categories.php
```

**Livello di Sicurezza**: ✅ **SICURO**
- Controllo server-side
- Redirect automatico se non autorizzato
- Separazione ruoli admin/operatore

---

### 6.2 Verifica Appartenenza per Risorse Socio ✅
**Implementazione**: Socio può accedere solo alle proprie risorse.

**Ubicazione**:
- `/public/portal/profile.php` - Profilo proprio
- `/public/portal/payments.php` - Quote proprie
- `/public/portal/receipts.php` - Ricevute proprie

**Codice**:
```php
// Socio loggato
$memberId = $_SESSION['member_id'];

// Query filtra per member_id
$stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE member_id = ?");
$stmt->execute([$memberId]);
```

**Livello di Sicurezza**: ✅ **SICURO**
- Nessun accesso a dati di altri soci
- ID socio da sessione, non da GET/POST

---

### 6.3 Token Sicuri per Risorse Sensibili ✅
**Implementazione**: Token per accesso a ricevute e tesserini.

**Ubicazione**:
- `/public/receipt.php` - Ricevute con token
- `/public/verify_member.php` - Verifica tessera con token
- `/public/portal/register.php` - Attivazione account con token

**Generazione token**:
```php
$token = bin2hex(random_bytes(32)); // 64 caratteri
```

**Livello di Sicurezza**: ✅ **SICURO**
- Token casuali crittograficamente sicuri
- Lunghezza 64 caratteri
- Impossibili da indovinare
- Scadenza per token temporanei (attivazione, reset password)

---

## 7. Configurazione Server

### 7.1 File .htaccess con Regole Sicurezza ✅
**Implementazione**: Protezione directory e file sensibili.

**Ubicazione**: `/.htaccess` (root), `/src/.htaccess`

**Contenuto**:
```apache
# Blocca accesso a file sensibili
<FilesMatch "\.(htaccess|htpasswd|ini|log|sh|inc|bak)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Blocca accesso a directory src/
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^src/ - [F,L]
</IfModule>
```

**Livello di Sicurezza**: ✅ **SICURO**
- Previene accesso diretto a config
- Protezione file sensibili
- Funziona su Apache (AlterVista compatibile)

---

### 7.2 Protezione Directory src/ ✅
**Implementazione**: Accesso diretto negato via .htaccess.

**File**: `/src/.htaccess`
```apache
Order Deny,Allow
Deny from all
```

**Livello di Sicurezza**: ✅ **SICURO**
- Nessun accesso HTTP diretto a src/
- File inclusi solo via PHP
- Config protetta

---

### 7.3 HTTPS Opzionale (force_https) ✅
**Implementazione**: Redirect HTTPS configurabile.

**Ubicazione**: `/src/config.php`

**Codice**:
```php
'force_https' => false, // Configurabile

// Se abilitato in auth.php
if ($config['app']['force_https'] && !isset($_SERVER['HTTPS'])) {
    $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    header('Location: ' . $redirect);
    exit;
}
```

**Livello di Sicurezza**: ✅ **SICURO**
- Compatibile con hosting senza SSL (AlterVista free)
- Attivabile quando SSL disponibile
- Protezione traffico se abilitato

---

## 8. Raccomandazioni

### 8.1 Rate Limiting per Login ⚠️
**Status**: ❌ NON IMPLEMENTATO

**Rischio**: Attacchi brute force su password.

**Raccomandazione**:
- Implementare limite tentativi login (es: 5 tentativi in 15 minuti)
- Blocco temporaneo dopo tentativi falliti
- CAPTCHA dopo X tentativi falliti

**Esempio implementazione**:
```php
// Tracciare tentativi in sessione o DB
if ($_SESSION['login_attempts'] > 5) {
    $lockoutTime = 15 * 60; // 15 minuti
    if (time() - $_SESSION['last_attempt'] < $lockoutTime) {
        die('Troppi tentativi. Riprova tra 15 minuti.');
    }
}
```

**Priorità**: 🔴 **ALTA** per area admin, 🟡 **MEDIA** per portale soci

---

### 8.2 Logging Tentativi Falliti ⚠️
**Status**: ❌ NON IMPLEMENTATO

**Rischio**: Difficile rilevare tentativi di intrusione.

**Raccomandazione**:
- Loggare tutti i tentativi di login falliti
- Includere: timestamp, IP, username tentato
- Alert admin per attività sospetta

**Esempio implementazione**:
```php
// In caso di login fallito
logSecurityEvent('login_failed', [
    'username' => $username,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'timestamp' => date('Y-m-d H:i:s')
]);
```

**Priorità**: 🟡 **MEDIA**

---

### 8.3 2FA per Admin ⚠️
**Status**: ❌ NON IMPLEMENTATO

**Rischio**: Account admin compromessi con solo password.

**Raccomandazione**:
- Implementare autenticazione a due fattori per admin
- Opzioni: TOTP (Google Authenticator), SMS, Email
- Obbligatorio per ruolo admin, opzionale per operatore

**Priorità**: 🟡 **MEDIA** (dipende dal livello di rischio)

---

### 8.4 Audit Log Operazioni Sensibili ✅
**Status**: ✅ PARZIALMENTE IMPLEMENTATO

**Implementazione**:
- File `/src/audit.php` presente
- Funzione `logAudit()` disponibile
- Tabella `audit_log` nel database

**Utilizzo attuale**:
- Non sistematicamente applicato a tutte le operazioni

**Raccomandazione**:
- Estendere logging a:
  - Creazione/modifica/eliminazione soci
  - Conferma pagamenti
  - Modifica impostazioni
  - Gestione utenti admin
  - Approvazione richieste

**Priorità**: 🟡 **MEDIA**

---

## 9. Analisi Vulnerabilità Specifiche

### 9.1 Iniezione Comandi (Command Injection) ✅
**Status**: ✅ **NON VULNERABILE**

**Verifica**: Nessun uso di funzioni come `exec()`, `shell_exec()`, `system()`, `passthru()`.

**Livello di Sicurezza**: ✅ **SICURO**

---

### 9.2 Path Traversal ✅
**Status**: ✅ **NON VULNERABILE**

**Verifica**: 
- Nessun accesso file basato su input utente diretto
- Upload delegato a ImgBB (esterno)
- Nessun `include` dinamico basato su GET/POST

**Livello di Sicurezza**: ✅ **SICURO**

---

### 9.3 Insecure Deserialization ✅
**Status**: ✅ **NON VULNERABILE**

**Verifica**: Nessun uso di `unserialize()` su dati non fidati.

**Livello di Sicurezza**: ✅ **SICURO**

---

### 9.4 XML External Entities (XXE) ✅
**Status**: ✅ **NON APPLICABILE**

**Verifica**: Nessun parsing XML nell'applicazione.

**Livello di Sicurezza**: ✅ **N/A**

---

### 9.5 Server-Side Request Forgery (SSRF) ⚠️
**Status**: ⚠️ **RISCHIO BASSO**

**Scenario**:
- Upload via ImgBB API
- Chiamata a PayPal API

**Mitigazione**:
- URL API hardcoded (non da input utente)
- HTTPS obbligatorio per API esterne
- Timeout configurati su cURL

**Livello di Sicurezza**: ✅ **ACCETTABILE**

---

### 9.6 Insecure Direct Object Reference (IDOR) ✅
**Status**: ✅ **PROTETTO**

**Verifica**:
- ID risorse sempre validati
- Portale soci: accesso solo a proprie risorse
- Admin: verifica ruolo prima di accesso

**Esempio**:
```php
// ✅ SICURO - Verifica appartenenza
$stmt = $pdo->prepare("SELECT * FROM " . table('member_fees') . " WHERE id = ? AND member_id = ?");
$stmt->execute([$feeId, $_SESSION['member_id']]);
```

**Livello di Sicurezza**: ✅ **SICURO**

---

### 9.7 Clickjacking ⚠️
**Status**: ⚠️ **NON PROTETTO**

**Raccomandazione**:
- Aggiungere header `X-Frame-Options` o CSP `frame-ancestors`

**Esempio**:
```php
header('X-Frame-Options: DENY');
// O in .htaccess
Header always set X-Frame-Options "DENY"
```

**Priorità**: 🟢 **BASSA** (rischio limitato per questo tipo di applicazione)

---

### 9.8 Content Security Policy (CSP) ⚠️
**Status**: ⚠️ **NON IMPLEMENTATO**

**Raccomandazione**:
- Implementare CSP per mitigare ulteriormente XSS

**Esempio**:
```php
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net; style-src 'self' https://cdn.jsdelivr.net 'unsafe-inline';");
```

**Priorità**: 🟢 **BASSA** (sanitizzazione output già robusta)

---

## 10. Compliance e Best Practices

### 10.1 GDPR / Privacy ⚠️
**Considerazioni**:
- Dati personali sensibili gestiti (CF, email, telefono)
- No policy privacy esplicita
- No consenso esplicito tracciato

**Raccomandazione**:
- Aggiungere informativa privacy
- Consenso esplicito per trattamento dati
- Funzionalità per export/cancellazione dati (diritto all'oblio)

**Priorità**: 🔴 **ALTA** (se operante in UE)

---

### 10.2 Backup e Disaster Recovery ⚠️
**Considerazioni**:
- Nessun sistema automatico di backup presente nel codice
- Dipende da backup hosting

**Raccomandazione**:
- Implementare backup automatici database
- Export periodico dati critici
- Piano di ripristino documentato

**Priorità**: 🟡 **MEDIA**

---

### 10.3 Gestione Secrets ✅
**Status**: ✅ **BUONO**

**Implementazione**:
- Config in file separato (`/src/config.php`)
- `.gitignore` impedisce commit config
- API keys in database (tabella `settings`)

**Raccomandazione**:
- Considerare cifratura API keys in database
- Usare variabili ambiente per secrets (se supportato dall'hosting)

**Priorità**: 🟢 **BASSA**

---

## 11. Scorecard Sicurezza

| Area | Score | Note |
|------|-------|------|
| **Autenticazione** | ✅ 9/10 | Eccellente. Manca solo 2FA |
| **CSRF** | ✅ 9/10 | Ottimo. Potrebbe rigenerare token dopo azioni critiche |
| **SQL Injection** | ✅ 10/10 | Perfetto. Prepared statements ovunque |
| **XSS** | ✅ 10/10 | Perfetto. Sanitizzazione consistente |
| **File Upload** | ✅ 10/10 | Perfetto. Upload esterno elimina rischi |
| **Controllo Accessi** | ✅ 9/10 | Ottimo. Bene separazione ruoli e risorse |
| **Configurazione** | ✅ 8/10 | Buono. Mancano header sicurezza (CSP, X-Frame) |
| **Audit/Logging** | ⚠️ 6/10 | Parziale. Audit log presente ma non completo |
| **Rate Limiting** | ❌ 3/10 | Assente. Vulnerabile a brute force |
| **Privacy/GDPR** | ⚠️ 5/10 | Mancano policy e consensi espliciti |

### Score Complessivo: ✅ **8.1/10** - **BUONO**

Il sistema AssoLife presenta un **buon livello di sicurezza** con implementazioni solide nelle aree critiche (SQL injection, XSS, autenticazione). Le raccomandazioni riguardano principalmente funzionalità aggiuntive (rate limiting, 2FA, logging esteso) che aumenterebbero ulteriormente la sicurezza senza essere critiche per il funzionamento sicuro di base.

---

## 12. Piano di Miglioramento Raccomandato

### Priorità Alta 🔴
1. **Rate limiting su login** - Previene brute force
2. **Informativa Privacy e GDPR compliance** - Obbligatorio se UE

### Priorità Media 🟡
3. **Logging tentativi falliti** - Monitoring sicurezza
4. **Audit log completo** - Tracciabilità operazioni
5. **Backup automatici** - Disaster recovery

### Priorità Bassa 🟢
6. **2FA per admin** - Sicurezza aggiuntiva
7. **Header CSP e X-Frame-Options** - Hardening
8. **Cifratura API keys in DB** - Protezione ulteriore

---

## 13. Conclusioni

AssoLife è un sistema **ben progettato dal punto di vista della sicurezza**, con particolare attenzione a:
- ✅ Prevenzione SQL injection tramite prepared statements
- ✅ Protezione XSS con sanitizzazione output
- ✅ Autenticazione robusta con bcrypt
- ✅ Protezione CSRF su tutti i form
- ✅ Controllo accessi basato su ruoli
- ✅ Upload file sicuro (delegato a servizio esterno)

Le vulnerabilità principali sono:
- ⚠️ Assenza rate limiting (rischio brute force)
- ⚠️ Logging sicurezza non completo
- ⚠️ Mancanza policy privacy GDPR

**Raccomandazione finale**: Il sistema è **adatto all'uso in produzione** con un livello di rischio **accettabile**. Per scenari ad alto rischio o regolamentati, implementare le raccomandazioni di priorità alta.

---

**AssoLife Security Analysis v1.0**  
*Powered by Luigi Pistarà*  
*Data Analisi: 2026-01-04*

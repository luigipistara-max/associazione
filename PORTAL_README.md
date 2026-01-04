# Portale Soci - Parte 1: Struttura Base, Autenticazione e Profilo

## Panoramica

Questo aggiornamento introduce un portale riservato ai soci dell'associazione dove possono:
- Accedere con email e password sicura
- Visualizzare e modificare i propri dati personali
- Caricare una fototessera via ImgBB API
- Visualizzare il proprio tesserino digitale con foto e QR code
- Gestire la propria password

## Installazione

### Per Nuove Installazioni

Se stai installando il sistema da zero, il file `schema.sql` include già tutte le modifiche necessarie. Non serve fare nulla di particolare.

### Per Aggiornamenti da Versioni Precedenti

Se hai già un'installazione esistente, devi eseguire la migrazione del database:

1. **Backup del Database** (IMPORTANTE!)
   ```bash
   mysqldump -u username -p database_name > backup_$(date +%Y%m%d).sql
   ```

2. **Esegui la Migration**
   ```bash
   mysql -u username -p database_name < migrations/001_portal_soci_parte_1.sql
   ```

   Oppure tramite phpMyAdmin:
   - Accedi a phpMyAdmin
   - Seleziona il tuo database
   - Vai su "SQL"
   - Copia e incolla il contenuto di `migrations/001_portal_soci_parte_1.sql`
   - Clicca "Esegui"

## Configurazione

### 1. Configurare API ImgBB (per upload foto)

1. Vai su [https://api.imgbb.com/](https://api.imgbb.com/)
2. Crea un account gratuito
3. Ottieni la tua API key
4. Nel gestionale, vai su **Impostazioni** > **API**
5. Inserisci la **API Key ImgBB**
6. Salva

### 2. Configurare Email (se non già fatto)

Assicurati che l'invio email sia configurato in `src/config.php` o tramite le impostazioni SMTP, perché i soci riceveranno email per:
- Attivazione account
- Recupero password

### 3. Invitare i Soci

1. Vai su **Soci** e modifica un socio
2. Nella sezione "Accesso Portale Soci" clicca su **Invia Attivazione**
3. Il socio riceverà un'email con un link per impostare la password
4. Il link scade dopo 24 ore

## Struttura File

### File Portale

```
public/portal/
├── index.php              # Dashboard socio
├── login.php              # Login
├── logout.php             # Logout
├── register.php           # Attivazione account (prima password)
├── forgot_password.php    # Recupero password
├── reset_password.php     # Reset password
├── profile.php            # Profilo socio
├── photo.php              # Upload fototessera
├── card.php               # Tesserino digitale
└── inc/
    ├── auth.php           # Funzioni autenticazione
    ├── header.php         # Header portale
    └── footer.php         # Footer portale
```

### Database

#### Nuove Colonne - Tabella `members`

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| portal_password | VARCHAR(255) | Password hash per accesso portale |
| portal_token | VARCHAR(64) | Token per attivazione/reset password |
| portal_token_expires | DATETIME | Scadenza token |
| photo_url | VARCHAR(500) | URL fototessera (ImgBB) |
| last_portal_login | DATETIME | Ultimo accesso al portale |

#### Nuove Colonne - Tabella `member_groups`

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| is_hidden | BOOLEAN | Gruppo nascosto nel portale soci |
| is_restricted | BOOLEAN | Solo admin può assegnare membri |

#### Nuova Tabella - `member_group_requests`

Gestisce le richieste dei soci di partecipare ai gruppi.

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| id | INT | ID richiesta |
| member_id | INT | ID socio |
| group_id | INT | ID gruppo |
| status | ENUM | pending/approved/rejected |
| message | TEXT | Messaggio del socio |
| requested_at | TIMESTAMP | Data richiesta |
| processed_at | TIMESTAMP | Data elaborazione |
| processed_by | INT | Admin che ha elaborato |
| admin_notes | TEXT | Note amministratore |

#### Nuove Colonne - Tabella `member_fees`

| Colonna | Tipo | Descrizione |
|---------|------|-------------|
| payment_pending | BOOLEAN | Pagamento in elaborazione |
| payment_reference | VARCHAR(100) | Riferimento pagamento |
| paypal_transaction_id | VARCHAR(100) | ID transazione PayPal |
| payment_confirmed_by | INT | Admin che ha confermato |
| payment_confirmed_at | DATETIME | Data conferma |

## Funzionalità

### Autenticazione

- **Password sicure**: Minimo 8 caratteri, maiuscola, minuscola, numero
- **Token sicuri**: 64 caratteri esadecimali, scadenza 24 ore
- **Session separata**: Portal session separata da admin session

### Profilo Socio

I soci possono:
- ✅ Visualizzare i propri dati (nome, cognome, CF, tessera - non modificabili)
- ✅ Modificare email, telefono, indirizzo
- ✅ Cambiare password
- ✅ Caricare fototessera

### Fototessera

- Formati supportati: JPG, PNG, GIF
- Dimensione massima: 2MB
- Upload via ImgBB API (servizio gratuito)
- Dimensioni ottimali: rapporto 3:4 (es. 600x800px)
- Visualizzazione nel tesserino: 150x200px

### Tesserino Digitale

Il tesserino include:
- Fototessera del socio
- Nome completo
- Numero tessera
- Stato (Attivo/Non Attivo basato su quota pagata)
- QR code per verifica validità
- Stampabile

## Sicurezza

### Implementate

- ✅ Password hash con `password_hash()` (bcrypt)
- ✅ Token sicuri generati con `random_bytes()`
- ✅ Validazione input su tutti i form
- ✅ HTTPS raccomandato (forza in config se disponibile)
- ✅ Session separate per portale e admin
- ✅ Escape output HTML con funzione `h()`
- ✅ Prepared statements per query SQL

### Raccomandazioni

1. **Abilita HTTPS**: Configura SSL/TLS per proteggere le comunicazioni
2. **Email sicure**: Usa SMTP autenticato per invio email
3. **Backup regolari**: Fai backup del database regolarmente
4. **Monitora accessi**: Controlla il log degli accessi sospetti
5. **Limita rate**: Considera di implementare rate limiting su login

## Testing

### Test Manuali

1. **Attivazione Account**
   - [ ] Admin invia email di attivazione
   - [ ] Socio riceve email con link
   - [ ] Link apre pagina di registrazione
   - [ ] Password debole viene rifiutata
   - [ ] Password forte viene accettata
   - [ ] Account viene attivato
   - [ ] Token scade dopo 24 ore

2. **Login**
   - [ ] Login con email e password corrette
   - [ ] Login fallisce con password sbagliata
   - [ ] Login fallisce con account non attivato
   - [ ] Redirect a dashboard dopo login

3. **Recupero Password**
   - [ ] Richiesta recupero invia email
   - [ ] Link reset password funziona
   - [ ] Nuova password viene salvata
   - [ ] Login con nuova password

4. **Profilo**
   - [ ] Visualizzazione dati corretta
   - [ ] Modifica email/telefono/indirizzo
   - [ ] Cambio password funziona
   - [ ] Validazione email duplicata

5. **Upload Foto**
   - [ ] Upload JPG funziona
   - [ ] Upload PNG funziona
   - [ ] File troppo grande viene rifiutato
   - [ ] Formato non valido viene rifiutato
   - [ ] Foto appare nel profilo e tesserino
   - [ ] Rimozione foto funziona

6. **Tesserino**
   - [ ] Tesserino mostra foto
   - [ ] Tesserino mostra dati corretti
   - [ ] QR code è visualizzato
   - [ ] Stampa funziona correttamente

7. **Admin - Gruppi**
   - [ ] Flag "Nascosto" viene salvato
   - [ ] Flag "Ristretto" viene salvato
   - [ ] Modifiche appaiono in lista gruppi

8. **Admin - Invio Attivazione**
   - [ ] Pulsante appare in modifica socio
   - [ ] Email viene inviata
   - [ ] Stato attivazione visualizzato correttamente

## Troubleshooting

### Email non arrivano

1. Controlla configurazione SMTP in `src/config.php`
2. Verifica che email sia abilitata: `email.enabled = true`
3. Controlla cartella spam
4. Verifica log email nel database: tabella `email_log`

### Upload foto non funziona

1. Verifica API key ImgBB in Impostazioni > API
2. Controlla che API key sia valida su imgbb.com
3. Verifica dimensione file (max 2MB)
4. Controlla formato file (solo JPG, PNG, GIF)

### Login non funziona

1. Verifica che socio abbia attivato account (portal_password non null)
2. Controlla stato socio (deve essere 'attivo')
3. Verifica email corretta
4. Prova recupero password

### Tesserino non mostra foto

1. Verifica che foto sia stata caricata (campo photo_url)
2. Controlla che URL ImgBB sia ancora valido
3. Verifica connessione internet

## Prossimi Sviluppi (Parte 2)

- Eventi: Iscrizione eventi dal portale
- Gruppi: Richiesta partecipazione gruppi
- Pagamenti: Pagamento quote via PayPal
- Ricevute: Download ricevute pagamenti
- Notifiche: Sistema notifiche in-app

## Supporto

Per problemi o domande:
1. Controlla questo README
2. Verifica la sezione Troubleshooting
3. Controlla i log del database
4. Contatta il supporto tecnico

## License

Questo software è parte del sistema di gestione AssoLife.

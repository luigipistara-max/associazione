# AssoLife - Test Plan Completo

Documento di test plan per il sistema AssoLife.  
Organizzato per area funzionale con test case dettagliati.

---

## 1. Test Autenticazione Admin

### 1.1 Login Admin con Credenziali Corrette
**Obiettivo**: Verificare che un admin possa accedere al sistema con credenziali valide.

**Prerequisiti**:
- Sistema installato e configurato
- Account admin esistente

**Passi**:
1. Navigare su `/public/login.php`
2. Inserire username valido
3. Inserire password corretta
4. Cliccare "Accedi"

**Risultato Atteso**:
- Redirect alla dashboard `/public/index.php`
- Sessione utente creata
- Messaggio di benvenuto visualizzato
- Menu admin completo visibile

---

### 1.2 Login Admin con Credenziali Errate
**Obiettivo**: Verificare che il sistema rifiuti credenziali non valide.

**Prerequisiti**:
- Sistema installato

**Passi**:
1. Navigare su `/public/login.php`
2. Inserire username o password errati
3. Cliccare "Accedi"

**Risultato Atteso**:
- Messaggio di errore "Credenziali non valide"
- Utente rimane sulla pagina di login
- Nessuna sessione creata

---

### 1.3 Logout Admin
**Obiettivo**: Verificare che l'admin possa disconnettersi correttamente.

**Prerequisiti**:
- Admin loggato nel sistema

**Passi**:
1. Essere loggati come admin
2. Cliccare "Logout" nel menu
3. Tentare di accedere a pagina protetta

**Risultato Atteso**:
- Sessione distrutta
- Redirect a `/public/login.php`
- Accesso alle pagine protette negato

---

### 1.4 Accesso Pagine Protette Senza Login
**Obiettivo**: Verificare che le pagine admin siano protette.

**Prerequisiti**:
- Utente non loggato

**Passi**:
1. Navigare direttamente su `/public/members.php`
2. Navigare su `/public/finance.php`
3. Navigare su `/public/settings.php`

**Risultato Atteso**:
- Redirect automatico a `/public/login.php`
- Messaggio "Accesso non autorizzato"

---

### 1.5 Verifica Ruolo Admin vs Operatore
**Obiettivo**: Verificare che gli operatori non possano accedere a funzioni admin.

**Prerequisiti**:
- Account operatore creato
- Loggato come operatore

**Passi**:
1. Login come operatore
2. Tentare accesso a `/public/users.php`
3. Tentare accesso a `/public/settings.php`
4. Verificare accesso a `/public/members.php`

**Risultato Atteso**:
- Accesso a users.php e settings.php negato
- Accesso a members.php consentito
- Messaggio "Permessi insufficienti"

---

## 2. Test Portale Soci - Autenticazione

### 2.1 Registrazione/Attivazione Account Socio
**Obiettivo**: Verificare il processo di attivazione account per un socio.

**Prerequisiti**:
- Socio presente nell'anagrafica
- Email di attivazione inviata dall'admin

**Passi**:
1. Admin va su scheda socio e clicca "Invia Email Attivazione"
2. Socio riceve email con link di attivazione
3. Socio clicca sul link
4. Pagina `/public/portal/register.php` si apre con token
5. Socio inserisce password (min 8 caratteri)
6. Socio conferma password
7. Socio clicca "Attiva Account"

**Risultato Atteso**:
- Account attivato con successo
- Password salvata con hash bcrypt
- Token invalidato
- Redirect a `/public/portal/login.php`
- Messaggio di conferma

---

### 2.2 Login Socio con Email e Password
**Obiettivo**: Verificare che un socio possa accedere al portale.

**Prerequisiti**:
- Account socio attivato

**Passi**:
1. Navigare su `/public/portal/login.php`
2. Inserire email del socio
3. Inserire password
4. Cliccare "Accedi"

**Risultato Atteso**:
- Redirect a `/public/portal/index.php`
- Sessione socio creata
- Dashboard socio visualizzata
- Menu portale visibile

---

### 2.3 Login Socio con Credenziali Errate
**Obiettivo**: Verificare rifiuto credenziali non valide.

**Prerequisiti**:
- Sistema configurato

**Passi**:
1. Navigare su `/public/portal/login.php`
2. Inserire email o password errati
3. Cliccare "Accedi"

**Risultato Atteso**:
- Messaggio di errore
- Utente rimane sulla pagina di login
- Nessuna sessione creata

---

### 2.4 Recupero Password (Forgot Password)
**Obiettivo**: Verificare il flusso di reset password.

**Prerequisiti**:
- Account socio attivato

**Passi**:
1. Navigare su `/public/portal/forgot_password.php`
2. Inserire email del socio
3. Cliccare "Invia Link Reset"

**Risultato Atteso**:
- Email inviata con link reset
- Token generato nel database
- Messaggio di conferma invio email

---

### 2.5 Reset Password con Token Valido
**Obiettivo**: Verificare cambio password con token valido.

**Prerequisiti**:
- Token reset generato e non scaduto

**Passi**:
1. Cliccare sul link nella email di reset
2. Pagina `/public/portal/reset_password.php?token=xxx`
3. Inserire nuova password
4. Confermare nuova password
5. Cliccare "Reimposta Password"

**Risultato Atteso**:
- Password aggiornata nel database
- Token invalidato
- Redirect al login
- Messaggio di conferma
- Login possibile con nuova password

---

### 2.6 Reset Password con Token Scaduto/Invalido
**Obiettivo**: Verificare gestione token non validi.

**Prerequisiti**:
- Token scaduto o inesistente

**Passi**:
1. Navigare su `/public/portal/reset_password.php?token=invalid`
2. Oppure usare token scaduto (>1 ora)

**Risultato Atteso**:
- Messaggio "Token non valido o scaduto"
- Form di reset non visualizzato
- Link per richiedere nuovo reset

---

### 2.7 Logout Socio
**Obiettivo**: Verificare disconnessione dal portale.

**Prerequisiti**:
- Socio loggato

**Passi**:
1. Cliccare "Logout" nel menu portale
2. Tentare accesso a pagina protetta del portale

**Risultato Atteso**:
- Sessione distrutta
- Redirect a `/public/portal/login.php`
- Accesso pagine portale negato

---

## 3. Test Portale Soci - Profilo

### 3.1 Visualizzazione Profilo
**Obiettivo**: Verificare visualizzazione dati personali.

**Prerequisiti**:
- Socio loggato

**Passi**:
1. Navigare su `/public/portal/profile.php`

**Risultato Atteso**:
- Dati anagrafici visualizzati correttamente
- Email, telefono, indirizzo visibili
- Foto profilo (se presente)
- Stato socio e numero tessera

---

### 3.2 Modifica Dati Personali
**Obiettivo**: Verificare aggiornamento dati modificabili.

**Prerequisiti**:
- Socio loggato

**Passi**:
1. Navigare su `/public/portal/profile.php`
2. Modificare email
3. Modificare telefono
4. Modificare indirizzo
5. Cliccare "Salva Modifiche"

**Risultato Atteso**:
- Dati aggiornati nel database
- Messaggio di conferma
- Validazione email (formato corretto)
- CSRF token verificato

---

### 3.3 Cambio Password
**Obiettivo**: Verificare cambio password dal profilo.

**Prerequisiti**:
- Socio loggato

**Passi**:
1. Navigare su `/public/portal/profile.php`
2. Inserire password attuale
3. Inserire nuova password (min 8 caratteri)
4. Confermare nuova password
5. Cliccare "Cambia Password"

**Risultato Atteso**:
- Password vecchia verificata
- Nuova password salvata con hash
- Messaggio di conferma
- Login con nuova password funzionante

---

### 3.4 Upload Fototessera (ImgBB)
**Obiettivo**: Verificare caricamento foto profilo.

**Prerequisiti**:
- Socio loggato
- ImgBB API key configurata in settings

**Passi**:
1. Navigare su `/public/portal/photo.php`
2. Selezionare immagine (JPG/PNG, max 2MB)
3. Cliccare "Carica Foto"

**Risultato Atteso**:
- File validato (tipo MIME, dimensione)
- Upload su ImgBB tramite API
- URL foto salvato in `members.photo_url`
- Foto visualizzata nel profilo
- Messaggio di conferma

---

### 3.5 Rimozione Fototessera
**Obiettivo**: Verificare rimozione foto profilo.

**Prerequisiti**:
- Socio loggato con foto caricata

**Passi**:
1. Navigare su `/public/portal/photo.php`
2. Cliccare "Rimuovi Foto"
3. Confermare rimozione

**Risultato Atteso**:
- Campo `photo_url` impostato a NULL
- Foto non più visualizzata
- Messaggio di conferma
- Possibilità di ricaricare nuova foto

---

### 3.6 Visualizzazione Tesserino Digitale
**Obiettivo**: Verificare generazione e visualizzazione tesserino.

**Prerequisiti**:
- Socio loggato

**Passi**:
1. Navigare su `/public/portal/card.php`

**Risultato Atteso**:
- Tesserino visualizzato con:
  - Nome e cognome socio
  - Numero tessera
  - Foto (se presente)
  - QR code con `card_token`
  - Anno sociale corrente
- Design professionale e stampabile

---

### 3.7 QR Code Verifica Tessera
**Obiettivo**: Verificare sistema di verifica tramite QR code.

**Prerequisiti**:
- Tesserino con QR code generato

**Passi**:
1. Scansionare QR code dal tesserino
2. Navigare su `/public/verify_member.php?token=xxx`

**Risultato Atteso**:
- Dati socio visualizzati (nome, tessera, stato)
- Validità tessera confermata
- Token non valido genera errore
- Pagina pubblica (no login richiesto)

---

## 4. Test Portale Soci - Eventi

### 4.1 Visualizzazione Eventi Disponibili (Target "All")
**Obiettivo**: Verificare lista eventi aperti a tutti.

**Prerequisiti**:
- Socio loggato
- Eventi pubblicati con target "all"

**Passi**:
1. Navigare su `/public/portal/events.php`

**Risultato Atteso**:
- Lista eventi con target "all" visualizzata
- Dettagli evento: titolo, data, luogo/online
- Stato iscrizioni (aperte/chiuse)
- Posti disponibili (se limitati)
- Pulsante per dare disponibilità

---

### 4.2 Visualizzazione Eventi dei Propri Gruppi
**Obiettivo**: Verificare visualizzazione eventi target gruppi di appartenenza.

**Prerequisiti**:
- Socio membro di uno o più gruppi
- Eventi con target specifico ai suoi gruppi

**Passi**:
1. Navigare su `/public/portal/events.php`

**Risultato Atteso**:
- Eventi del gruppo visualizzati
- Eventi di altri gruppi NON visualizzati
- Badge gruppo visibile sull'evento
- Filtro per gruppo funzionante

---

### 4.3 Dare Disponibilità "Sì"
**Obiettivo**: Verificare conferma partecipazione.

**Prerequisiti**:
- Socio loggato
- Evento pubblicato e aperto

**Passi**:
1. Visualizzare dettaglio evento
2. Selezionare "Parteciperò" (Sì)
3. Confermare

**Risultato Atteso**:
- Record creato in `event_registrations`
- Status impostato a "yes"
- Contatore partecipanti incrementato
- Messaggio di conferma
- Link online visibile (se evento online)

---

### 4.4 Dare Disponibilità "Forse"
**Obiettivo**: Verificare disponibilità incerta.

**Prerequisiti**:
- Socio loggato
- Evento pubblicato

**Passi**:
1. Visualizzare dettaglio evento
2. Selezionare "Forse"
3. Confermare

**Risultato Atteso**:
- Status impostato a "maybe"
- Contatore "forse" incrementato
- Link online NON visibile
- Messaggio di conferma

---

### 4.5 Dare Disponibilità "No"
**Obiettivo**: Verificare rifiuto partecipazione.

**Prerequisiti**:
- Socio loggato
- Evento pubblicato

**Passi**:
1. Visualizzare dettaglio evento
2. Selezionare "Non parteciperò" (No)
3. Confermare

**Risultato Atteso**:
- Status impostato a "no"
- Contatore "no" incrementato
- Link online NON visibile
- Messaggio di conferma

---

### 4.6 Modificare Disponibilità Esistente
**Obiettivo**: Verificare cambio disponibilità.

**Prerequisiti**:
- Socio con disponibilità già espressa

**Passi**:
1. Visualizzare evento con disponibilità esistente
2. Cambiare da "Sì" a "Forse"
3. Confermare

**Risultato Atteso**:
- Record aggiornato (non duplicato)
- Contatori ricalcolati
- Link online visibilità aggiornata
- Messaggio di conferma

---

### 4.7 Aggiornamento Contatori in Tempo Reale (AJAX)
**Obiettivo**: Verificare aggiornamento contatori senza refresh.

**Prerequisiti**:
- Socio loggato
- JavaScript abilitato

**Passi**:
1. Visualizzare lista eventi
2. Dare disponibilità a un evento
3. Osservare contatori

**Risultato Atteso**:
- Contatori aggiornati via AJAX
- Nessun refresh pagina necessario
- Feedback visivo immediato
- Posti rimanenti aggiornati

---

## 5. Test Portale Soci - Gruppi

### 5.1 Visualizzazione Gruppi di Appartenenza
**Obiettivo**: Verificare lista gruppi del socio.

**Prerequisiti**:
- Socio membro di uno o più gruppi

**Passi**:
1. Navigare su `/public/portal/groups.php`

**Risultato Atteso**:
- Sezione "I Miei Gruppi" visibile
- Gruppi di appartenenza elencati
- Nome, descrizione, colore gruppo
- Data iscrizione al gruppo

---

### 5.2 Visualizzazione Gruppi Pubblici Disponibili
**Obiettivo**: Verificare gruppi richiedibili.

**Prerequisiti**:
- Gruppi pubblici esistenti (non nascosti, non ristretti)

**Passi**:
1. Navigare su `/public/portal/groups.php`

**Risultato Atteso**:
- Sezione "Gruppi Disponibili" visibile
- Solo gruppi pubblici elencati
- Pulsante "Richiedi Partecipazione"
- Descrizione gruppo visibile

---

### 5.3 Gruppi Nascosti (is_hidden) NON Visibili
**Obiettivo**: Verificare che gruppi nascosti non appaiano.

**Prerequisiti**:
- Gruppi con `is_hidden = 1` esistenti

**Passi**:
1. Navigare su `/public/portal/groups.php`
2. Cercare gruppi nascosti nella lista

**Risultato Atteso**:
- Gruppi con `is_hidden = 1` NON visualizzati
- Non appaiono in "Gruppi Disponibili"
- Visibili solo in admin

---

### 5.4 Gruppi Ristretti (is_restricted) NON Richiedibili
**Obiettivo**: Verificare che gruppi ristretti non siano richiedibili.

**Prerequisiti**:
- Gruppi con `is_restricted = 1` esistenti

**Passi**:
1. Navigare su `/public/portal/groups.php`
2. Cercare gruppi ristretti

**Risultato Atteso**:
- Gruppi ristretti NON presenti in "Gruppi Disponibili"
- Nessun pulsante per richiedere
- Solo admin può aggiungere membri

---

### 5.5 Richiesta Partecipazione Gruppo
**Obiettivo**: Verificare invio richiesta di adesione.

**Prerequisiti**:
- Gruppo pubblico disponibile
- Socio non membro del gruppo

**Passi**:
1. Navigare su `/public/portal/groups.php`
2. Cliccare "Richiedi Partecipazione" su un gruppo
3. Confermare richiesta

**Risultato Atteso**:
- Record creato in `member_group_requests`
- Status impostato a "pending"
- Messaggio "Richiesta inviata"
- Pulsante cambia in "Richiesta in attesa"
- Notifica admin (opzionale)

---

### 5.6 Visualizzazione Stato Richieste
**Obiettivo**: Verificare monitoraggio richieste pendenti.

**Prerequisiti**:
- Richieste inviate

**Passi**:
1. Navigare su `/public/portal/groups.php`
2. Visualizzare sezione richieste

**Risultato Atteso**:
- Lista richieste pendenti
- Stato visualizzato: "In attesa", "Approvata", "Rifiutata"
- Data richiesta
- Nome gruppo

---

## 6. Test Portale Soci - Pagamenti

### 6.1 Visualizzazione Quote da Pagare
**Obiettivo**: Verificare lista quote non pagate.

**Prerequisiti**:
- Quote assegnate al socio con status "pending" o "overdue"

**Passi**:
1. Navigare su `/public/portal/payments.php`

**Risultato Atteso**:
- Lista quote non pagate visibile
- Importo, scadenza, stato
- Badge "Scaduta" se overdue
- Pulsante "Paga Ora"

---

### 6.2 Visualizzazione Quote Pagate
**Obiettivo**: Verificare storico pagamenti.

**Prerequisiti**:
- Quote con status "paid"

**Passi**:
1. Navigare su `/public/portal/payments.php`
2. Visualizzare sezione "Quote Pagate"

**Risultato Atteso**:
- Lista quote pagate
- Data pagamento
- Metodo di pagamento
- Link a ricevuta (se disponibile)
- Badge "Pagata" verde

---

### 6.3 Pagamento Tramite Bonifico (Dichiarazione)
**Obiettivo**: Verificare dichiarazione pagamento offline.

**Prerequisiti**:
- Quota da pagare
- Coordinate bancarie configurate

**Passi**:
1. Cliccare "Paga Ora" su una quota
2. Selezionare "Bonifico Bancario"
3. Visualizzare coordinate bancarie
4. Confermare di aver effettuato il bonifico
5. Inserire data e causale
6. Cliccare "Conferma Pagamento"

**Risultato Atteso**:
- Status quota cambiato a "pending_confirmation"
- Record pagamento creato
- Messaggio "In attesa conferma admin"
- Admin notificato (opzionale)

---

### 6.4 Pagamento Tramite PayPal Smart Buttons
**Obiettivo**: Verificare pagamento online con PayPal.

**Prerequisiti**:
- PayPal configurato (Client ID)
- Quota da pagare

**Passi**:
1. Cliccare "Paga Ora" su una quota
2. Selezionare "PayPal"
3. Cliccare sul PayPal Smart Button
4. Completare pagamento su PayPal
5. Tornare al sito

**Risultato Atteso**:
- Transazione PayPal creata
- Status quota aggiornato a "paid"
- Transaction ID salvato
- Ricevuta generata automaticamente
- Messaggio di conferma

---

### 6.5 Quote in Attesa di Conferma
**Obiettivo**: Verificare visualizzazione quote pending.

**Prerequisiti**:
- Quote con status "pending_confirmation"

**Passi**:
1. Navigare su `/public/portal/payments.php`
2. Visualizzare sezione "In Attesa Conferma"

**Risultato Atteso**:
- Quote con status pending_confirmation elencate
- Badge giallo "In attesa conferma"
- Dettagli pagamento dichiarato
- Impossibile ripagare

---

### 6.6 Visualizzazione Ricevute
**Obiettivo**: Verificare accesso alle ricevute.

**Prerequisiti**:
- Quote pagate con ricevuta generata

**Passi**:
1. Navigare su `/public/portal/receipts.php`

**Risultato Atteso**:
- Lista ricevute disponibili
- Numero ricevuta
- Data emissione
- Importo
- Link download PDF

---

### 6.7 Download PDF Ricevuta
**Obiettivo**: Verificare generazione e download PDF.

**Prerequisiti**:
- Ricevuta disponibile

**Passi**:
1. Navigare su `/public/portal/receipts.php`
2. Cliccare "Scarica PDF" su una ricevuta
3. O navigare su `/public/receipt.php?token=xxx`

**Risultato Atteso**:
- PDF generato con TCPDF
- Contenuto: dati associazione, socio, importo
- Download automatico
- Token verificato per sicurezza
- Token non valido = errore 404

---

## 7. Test Admin - Gestione Soci

### 7.1 Lista Soci con Filtri
**Obiettivo**: Verificare ricerca e filtri soci.

**Prerequisiti**:
- Admin loggato
- Soci presenti nel database

**Passi**:
1. Navigare su `/public/members.php`
2. Applicare filtro stato (Attivo/Sospeso/Cessato)
3. Ricerca per nome/cognome
4. Ricerca per codice fiscale

**Risultato Atteso**:
- Filtri applicati correttamente
- Risultati filtrati visualizzati
- Paginazione funzionante
- Contatore totale soci

---

### 7.2 Aggiunta Nuovo Socio
**Obiettivo**: Verificare creazione nuovo socio.

**Prerequisiti**:
- Admin loggato

**Passi**:
1. Navigare su `/public/member_edit.php`
2. Compilare tutti i campi obbligatori
3. Inserire codice fiscale valido
4. Cliccare "Salva"

**Risultato Atteso**:
- Socio creato nel database
- Numero tessera generato (se auto)
- Validazione campi obbligatori
- Messaggio di conferma
- Redirect a lista soci

---

### 7.3 Modifica Socio Esistente
**Obiettivo**: Verificare aggiornamento dati socio.

**Prerequisiti**:
- Socio esistente

**Passi**:
1. Navigare su `/public/member_edit.php?id=X`
2. Modificare dati (email, telefono, indirizzo)
3. Cliccare "Salva"

**Risultato Atteso**:
- Dati aggiornati nel database
- Timestamp `updated_at` aggiornato
- Messaggio di conferma
- Audit log registrato (se abilitato)

---

### 7.4 Validazione Codice Fiscale
**Obiettivo**: Verificare validazione CF italiano.

**Prerequisiti**:
- Admin in form modifica socio

**Passi**:
1. Inserire CF valido (es: RSSMRA85T10A562S)
2. Inserire CF non valido (es: INVALID123)
3. Tentare salvataggio

**Risultato Atteso**:
- CF valido accettato
- CF non valido rifiutato
- Messaggio di errore specifico
- Form non salvato se CF invalido

---

### 7.5 Import Soci da CSV
**Obiettivo**: Verificare import massivo soci.

**Prerequisiti**:
- Admin loggato
- File CSV preparato con formato corretto

**Passi**:
1. Navigare su `/public/import_members.php`
2. Caricare file CSV
3. Verificare anteprima
4. Confermare import

**Risultato Atteso**:
- Parsing CSV corretto
- Soci importati nel database
- Validazione CF per ogni riga
- Report errori e successi
- Righe con errori saltate

---

### 7.6 Invio Email Attivazione Portale
**Obiettivo**: Verificare invio link attivazione.

**Prerequisiti**:
- Socio senza account portale attivato

**Passi**:
1. Navigare su scheda socio
2. Cliccare "Invia Email Attivazione Portale"
3. Confermare invio

**Risultato Atteso**:
- Token generato in `members.portal_token`
- Email inviata con link
- Scadenza token impostata (24h)
- Messaggio di conferma
- Email contiene link a register.php

---

## 8. Test Admin - Gestione Quote

### 8.1 Creazione Quota Singola
**Obiettivo**: Verificare assegnazione quota a singolo socio.

**Prerequisiti**:
- Admin loggato
- Anno sociale configurato

**Passi**:
1. Navigare su `/public/member_fees.php?member_id=X`
2. Cliccare "Aggiungi Quota"
3. Selezionare anno sociale
4. Inserire importo
5. Impostare scadenza
6. Cliccare "Salva"

**Risultato Atteso**:
- Quota creata in `member_fees`
- Status impostato a "pending"
- Socio notificato (opzionale)
- Quota visibile nel portale socio

---

### 8.2 Rinnovo Massivo Quote
**Obiettivo**: Verificare creazione quote per tutti i soci attivi.

**Prerequisiti**:
- Admin loggato
- Soci attivi nel database

**Passi**:
1. Navigare su `/public/bulk_fees.php`
2. Selezionare anno sociale
3. Inserire importo standard
4. Selezionare soci (tutti/filtrati)
5. Cliccare "Crea Quote"

**Risultato Atteso**:
- Quote create per tutti i soci selezionati
- Status "pending"
- Scadenza impostata
- Report quote create
- Soci già con quota non duplicati

---

### 8.3 Registrazione Pagamento Manuale
**Obiettivo**: Verificare registrazione pagamento diretto.

**Prerequisiti**:
- Quota esistente con status "pending"

**Passi**:
1. Navigare su `/public/member_fees.php?member_id=X`
2. Cliccare "Registra Pagamento" su quota
3. Selezionare metodo (Contante/Bonifico/PayPal)
4. Inserire data e riferimento
5. Cliccare "Conferma"

**Risultato Atteso**:
- Status quota cambiato a "paid"
- Data pagamento registrata
- Metodo e riferimento salvati
- Ricevuta generata
- Movimento finanziario creato (opzionale)

---

### 8.4 Conferma Pagamento Offline (Bonifico)
**Obiettivo**: Verificare conferma pagamenti dichiarati dai soci.

**Prerequisiti**:
- Quota con status "pending_confirmation"

**Passi**:
1. Navigare su `/public/payment_confirm.php`
2. Visualizzare lista pagamenti da confermare
3. Verificare dettagli bonifico
4. Cliccare "Conferma"

**Risultato Atteso**:
- Status quota cambiato a "paid"
- Ricevuta generata
- Socio notificato (opzionale)
- Movimento registrato

---

### 8.5 Generazione Ricevuta
**Obiettivo**: Verificare creazione PDF ricevuta.

**Prerequisiti**:
- Quota pagata

**Passi**:
1. Navigare su dettaglio quota pagata
2. Cliccare "Genera Ricevuta"
3. Visualizzare/scaricare PDF

**Risultato Atteso**:
- PDF generato con TCPDF
- Dati completi: associazione, socio, importo
- Numero ricevuta progressivo
- Token sicuro generato
- Ricevuta accessibile dal portale socio

---

### 8.6 Export Morosi
**Obiettivo**: Verificare export soci con quote scadute.

**Prerequisiti**:
- Quote con status "overdue"

**Passi**:
1. Navigare su `/public/members.php` o report
2. Cliccare "Export Morosi"
3. Scaricare CSV/Excel

**Risultato Atteso**:
- File generato con soci con quote scadute
- Campi: nome, email, quota, scadenza
- Solo soci attivi
- Formato CSV o Excel

---

## 9. Test Admin - Gestione Eventi

### 9.1 Creazione Evento
**Obiettivo**: Verificare creazione nuovo evento.

**Prerequisiti**:
- Admin loggato

**Passi**:
1. Navigare su `/public/event_edit.php`
2. Inserire titolo
3. Inserire descrizione (opzionale)
4. Selezionare data/ora
5. Selezionare modalità (in persona/online/ibrido)
6. Compilare campi specifici modalità
7. Cliccare "Salva"

**Risultato Atteso**:
- Evento creato con status "draft"
- Campi validati
- Messaggio di conferma
- Redirect a lista eventi

---

### 9.2 Modifica Evento
**Obiettivo**: Verificare aggiornamento evento esistente.

**Prerequisiti**:
- Evento esistente

**Passi**:
1. Navigare su `/public/event_edit.php?id=X`
2. Modificare campi
3. Cliccare "Salva"

**Risultato Atteso**:
- Evento aggiornato
- Timestamp `updated_at` aggiornato
- Iscritti notificati se cambi rilevanti (opzionale)
- Messaggio di conferma

---

### 9.3 Pubblicazione Evento
**Obiettivo**: Verificare cambio stato a published.

**Prerequisiti**:
- Evento in stato "draft"

**Passi**:
1. Visualizzare evento
2. Cliccare "Pubblica"
3. Confermare

**Risultato Atteso**:
- Status cambiato a "published"
- Evento visibile nel portale soci
- Notifiche inviate ai soci target (opzionale)
- Badge stato aggiornato

---

### 9.4 Target: Tutti i Soci
**Obiettivo**: Verificare evento aperto a tutti.

**Prerequisiti**:
- Creazione nuovo evento

**Passi**:
1. In form evento, selezionare target "Tutti i soci"
2. Salvare evento
3. Verificare nel portale socio

**Risultato Atteso**:
- Evento visibile a tutti i soci loggati
- Nessun filtro per gruppo
- Tutti i soci possono iscriversi

---

### 9.5 Target: Gruppi Specifici
**Obiettivo**: Verificare evento ristretto a gruppi.

**Prerequisiti**:
- Gruppi esistenti
- Creazione nuovo evento

**Passi**:
1. Selezionare target "Gruppi specifici"
2. Selezionare uno o più gruppi
3. Salvare evento
4. Verificare visibilità nel portale

**Risultato Atteso**:
- Evento visibile solo a membri dei gruppi selezionati
- Altri soci non vedono l'evento
- Badge gruppo visibile

---

### 9.6 Visualizzazione Iscritti/Disponibilità
**Obiettivo**: Verificare report iscrizioni evento.

**Prerequisiti**:
- Evento con iscrizioni

**Passi**:
1. Navigare su `/public/event_view.php?id=X`
2. Visualizzare tab "Iscritti"

**Risultato Atteso**:
- Lista iscritti per stato (Sì/Forse/No)
- Contatori totali
- Dati socio per ogni iscritto
- Export lista possibile

---

### 9.7 Rimozione Risposta Socio da Evento (NUOVO)
**Obiettivo**: Verificare cancellazione iscrizione da parte admin.

**Prerequisiti**:
- Evento con iscrizioni

**Passi**:
1. Navigare su `/public/event_view.php?id=X`
2. Identificare un iscritto
3. Cliccare "Rimuovi" accanto al socio
4. Confermare cancellazione

**Risultato Atteso**:
- Record eliminato da `event_registrations`
- Contatori aggiornati
- Socio può iscriversi nuovamente
- Messaggio di conferma

---

## 10. Test Admin - Gestione Gruppi

### 10.1 Creazione Gruppo
**Obiettivo**: Verificare creazione nuovo gruppo.

**Prerequisiti**:
- Admin loggato

**Passi**:
1. Navigare su `/public/member_groups.php`
2. Cliccare "Nuovo Gruppo"
3. Inserire nome e descrizione
4. Selezionare colore
5. Impostare flag (nascosto/ristretto)
6. Cliccare "Salva"

**Risultato Atteso**:
- Gruppo creato nel database
- Colore e flag salvati
- Messaggio di conferma
- Gruppo visibile in lista

---

### 10.2 Modifica Gruppo
**Obiettivo**: Verificare aggiornamento gruppo esistente.

**Prerequisiti**:
- Gruppo esistente

**Passi**:
1. Cliccare "Modifica" su un gruppo
2. Cambiare nome/descrizione
3. Modificare flag
4. Cliccare "Salva"

**Risultato Atteso**:
- Gruppo aggiornato
- Flag riflessi in portale socio
- Messaggio di conferma

---

### 10.3 Flag Nascosto (is_hidden)
**Obiettivo**: Verificare comportamento flag nascosto.

**Prerequisiti**:
- Gruppo esistente

**Passi**:
1. Modificare gruppo e attivare "Nascosto"
2. Salvare
3. Verificare in portale socio

**Risultato Atteso**:
- Gruppo NON visibile in portale
- Non appare in "Gruppi Disponibili"
- Solo admin può gestirlo
- Membri esistenti rimangono nel gruppo

---

### 10.4 Flag Ristretto (is_restricted)
**Obiettivo**: Verificare comportamento flag ristretto.

**Prerequisiti**:
- Gruppo esistente

**Passi**:
1. Modificare gruppo e attivare "Ristretto"
2. Salvare
3. Verificare in portale socio

**Risultato Atteso**:
- Gruppo NON richiedibile dai soci
- Visibile ma senza pulsante "Richiedi"
- Solo admin può aggiungere membri
- Nota esplicativa visibile

---

### 10.5 Approvazione Richiesta Partecipazione
**Obiettivo**: Verificare approvazione richiesta socio.

**Prerequisiti**:
- Richieste pendenti in `member_group_requests`

**Passi**:
1. Navigare su `/public/group_requests.php`
2. Visualizzare richieste pendenti
3. Cliccare "Approva" su una richiesta
4. Confermare

**Risultato Atteso**:
- Status richiesta cambiato a "approved"
- Socio aggiunto a `member_group_members`
- Socio notificato (opzionale)
- Gruppo visibile nel portale del socio

---

### 10.6 Rifiuto Richiesta Partecipazione
**Obiettivo**: Verificare rifiuto richiesta.

**Prerequisiti**:
- Richieste pendenti

**Passi**:
1. Navigare su `/public/group_requests.php`
2. Cliccare "Rifiuta" su una richiesta
3. Opzionalmente inserire motivazione
4. Confermare

**Risultato Atteso**:
- Status richiesta cambiato a "rejected"
- Socio NON aggiunto al gruppo
- Socio notificato con motivazione (opzionale)
- Richiesta archiviata

---

### 10.7 Visualizzazione Membri Gruppo (NUOVO)
**Obiettivo**: Verificare lista membri di un gruppo.

**Prerequisiti**:
- Gruppo con membri

**Passi**:
1. Navigare su `/public/member_group_members.php?group_id=X`
2. Visualizzare lista membri

**Risultato Atteso**:
- Lista completa membri del gruppo
- Dati socio: nome, tessera, email
- Data iscrizione al gruppo
- Pulsante "Rimuovi" per ogni membro

---

### 10.8 Aggiunta Manuale Membro a Gruppo (NUOVO)
**Obiettivo**: Verificare aggiunta diretta membro da admin.

**Prerequisiti**:
- Admin loggato
- Gruppo esistente

**Passi**:
1. Navigare su `/public/member_group_members.php?group_id=X`
2. Cliccare "Aggiungi Membro"
3. Selezionare socio dal dropdown
4. Cliccare "Aggiungi"

**Risultato Atteso**:
- Socio aggiunto a `member_group_members`
- Nessuna richiesta necessaria
- Gruppo visibile nel portale del socio
- Messaggio di conferma

---

### 10.9 Rimozione Membro da Gruppo (NUOVO)
**Obiettivo**: Verificare rimozione membro da gruppo.

**Prerequisiti**:
- Gruppo con membri

**Passi**:
1. Navigare su `/public/member_group_members.php?group_id=X`
2. Cliccare "Rimuovi" accanto a un membro
3. Confermare rimozione

**Risultato Atteso**:
- Record eliminato da `member_group_members`
- Gruppo non più visibile al socio
- Eventi del gruppo non più accessibili
- Messaggio di conferma

---

## 11. Test Finanziari

### 11.1 Registrazione Entrata
**Obiettivo**: Verificare creazione movimento di entrata.

**Prerequisiti**:
- Admin loggato
- Categorie entrate configurate

**Passi**:
1. Navigare su `/public/finance.php`
2. Selezionare tab "Entrate"
3. Cliccare "Nuova Entrata"
4. Compilare: importo, categoria, data, metodo
5. Opzionalmente collegare a socio
6. Cliccare "Salva"

**Risultato Atteso**:
- Entrata salvata in `income`
- Anno sociale assegnato automaticamente
- Messaggio di conferma
- Visibile in report economico

---

### 11.2 Registrazione Uscita
**Obiettivo**: Verificare creazione movimento di uscita.

**Prerequisiti**:
- Admin loggato
- Categorie uscite configurate

**Passi**:
1. Navigare su `/public/finance.php`
2. Selezionare tab "Uscite"
3. Cliccare "Nuova Uscita"
4. Compilare: importo, categoria, data, metodo
5. Cliccare "Salva"

**Risultato Atteso**:
- Uscita salvata in `expenses`
- Anno sociale assegnato
- Messaggio di conferma
- Visibile in report economico

---

### 11.3 Collegamento Entrata-Socio
**Obiettivo**: Verificare associazione entrata a socio.

**Prerequisiti**:
- Creazione entrata

**Passi**:
1. In form nuova entrata
2. Selezionare "Collegata a socio"
3. Scegliere socio dal dropdown
4. Salvare

**Risultato Atteso**:
- Campo `member_id` popolato
- Entrata visibile in scheda socio
- Report filtrabili per socio

---

### 11.4 Report Economico per Anno
**Obiettivo**: Verificare generazione rendiconto.

**Prerequisiti**:
- Movimenti registrati
- Anno sociale configurato

**Passi**:
1. Navigare su `/public/reports.php`
2. Selezionare anno sociale
3. Visualizzare report

**Risultato Atteso**:
- Totali entrate per categoria
- Totali uscite per categoria
- Calcolo saldo (entrate - uscite)
- Percentuali visualizzate
- Grafici a barre

---

### 11.5 Export Excel
**Obiettivo**: Verificare export rendiconto in Excel/CSV.

**Prerequisiti**:
- Report generato

**Passi**:
1. Visualizzare report
2. Cliccare "Export Excel"
3. Scaricare file

**Risultato Atteso**:
- File CSV/Excel generato
- Dati completi: categoria, importo, percentuale
- Formato apribile in Excel/LibreOffice
- Nome file con anno e data

---

## 12. Test Email

### 12.1 Invio Email Singola
**Obiettivo**: Verificare invio email a singolo socio.

**Prerequisiti**:
- Admin loggato
- SMTP configurato

**Passi**:
1. Navigare su scheda socio
2. Cliccare "Invia Email"
3. Compilare oggetto e corpo
4. Cliccare "Invia"

**Risultato Atteso**:
- Email inviata tramite SMTP
- Log email salvato in `email_log`
- Messaggio di conferma o errore
- Email ricevuta dal destinatario

---

### 12.2 Invio Email Massiva
**Obiettivo**: Verificare invio email a più soci.

**Prerequisiti**:
- Admin loggato
- Più soci nel database

**Passi**:
1. Navigare su `/public/mass_email.php`
2. Selezionare destinatari (tutti/filtrati)
3. Compilare oggetto e corpo
4. Cliccare "Invia"

**Risultato Atteso**:
- Batch creato in `mass_email_batches`
- Email accodate in `email_queue`
- Invio progressivo (limite AlterVista)
- Report invio visibile

---

### 12.3 Coda Email (Limite AlterVista)
**Obiettivo**: Verificare gestione coda con limiti hosting.

**Prerequisiti**:
- Email massive in coda

**Passi**:
1. Inviare email massive (>10 destinatari)
2. Osservare processo di invio
3. Verificare log

**Risultato Atteso**:
- Email inviate in batch (es: 10 per minuto)
- Status "pending" → "sent"
- Pause automatiche per evitare limiti
- Retry su errori

---

### 12.4 Notifiche Automatiche
**Obiettivo**: Verificare invio automatico notifiche.

**Prerequisiti**:
- Template email configurati

**Passi**:
1. Creare nuova quota per socio
2. Confermare pagamento
3. Approvare richiesta gruppo
4. Pubblicare evento

**Risultato Atteso**:
- Email automatiche inviate
- Template compilati con dati corretti
- Log salvato
- Soci ricevono notifiche appropriate

---

## Note Finali

### Strumenti di Test
- Test manuali via browser
- Validazione con tool sviluppatore (Network, Console)
- Verifica database con phpMyAdmin o client SQL
- Log applicativo per debug

### Copertura
Questo test plan copre tutte le funzionalità principali di AssoLife:
- ✅ Autenticazione (admin e soci)
- ✅ Gestione soci
- ✅ Portale soci completo
- ✅ Eventi e iscrizioni
- ✅ Gruppi e richieste
- ✅ Pagamenti (online e offline)
- ✅ Finanza e report
- ✅ Email e notifiche

### Priorità Test
1. **Critici**: Autenticazione, pagamenti, sicurezza
2. **Alti**: Gestione soci, quote, eventi
3. **Medi**: Gruppi, email, report
4. **Bassi**: Export, notifiche automatiche

---

## 13. Test Workflow Approvazione Iscrizioni Eventi

### 13.1 Socio: Dare Disponibilità "Sì"
**Obiettivo**: Verificare che un socio possa dare disponibilità a un evento e che questa entri in stato "pending".

**Prerequisiti**:
- Socio loggato nel portale
- Evento pubblicato disponibile

**Passi**:
1. Navigare su `/public/portal/events.php`
2. Visualizzare un evento disponibile
3. Cliccare sul pulsante "✅ Parteciperò"
4. Verificare messaggio di conferma

**Risultato Atteso**:
- Risposta salvata con successo
- Badge "⏳ In attesa di approvazione" visibile sotto la scelta
- Messaggio "La tua disponibilità è stata registrata e sarà valutata dall'organizzatore"
- Contatore "Sì" incrementato

---

### 13.2 Admin: Visualizzare Disponibilità in Attesa
**Obiettivo**: Verificare che l'admin veda le disponibilità in attesa nella sezione dedicata.

**Prerequisiti**:
- Admin loggato
- Almeno una disponibilità "Sì" in stato pending

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Scorrere alla sezione "Disponibilità in Attesa"

**Risultato Atteso**:
- Card con header giallo "Disponibilità in Attesa"
- Badge con numero di disponibilità pending
- Tabella con lista soci che hanno dato disponibilità
- Pulsanti "Approva" e "Rifiuta" per ogni riga
- Pulsanti "Approva Tutti" e "Rifiuta Tutti" visibili

---

### 13.3 Admin: Approvare Singola Iscrizione
**Obiettivo**: Verificare che l'admin possa approvare una singola disponibilità.

**Prerequisiti**:
- Admin loggato
- Disponibilità in stato pending

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Nella sezione "Disponibilità in Attesa", cliccare pulsante verde "✓"
3. Confermare l'azione

**Risultato Atteso**:
- Messaggio "Iscrizione approvata con successo"
- La disponibilità scompare dalla sezione "In Attesa"
- La disponibilità appare nella sezione "Iscritti Confermati" (card verde)
- Tabella mostra: socio, email, tessera, chi ha approvato, data approvazione

---

### 13.4 Admin: Rifiutare Singola Iscrizione con Motivo
**Obiettivo**: Verificare che l'admin possa rifiutare una disponibilità con motivo.

**Prerequisiti**:
- Admin loggato
- Disponibilità in stato pending

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Nella sezione "Disponibilità in Attesa", cliccare pulsante rosso "✗"
3. Nel modal inserire motivo: "Posti esauriti"
4. Confermare

**Risultato Atteso**:
- Messaggio "Iscrizione rifiutata"
- La disponibilità scompare dalla sezione "In Attesa"
- La disponibilità appare nella sezione "Rifiutati" (card rossa)
- Motivo visibile nella tabella

---

### 13.5 Admin: Approva Tutti (Bulk)
**Obiettivo**: Verificare l'approvazione in blocco di tutte le disponibilità "Sì" pending.

**Prerequisiti**:
- Admin loggato
- Almeno 2 disponibilità "Sì" in stato pending

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Cliccare "Approva Tutti"
3. Confermare nel modal

**Risultato Atteso**:
- Messaggio "N iscrizioni approvate" (dove N = numero approvate)
- Tutte le disponibilità "Sì" passano a "Iscritti Confermati"
- Sezione "Disponibilità in Attesa" vuota o con solo "Forse/No"

---

### 13.6 Admin: Rifiuta Tutti con Motivo (Bulk)
**Obiettivo**: Verificare il rifiuto in blocco con motivo comune.

**Prerequisiti**:
- Admin loggato
- Disponibilità in stato pending

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Cliccare "Rifiuta Tutti"
3. Nel modal inserire motivo: "Evento annullato"
4. Confermare

**Risultato Atteso**:
- Messaggio "N iscrizioni rifiutate"
- Tutte le pending passano a "Rifiutati"
- Motivo "Evento annullato" visibile per tutte

---

### 13.7 Admin: Revocare Iscrizione Approvata
**Obiettivo**: Verificare che l'admin possa riportare un'iscrizione approvata in stato pending.

**Prerequisiti**:
- Admin loggato
- Iscrizione in stato "approved"

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Nella sezione "Iscritti Confermati", cliccare pulsante "↶" (revoca)
3. Confermare

**Risultato Atteso**:
- Messaggio "Iscrizione revocata"
- L'iscrizione scompare da "Iscritti Confermati"
- L'iscrizione riappare in "Disponibilità in Attesa"
- approved_by e approved_at azzerati

---

### 13.8 Admin: Revocare Iscrizione Rifiutata
**Obiettivo**: Verificare che l'admin possa riportare un rifiutato in pending.

**Prerequisiti**:
- Admin loggato
- Iscrizione in stato "rejected"

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Nella sezione "Rifiutati", cliccare pulsante "↶" (revoca)
3. Confermare

**Risultato Atteso**:
- Messaggio "Iscrizione revocata"
- L'iscrizione scompare da "Rifiutati"
- L'iscrizione riappare in "Disponibilità in Attesa"
- rejection_reason azzerato

---

### 13.9 Socio: Visualizzare Stato "In Attesa"
**Obiettivo**: Verificare che il socio veda il badge "In attesa" dopo aver dato disponibilità.

**Prerequisiti**:
- Socio loggato nel portale
- Disponibilità "Sì" data ma non ancora approvata

**Passi**:
1. Navigare su `/public/portal/events.php`
2. Verificare l'evento

**Risultato Atteso**:
- Badge giallo "⏳ In attesa di approvazione"
- Messaggio "La tua disponibilità è stata registrata e sarà valutata dall'organizzatore"

---

### 13.10 Socio: Visualizzare Stato "Approvata"
**Obiettivo**: Verificare che il socio veda il badge "Confermata" dopo approvazione.

**Prerequisiti**:
- Socio loggato nel portale
- Disponibilità approvata dall'admin

**Passi**:
1. Navigare su `/public/portal/events.php`
2. Verificare l'evento

**Risultato Atteso**:
- Badge verde "✅ Iscrizione confermata"
- Messaggio "La tua partecipazione è stata approvata!"

---

### 13.11 Socio: Visualizzare Stato "Rifiutata" con Motivo
**Obiettivo**: Verificare che il socio veda il motivo del rifiuto.

**Prerequisiti**:
- Socio loggato nel portale
- Disponibilità rifiutata con motivo

**Passi**:
1. Navigare su `/public/portal/events.php`
2. Verificare l'evento

**Risultato Atteso**:
- Badge rosso "❌ Iscrizione rifiutata"
- Messaggio "Motivo: [motivo inserito dall'admin]"

---

### 13.12 Database: Verificare Struttura Tabella
**Obiettivo**: Verificare che la migrazione abbia creato correttamente le colonne.

**Prerequisiti**:
- Accesso al database
- Migrazione eseguita

**Passi**:
1. Eseguire query: `DESCRIBE event_responses;`

**Risultato Atteso**:
Colonne presenti:
- `registration_status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
- `approved_by` INT NULL
- `approved_at` DATETIME NULL
- `rejection_reason` VARCHAR(255) NULL
- Foreign key `fk_event_responses_approved_by` su `approved_by → users(id)`

---

### 13.13 Retrocompatibilità: Risposte Esistenti
**Obiettivo**: Verificare che le risposte pre-esistenti funzionino correttamente.

**Prerequisiti**:
- Database con risposte esistenti prima della migrazione
- Migrazione eseguita

**Passi**:
1. Verificare che tutte le risposte abbiano `registration_status = 'pending'` di default
2. Admin può approvarle normalmente

**Risultato Atteso**:
- Nessun errore nel caricamento delle pagine
- Le risposte esistenti sono tutte in stato "pending"
- L'admin può gestirle normalmente

---

### 13.14 Sicurezza: CSRF Protection
**Obiettivo**: Verificare che le azioni di approvazione siano protette da CSRF.

**Prerequisiti**:
- Admin loggato

**Passi**:
1. Tentare POST a `/public/event_view.php` con action=approve senza token CSRF valido
2. Verificare che l'azione sia bloccata

**Risultato Atteso**:
- Richiesta bloccata
- Nessuna modifica al database

---

### 13.15 Permessi: Solo Admin
**Obiettivo**: Verificare che solo admin possa approvare/rifiutare.

**Prerequisiti**:
- Utente operatore loggato (non admin)

**Passi**:
1. Tentare di accedere a `/public/event_view.php`
2. Verificare che le sezioni di approvazione non siano visibili

**Risultato Atteso**:
- Sezioni "Disponibilità in Attesa", "Iscritti Confermati", "Rifiutati" NON visibili
- Solo riepilogo disponibilità visibile

---

### 13.16 Riepilogo Disponibilità
**Obiettivo**: Verificare che il riepilogo mostri sempre i conteggi corretti.

**Prerequisiti**:
- Admin loggato
- Mix di risposte: Sì (3), Forse (2), No (1)

**Passi**:
1. Navigare su `/public/event_view.php?id=[event_id]`
2. Verificare card "Riepilogo Disponibilità"

**Risultato Atteso**:
- ✓ Parteciperò: 3
- ? Forse: 2
- ✗ Non parteciperò: 1
- I conteggi NON cambiano in base allo stato di approvazione (contano tutte le risposte)

---

**Priorità Test**: Alta  
**Area**: Eventi - Workflow Approvazione

---

**AssoLife Testing Documentation v1.0**  
*Powered by Luigi Pistarà*

# Portale Soci - Parte 2: Implementazione Completata

## Panoramica

Questa implementazione completa il portale soci con le funzionalità rimanenti come specificato nei requisiti.

## Funzionalità Implementate

### 1. Eventi e Disponibilità

**Pagina**: `public/portal/events.php`

- I soci possono visualizzare tutti gli eventi disponibili (tutti i soci + gruppi di appartenenza)
- Possibilità di dare disponibilità per ogni evento: Sì/No/Forse
- Aggiornamento in tempo reale dei conteggi tramite AJAX
- Visualizzazione dettagli evento (data, ora, luogo, modalità)

**Tabella Database**: `event_responses`
```sql
- id (PK)
- event_id (FK)
- member_id (FK)
- response (ENUM: yes/no/maybe)
- notes
- responded_at
- updated_at
```

**Funzioni Helper**:
- `getMemberVisibleEvents($memberId, $upcomingOnly = true)` - Ottiene eventi visibili al socio
- `getMemberEventResponse($eventId, $memberId)` - Ottiene risposta del socio
- `setMemberEventResponse($eventId, $memberId, $response, $notes)` - Salva risposta
- `getEventResponses($eventId)` - Ottiene tutte le risposte (admin)
- `countEventResponses($eventId)` - Conta risposte per tipo

**Vista Admin**: Le risposte vengono mostrate nella pagina `event_view.php` con conteggi e lista dettagliata.

---

### 2. Gruppi e Richieste

**Pagina**: `public/portal/groups.php`

- Visualizzazione gruppi di appartenenza
- Lista gruppi pubblici disponibili (esclusi nascosti e ristretti)
- Richiesta di partecipazione con messaggio opzionale
- Stato richieste (pending/approved/rejected)

**Pagina Admin**: `public/group_requests.php`

- Lista richieste pendenti
- Approvazione/rifiuto con note
- Badge notifica nel menu admin

**Funzioni Helper**:
- `getPublicGroups()` - Gruppi visibili (not hidden, not restricted)
- `createGroupRequest($memberId, $groupId, $message)` - Crea richiesta
- `getMemberGroupRequests($memberId)` - Richieste del socio
- `getPendingGroupRequests()` - Richieste pendenti (admin)
- `countPendingGroupRequests()` - Contatore per badge
- `approveGroupRequest($requestId, $adminId, $notes)` - Approva e aggiunge al gruppo
- `rejectGroupRequest($requestId, $adminId, $notes)` - Rifiuta

---

### 3. Pagamenti Quote

**Pagina**: `public/portal/payments.php`

Due modalità di pagamento:

#### A. Bonifico Bancario (Offline)
1. Socio visualizza coordinate IBAN (da settings)
2. Socio dichiara di aver effettuato bonifico
3. Sistema imposta `payment_pending = 1`
4. Admin riceve notifica (badge)
5. Admin conferma in `payment_confirm.php`
6. Sistema genera ricevuta e movimento finanziario

#### B. PayPal (Online)
1. Integrazione PayPal Smart Buttons
2. Pagamento istantaneo
3. Conferma tramite API `paypal_confirm.php`
4. Generazione automatica ricevuta
5. Creazione movimento finanziario

**Pagina Admin**: `public/payment_confirm.php`

- Lista pagamenti in attesa di conferma
- Conferma/rifiuto con verifica
- Badge notifica nel menu admin

**API Endpoints**:
- `public/portal/api/paypal_confirm.php` - Conferma pagamento PayPal
- `public/api/paypal_webhook.php` - Webhook per notifiche asincrone PayPal

**Funzioni Helper**:
- `confirmOfflinePayment($feeId, $adminId)` - Conferma pagamento offline
- `countPendingPayments()` - Contatore per badge
- `generateReceiptToken($receiptId, $memberId)` - Token sicuro per PDF

---

### 4. Ricevute

**Pagina**: `public/portal/receipts.php`

- Lista ricevute pagate
- Visualizzazione/download PDF con token sicuro
- Informazioni dettagliate (numero, data, importo, metodo)
- Tracciamento transazione PayPal

---

## Modifiche Database

### Schema Aggiornato
File `schema.sql` aggiornato con tabella `event_responses`

### Migration
File `migrations/002_portal_soci_parte_2.sql` per installazioni esistenti

```sql
CREATE TABLE IF NOT EXISTS event_responses (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_id INT NOT NULL,
    member_id INT NOT NULL,
    response ENUM('yes', 'no', 'maybe') NOT NULL,
    notes TEXT,
    responded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_response (event_id, member_id),
    INDEX idx_event (event_id),
    INDEX idx_member (member_id),
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE
);
```

---

## Aggiornamenti UI

### Menu Portale Soci (`public/portal/inc/header.php`)

Aggiunte voci menu:
- 📅 Eventi
- 👥 Gruppi
- 💳 Pagamenti
- 🧾 Ricevute

### Menu Admin (`public/inc/header.php`)

Aggiunte voci menu con badge notifiche:
- ➕ Richieste Gruppi (badge rosso se pending)
- 💰 Conferma Pagamenti (badge giallo se pending)

### Event View (`public/event_view.php`)

Aggiunta sezione "Disponibilità Soci" per admin con:
- Conteggi risposte per tipo
- Lista dettagliata risposte con nomi e date

---

## Configurazione Richiesta

### Settings da configurare:

#### Per Bonifici:
- `bank_name` - Nome intestatario conto
- `bank_iban` - IBAN completo

#### Per PayPal:
- `paypal_enabled` - Abilita/disabilita PayPal (1/0)
- `paypal_client_id` - Client ID da PayPal Dashboard

### Note Implementative

1. **Eventi visibili**: Query unisce eventi "all" + eventi dei gruppi del socio
2. **Gruppi nascosti**: Esclusi dalla lista pubblica (`is_hidden = 1`)
3. **Gruppi ristretti**: Esclusi dalle richieste (`is_restricted = 1`)
4. **PayPal**: Smart Buttons per semplicità, webhook come backup
5. **Ricevute**: Solo per quote `status = 'paid'` con `receipt_number`
6. **Badge notifiche**: Aggiornamento automatico contatori nel menu admin
7. **Sicurezza**: Token per visualizzazione ricevute, validazione input
8. **Performance**: AJAX per aggiornamenti senza reload pagina

---

## File Creati

### Portal:
- `public/portal/events.php`
- `public/portal/groups.php`
- `public/portal/payments.php`
- `public/portal/receipts.php`
- `public/portal/api/paypal_confirm.php`

### Admin:
- `public/group_requests.php`
- `public/payment_confirm.php`
- `public/api/paypal_webhook.php`

### Database:
- `migrations/002_portal_soci_parte_2.sql`

---

## File Modificati

- `schema.sql` - Aggiunta tabella event_responses
- `src/functions.php` - Aggiunte funzioni helper (443 linee)
- `public/portal/inc/header.php` - Menu aggiornato
- `public/inc/header.php` - Menu admin con badge
- `public/event_view.php` - Sezione risposte eventi

---

## Testing Raccomandato

1. **Eventi**:
   - [ ] Visualizzazione eventi per socio con/senza gruppi
   - [ ] Invio risposta evento (Sì/No/Forse)
   - [ ] Aggiornamento conteggi in tempo reale
   - [ ] Visualizzazione risposte in admin

2. **Gruppi**:
   - [ ] Visualizzazione gruppi di appartenenza
   - [ ] Richiesta partecipazione gruppo
   - [ ] Approvazione/rifiuto da admin
   - [ ] Aggiunta automatica al gruppo su approvazione

3. **Pagamenti**:
   - [ ] Visualizzazione quote da pagare
   - [ ] Dichiarazione bonifico
   - [ ] Pagamento PayPal (sandbox)
   - [ ] Conferma pagamento da admin
   - [ ] Generazione ricevuta

4. **Ricevute**:
   - [ ] Visualizzazione lista ricevute
   - [ ] Download PDF con token
   - [ ] Informazioni corrette

5. **UI/UX**:
   - [ ] Badge notifiche funzionanti
   - [ ] Menu responsive
   - [ ] Messaggi successo/errore
   - [ ] Responsive design mobile

---

## Compatibilità

- ✅ PHP 7.4+
- ✅ MySQL 5.7+
- ✅ Bootstrap 5.3
- ✅ PayPal SDK JavaScript
- ✅ Compatibile con installazioni esistenti (migration)

---

## Prossimi Passi

1. Testing completo di tutte le funzionalità
2. Configurazione PayPal (se necessario)
3. Configurazione coordinate bancarie
4. Test pagamenti in sandbox
5. Deployment in produzione
6. Formazione utenti admin

---

## Note di Sicurezza

- ✅ Escape HTML output (`h()` function)
- ✅ Prepared statements per query SQL
- ✅ Validazione input lato server
- ✅ Token sicuri per ricevute
- ✅ Controlli autorizzazione per ogni azione
- ✅ Transazioni database per operazioni critiche
- ✅ Error handling e logging

---

**Data Implementazione**: 04 Gennaio 2026  
**Versione**: 1.0.0  
**Status**: ✅ Implementazione Completata

<?php
/**
 * Email Functions - SMTP and Queue Management
 * Invio email tramite SMTP (senza librerie esterne) con fallback a mail()
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Controlla se siamo su hosting AlterVista
 * 
 * @return bool True se su AlterVista
 */
function isAlterVista() {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    return (strpos($host, 'altervista.org') !== false || strpos($host, 'it.altervista.org') !== false);
}

/**
 * Verifica rate limiting email (specialmente per AlterVista)
 * 
 * @return bool True se possiamo ancora inviare email
 */
function checkEmailRateLimit() {
    global $pdo;
    
    try {
        // Conta email inviate oggi
        $stmt = $pdo->query("
            SELECT COUNT(*) as count FROM " . table('email_log') . "
            WHERE DATE(sent_at) = CURDATE() AND status = 'sent'
        ");
        $result = $stmt->fetch();
        
        // AlterVista free: 50/giorno, altrimenti 500/giorno
        $dailyLimit = isAlterVista() ? 50 : 500;
        return $result['count'] < $dailyLimit;
    } catch (PDOException $e) {
        // Se tabella non esiste ancora, permetti l'invio
        return true;
    }
}

/**
 * Invia email tramite SMTP o mail() come fallback
 * 
 * @param string $to Destinatario email
 * @param string $subject Oggetto
 * @param string $bodyHtml Corpo HTML
 * @param string|null $bodyText Corpo testo plain (opzionale)
 * @param string|null $fromEmail Email mittente (opzionale)
 * @param string|null $fromName Nome mittente (opzionale)
 * @return bool True se inviata con successo
 */
function sendEmail($to, $subject, $bodyHtml, $bodyText = null, $fromEmail = null, $fromName = null) {
    global $config;
    
    // Validazione email destinatario
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError($to, $subject, 'Email destinatario non valida');
        return false;
    }
    
    // Verifica rate limiting
    if (!checkEmailRateLimit()) {
        logEmailError($to, $subject, 'Rate limit giornaliero raggiunto');
        return false;
    }
    
    // Verifica se SMTP è abilitato tramite settings
    $smtpEnabled = getSetting('smtp_enabled') == '1';
    
    try {
        // Se su AlterVista, usa sempre sendEmailAlterVista per compatibilità
        if (isAlterVista()) {
            return sendEmailAlterVista($to, $subject, $bodyHtml);
        }
        
        if ($smtpEnabled) {
            // Invio tramite SMTP
            return sendEmailSmtp($to, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName);
        } else {
            // Fallback a mail()
            return sendEmailNative($to, $subject, $bodyHtml, $bodyText, $fromEmail, $fromName);
        }
    } catch (Exception $e) {
        logEmailError($to, $subject, $e->getMessage());
        return false;
    }
}

/**
 * Invia email su AlterVista con headers semplificati
 * AlterVista non supporta SMTP diretto e ha limitazioni su mail()
 * 
 * @param string $to Destinatario email
 * @param string $subject Oggetto
 * @param string $bodyHtml Corpo HTML
 * @return bool True se inviata con successo
 */
function sendEmailAlterVista($to, $subject, $bodyHtml) {
    // Headers semplificati per AlterVista
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    
    // AlterVista richiede From con dominio altervista
    $fromEmail = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'associazione.altervista.org');
    $headers .= "From: " . $fromEmail . "\r\n";
    
    // Usa @ per sopprimere eventuali warning
    $success = @mail($to, $subject, $bodyHtml, $headers);
    
    if ($success) {
        logEmailSuccess($to, $subject);
    } else {
        logEmailError($to, $subject, 'Invio mail() su AlterVista fallito');
    }
    
    return $success;
}

/**
 * Send email via SMTP
 */
function sendEmailSmtp($to, $subject, $bodyHtml, $bodyText = null, $fromEmail = null, $fromName = null) {
    $host = getSetting('smtp_host');
    $port = (int) getSetting('smtp_port');
    $security = getSetting('smtp_security');
    $username = getSetting('smtp_username');
    $password = getSetting('smtp_password');
    
    // Validate required settings
    if (empty($host) || empty($username) || empty($password)) {
        error_log("SMTP configuration incomplete: missing host, username, or password");
        return false;
    }
    
    if ($port <= 0 || $port > 65535) {
        error_log("SMTP port invalid: $port");
        return false;
    }
    
    $fromEmail = $fromEmail ?: getSetting('smtp_from_email') ?: $username;
    $fromName = $fromName ?: getSetting('smtp_from_name') ?: '';
    
    // Connessione SMTP
    $smtp = null;
    $errno = 0;
    $errstr = '';
    
    // Determina protocollo
    if ($security === 'ssl') {
        $smtp = @fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
    } elseif ($security === 'tls') {
        $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
    } else {
        $smtp = @fsockopen($host, $port, $errno, $errstr, 30);
    }
    
    if (!$smtp) {
        error_log("SMTP connection failed: $errstr ($errno)");
        return false;
    }
    
    // Leggi risposta iniziale
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '220') {
        fclose($smtp);
        return false;
    }
    
    // Sanitize server name to prevent header injection - allow alphanumeric, dots, and hyphens
    $serverName = preg_replace('/[^a-zA-Z0-9.-]/', '', $_SERVER['SERVER_NAME'] ?? 'localhost');
    // Validate it looks like a hostname (not empty, doesn't start/end with hyphen or dot)
    if (empty($serverName) || preg_match('/^[.-]|[.-]$/', $serverName)) {
        $serverName = 'localhost';
    }
    
    // EHLO
    fputs($smtp, "EHLO " . $serverName . "\r\n");
    $response = '';
    while ($line = fgets($smtp, 515)) {
        $response .= $line;
        if (substr($line, 3, 1) == ' ') break;
    }
    
    // STARTTLS se necessario
    if ($security === 'tls') {
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            fclose($smtp);
            return false;
        }
        
        // Use appropriate TLS method with fallback for compatibility
        $cryptoMethod = defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT') 
            ? STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLS_CLIENT
            : STREAM_CRYPTO_METHOD_TLS_CLIENT;
        
        $tlsResult = stream_socket_enable_crypto($smtp, true, $cryptoMethod);
        if (!$tlsResult) {
            error_log("SMTP TLS encryption failed");
            fclose($smtp);
            return false;
        }
        
        // EHLO di nuovo dopo STARTTLS
        fputs($smtp, "EHLO " . $serverName . "\r\n");
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) == ' ') break;
        }
    }
    
    // AUTH LOGIN
    fputs($smtp, "AUTH LOGIN\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode($username) . "\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '334') {
        fclose($smtp);
        return false;
    }
    
    fputs($smtp, base64_encode($password) . "\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '235') {
        error_log("SMTP auth failed: $response");
        fclose($smtp);
        return false;
    }
    
    // MAIL FROM
    fputs($smtp, "MAIL FROM:<$fromEmail>\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '250') {
        fclose($smtp);
        return false;
    }
    
    // RCPT TO
    fputs($smtp, "RCPT TO:<$to>\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '250') {
        fclose($smtp);
        return false;
    }
    
    // DATA
    fputs($smtp, "DATA\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '354') {
        fclose($smtp);
        return false;
    }
    
    // Sanitize subject and to address to prevent header injection
    $subject = str_replace(["\r", "\n"], '', $subject);
    $to = str_replace(["\r", "\n"], '', $to);
    $fromEmail = str_replace(["\r", "\n"], '', $fromEmail);
    // Also strip quotes from fromName to prevent header injection
    $fromName = str_replace(["\r", "\n", '"', '\\'], '', $fromName);
    
    // Headers
    $boundary = md5(uniqid(time()));
    $headers = "From: " . ($fromName ? "\"$fromName\" <$fromEmail>" : $fromEmail) . "\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
    $headers .= "\r\n";
    
    // Body - ensure content doesn't contain the boundary string
    $bodyText = $bodyText ? str_replace($boundary, 'boundary-removed', $bodyText) : strip_tags($bodyHtml);
    $bodyHtml = str_replace($boundary, 'boundary-removed', $bodyHtml);
    
    $body = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
    $body .= $bodyText . "\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
    $body .= $bodyHtml . "\r\n\r\n";
    $body .= "--$boundary--\r\n";
    
    // Invia messaggio
    fputs($smtp, $headers . $body . "\r\n.\r\n");
    $response = fgets($smtp, 515);
    if (substr($response, 0, 3) != '250') {
        fclose($smtp);
        return false;
    }
    
    // QUIT
    fputs($smtp, "QUIT\r\n");
    fclose($smtp);
    
    logEmailSuccess($to, $subject);
    return true;
}

/**
 * Invia email usando la funzione mail() di PHP
 */
function sendEmailNative($to, $subject, $bodyHtml, $bodyText = null, $fromEmail = null, $fromName = null) {
    global $config;
    
    $emailConfig = $config['email'] ?? [];
    $fromEmail = $fromEmail ?: ($emailConfig['from_email'] ?? 'noreply@associazione.it');
    $fromName = $fromName ?: ($emailConfig['from_name'] ?? 'AssoLife');
    
    // Prepara il messaggio multipart
    $boundary = md5(uniqid(time()));
    
    // Headers
    $headers = "From: " . $fromName . " <" . $fromEmail . ">\r\n";
    $headers .= "Reply-To: " . $fromEmail . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    
    // Corpo del messaggio
    $message = "--" . $boundary . "\r\n";
    
    // Parte testo
    if ($bodyText) {
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
        $message .= $bodyText . "\r\n\r\n";
        $message .= "--" . $boundary . "\r\n";
    }
    
    // Parte HTML
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
    $message .= $bodyHtml . "\r\n\r\n";
    $message .= "--" . $boundary . "--";
    
    $success = @mail($to, $subject, $message, $headers);
    
    if ($success) {
        logEmailSuccess($to, $subject);
    } else {
        logEmailError($to, $subject, 'Funzione mail() non disponibile o fallita');
    }
    
    return $success;
}

/**
 * Invia email da template
 * 
 * @param string $to Destinatario
 * @param string $templateCode Codice template
 * @param array $variables Variabili da sostituire
 * @return bool
 */
function sendEmailFromTemplate($to, $templateCode, $variables = []) {
    global $pdo, $config;
    
    // Recupera template
    $stmt = $pdo->prepare("SELECT * FROM " . table('email_templates') . " WHERE code = ? AND is_active = 1");
    $stmt->execute([$templateCode]);
    $template = $stmt->fetch();
    
    if (!$template) {
        logEmailError($to, 'Template non trovato: ' . $templateCode, 'Template non esistente o disabilitato');
        return false;
    }
    
    // Aggiungi variabili di sistema
    $variables['app_name'] = $config['app']['name'] ?? 'Associazione';
    
    // Sostituisci variabili nel subject e body
    $subject = replaceTemplateVariables($template['subject'], $variables);
    $bodyHtml = replaceTemplateVariables($template['body_html'], $variables);
    $bodyText = $template['body_text'] ? replaceTemplateVariables($template['body_text'], $variables) : null;
    
    // Add email footer automatically
    $bodyHtml .= getEmailFooter();
    
    return sendEmail($to, $subject, $bodyHtml, $bodyText);
}

/**
 * Sostituisce le variabili nel testo del template con protezione XSS
 * 
 * @param string $text Testo template
 * @param array $variables Variabili da sostituire
 * @param bool $escapeHtml Se true, esegue escape HTML (default: true)
 * @return string Testo con variabili sostituite
 */
function replaceTemplateVariables($text, $variables, $escapeHtml = true) {
    // Lista di variabili che contengono link HTML e NON devono essere escapate
    $safeVariables = ['reset_link', 'link_ricevuta', 'unsubscribe_link', 'link', 'verification_link'];
    
    foreach ($variables as $key => $value) {
        // Escape HTML per prevenire XSS injection via variabili
        // Tranne per i link che devono rimanere funzionanti
        if ($escapeHtml && !in_array($key, $safeVariables)) {
            $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
}

/**
 * Invia email direttamente o accoda in base alle impostazioni
 * 
 * @param string $to Destinatario
 * @param string $subject Oggetto
 * @param string $bodyHtml Corpo HTML
 * @param string|null $bodyText Corpo testo
 * @return bool|int True/ID se successo, false se fallito
 */
function sendOrQueueEmail($to, $subject, $bodyHtml, $bodyText = null) {
    $mode = getSetting('email_send_mode', 'direct');
    
    if ($mode === 'queue') {
        return queueEmail($to, $subject, $bodyHtml, $bodyText);
    } else {
        return sendEmail($to, $subject, $bodyHtml, $bodyText);
    }
}

/**
 * Accoda email per invio differito o massivo
 * 
 * @param string $to Destinatario
 * @param string $subject Oggetto
 * @param string $bodyHtml Corpo HTML
 * @param string|null $bodyText Corpo testo
 * @param string|null $scheduledAt Data/ora programmata (Y-m-d H:i:s)
 * @return int|false ID della email in coda o false
 */
function queueEmail($to, $subject, $bodyHtml, $bodyText = null, $scheduledAt = null) {
    global $pdo;
    
    // Estrai nome dal destinatario se presente
    $toName = null;
    if (preg_match('/^(.+?)\s*<(.+?)>$/', $to, $matches)) {
        $toName = trim($matches[1]);
        $to = trim($matches[2]);
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('email_queue') . " 
            (to_email, to_name, subject, body_html, body_text, scheduled_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $to,
            $toName,
            $subject,
            $bodyHtml,
            $bodyText,
            $scheduledAt
        ]);
        
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Errore accodamento email: " . $e->getMessage());
        return false;
    }
}

/**
 * Processa la coda email
 * 
 * @param int $limit Numero massimo di email da processare
 * @return array Statistiche processamento
 */
function processEmailQueue($limit = 10) {
    global $pdo;
    
    $stats = [
        'processed' => 0,
        'sent' => 0,
        'failed' => 0,
        'errors' => []
    ];
    
    // Seleziona email in coda
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('email_queue') . "
        WHERE status = 'pending'
        AND attempts < max_attempts
        AND (scheduled_at IS NULL OR scheduled_at <= NOW())
        ORDER BY created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $emails = $stmt->fetchAll();
    
    foreach ($emails as $email) {
        $stats['processed']++;
        
        // Aggiorna stato a processing
        $updateStmt = $pdo->prepare("
            UPDATE " . table('email_queue') . "
            SET status = 'processing', attempts = attempts + 1
            WHERE id = ?
        ");
        $updateStmt->execute([$email['id']]);
        
        // Tenta invio
        $success = sendEmail(
            $email['to_email'],
            $email['subject'],
            $email['body_html'],
            $email['body_text']
        );
        
        if ($success) {
            // Segna come inviata
            $updateStmt = $pdo->prepare("
                UPDATE " . table('email_queue') . "
                SET status = 'sent', sent_at = NOW(), error_message = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$email['id']]);
            $stats['sent']++;
        } else {
            // Segna come fallita se ha raggiunto il max tentativi
            $newStatus = ($email['attempts'] + 1 >= $email['max_attempts']) ? 'failed' : 'pending';
            $updateStmt = $pdo->prepare("
                UPDATE " . table('email_queue') . "
                SET status = ?, error_message = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $newStatus,
                'Invio fallito dopo ' . ($email['attempts'] + 1) . ' tentativi',
                $email['id']
            ]);
            $stats['failed']++;
            $stats['errors'][] = 'Email ID ' . $email['id'] . ' fallita';
        }
    }
    
    return $stats;
}

/**
 * Log email inviata con successo
 */
function logEmailSuccess($to, $subject) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('email_log') . " (to_email, subject, status)
            VALUES (?, ?, 'sent')
        ");
        $stmt->execute([$to, $subject]);
    } catch (PDOException $e) {
        error_log("Errore log email: " . $e->getMessage());
    }
}

/**
 * Log email fallita
 */
function logEmailError($to, $subject, $errorMessage) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('email_log') . " (to_email, subject, status, error_message)
            VALUES (?, ?, 'failed', ?)
        ");
        $stmt->execute([$to, $subject, $errorMessage]);
    } catch (PDOException $e) {
        error_log("Errore log email: " . $e->getMessage());
    }
}

/**
 * Ottieni statistiche email
 */
function getEmailStats($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count
        FROM " . table('email_log') . "
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY status
    ");
    $stmt->execute([$days]);
    
    $stats = ['sent' => 0, 'failed' => 0];
    while ($row = $stmt->fetch()) {
        $stats[$row['status']] = $row['count'];
    }
    
    return $stats;
}

/**
 * Conta email in coda
 */
function getQueuedEmailsCount() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count 
        FROM " . table('email_queue') . " 
        WHERE status = 'pending'
    ");
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Test SMTP connection and send test email
 */
function testSmtpConnection($testEmail) {
    $subject = "Test Email - " . getSetting('app_name', 'AssoLife');
    $body = "<h1>Test Email</h1><p>Se ricevi questa email, la configurazione SMTP è corretta!</p>";
    $body .= "<p>Inviata il: " . date('d/m/Y H:i:s') . "</p>";
    
    return sendEmailSmtp($testEmail, $subject, $body);
}

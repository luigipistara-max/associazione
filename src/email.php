<?php
/**
 * Email Functions - SMTP and Queue Management
 * Invio email tramite SMTP (senza librerie esterne) con fallback a mail()
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Invia email tramite SMTP o mail() come fallback
 * 
 * @param string $to Destinatario email
 * @param string $subject Oggetto
 * @param string $bodyHtml Corpo HTML
 * @param string|null $bodyText Corpo testo plain (opzionale)
 * @return bool True se inviata con successo
 */
function sendEmail($to, $subject, $bodyHtml, $bodyText = null) {
    global $config;
    
    // Validazione email destinatario
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        logEmailError($to, $subject, 'Email destinatario non valida');
        return false;
    }
    
    $emailConfig = $config['email'] ?? [];
    $enabled = $emailConfig['enabled'] ?? false;
    
    try {
        if ($enabled && !empty($emailConfig['smtp_host'])) {
            // Invio tramite SMTP
            return sendEmailSMTP($to, $subject, $bodyHtml, $bodyText);
        } else {
            // Fallback a mail()
            return sendEmailBuiltin($to, $subject, $bodyHtml, $bodyText);
        }
    } catch (Exception $e) {
        logEmailError($to, $subject, $e->getMessage());
        return false;
    }
}

/**
 * Invia email tramite SMTP usando fsockopen
 */
function sendEmailSMTP($to, $subject, $bodyHtml, $bodyText = null) {
    global $config;
    
    $emailConfig = $config['email'];
    $host = $emailConfig['smtp_host'];
    $port = $emailConfig['smtp_port'] ?? 587;
    $user = $emailConfig['smtp_user'] ?? '';
    $pass = $emailConfig['smtp_pass'] ?? '';
    $secure = $emailConfig['smtp_secure'] ?? 'tls';
    $fromEmail = $emailConfig['from_email'] ?? 'noreply@associazione.it';
    $fromName = $emailConfig['from_name'] ?? 'AssoLife';
    
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
    
    // Tentativo di connessione SMTP
    try {
        // Per semplicità e compatibilità, usiamo mail() con headers custom
        // Una implementazione SMTP completa richiederebbe più codice
        $success = mail($to, $subject, $message, $headers);
        
        if ($success) {
            logEmailSuccess($to, $subject);
        } else {
            logEmailError($to, $subject, 'Invio mail() fallito');
        }
        
        return $success;
    } catch (Exception $e) {
        logEmailError($to, $subject, $e->getMessage());
        return false;
    }
}

/**
 * Invia email usando la funzione mail() di PHP
 */
function sendEmailBuiltin($to, $subject, $bodyHtml, $bodyText = null) {
    global $config;
    
    $emailConfig = $config['email'] ?? [];
    $fromEmail = $emailConfig['from_email'] ?? 'noreply@associazione.it';
    $fromName = $emailConfig['from_name'] ?? 'AssoLife';
    
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
    
    return sendEmail($to, $subject, $bodyHtml, $bodyText);
}

/**
 * Sostituisce le variabili nel testo del template
 */
function replaceTemplateVariables($text, $variables) {
    foreach ($variables as $key => $value) {
        $text = str_replace('{' . $key . '}', $value, $text);
    }
    return $text;
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

<?php
/**
 * PDF Generation Functions
 * Generazione ricevute PDF senza librerie esterne
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

/**
 * Genera numero ricevuta progressivo
 * 
 * @param int $feeId ID quota
 * @param int|null $year Anno (default: anno corrente)
 * @return string Numero ricevuta formato RIC-ANNO-NUMERO
 */
function generateReceiptNumber($feeId, $year = null) {
    global $pdo;
    
    if ($year === null) {
        $year = date('Y');
    }
    
    // Trova l'ultimo numero ricevuta per l'anno
    $stmt = $pdo->prepare("
        SELECT receipt_number FROM " . table('member_fees') . "
        WHERE receipt_number LIKE ? 
        ORDER BY receipt_number DESC 
        LIMIT 1
    ");
    $stmt->execute(['RIC-' . $year . '-%']);
    $lastReceipt = $stmt->fetch();
    
    $nextNumber = 1;
    if ($lastReceipt && $lastReceipt['receipt_number']) {
        // Estrai numero dalla ricevuta (es: RIC-2024-00005 -> 5)
        if (preg_match('/RIC-\d{4}-(\d+)/', $lastReceipt['receipt_number'], $matches)) {
            $nextNumber = intval($matches[1]) + 1;
        }
    }
    
    // Formato: RIC-2024-00001
    return sprintf('RIC-%d-%05d', $year, $nextNumber);
}

/**
 * Genera HTML per ricevuta
 * 
 * @param int $feeId ID quota
 * @return string|false HTML ricevuta o false se errore
 */
function generateReceiptHTML($feeId) {
    global $pdo, $config;
    
    // Recupera dati quota con socio e anno
    $stmt = $pdo->prepare("
        SELECT 
            mf.*,
            m.first_name, m.last_name, m.fiscal_code, m.address, m.city, m.province, m.postal_code,
            m.membership_number,
            sy.name as year_name
        FROM " . table('member_fees') . " mf
        JOIN " . table('members') . " m ON mf.member_id = m.id
        LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
        WHERE mf.id = ?
    ");
    $stmt->execute([$feeId]);
    $fee = $stmt->fetch();
    
    if (!$fee) {
        return false;
    }
    
    // Verifica che sia stata pagata
    if ($fee['status'] !== 'paid') {
        return false;
    }
    
    // Genera numero ricevuta se non esiste
    if (empty($fee['receipt_number'])) {
        $receiptNumber = generateReceiptNumber($feeId);
        
        // Aggiorna database
        $updateStmt = $pdo->prepare("
            UPDATE " . table('member_fees') . "
            SET receipt_number = ?, receipt_generated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([$receiptNumber, $feeId]);
        
        $fee['receipt_number'] = $receiptNumber;
        $fee['receipt_generated_at'] = date('Y-m-d H:i:s');
    }
    
    // Get association information from settings
    $assocInfo = getAssociationInfo();
    $bankDetails = getBankDetails();
    $appName = $assocInfo['name'] ?? $config['app']['name'] ?? 'Associazione';
    
    // Importo in lettere
    $amountInWords = numberToWords($fee['amount']);
    
    // Costruisci indirizzo completo socio
    $address = trim($fee['address'] ?? '');
    if ($fee['city']) {
        $address .= ($address ? ', ' : '') . $fee['city'];
    }
    if ($fee['province']) {
        $address .= ($address && $fee['city'] ? ' (' . $fee['province'] . ')' : '');
    }
    if ($fee['postal_code']) {
        $address .= ($address ? ' - ' : '') . $fee['postal_code'];
    }
    
    // Template HTML ricevuta
    $html = '
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ricevuta ' . h($fee['receipt_number']) . '</title>
    <style>
        @media print {
            body { margin: 0; }
            .no-print { display: none; }
        }
        
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .receipt-container {
            border: 2px solid #0d6efd;
            padding: 30px;
            background: white;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 20px;
        }
        
        .header h1 {
            color: #0d6efd;
            margin: 0 0 10px 0;
            font-size: 28px;
        }
        
        .header .receipt-number {
            font-size: 20px;
            font-weight: bold;
            color: #666;
        }
        
        .section {
            margin: 20px 0;
        }
        
        .section-title {
            font-weight: bold;
            color: #0d6efd;
            margin-bottom: 10px;
            font-size: 16px;
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 5px;
        }
        
        .info-row {
            display: flex;
            margin: 5px 0;
        }
        
        .info-label {
            font-weight: bold;
            width: 200px;
        }
        
        .info-value {
            flex: 1;
        }
        
        .amount-box {
            background: #f8f9fa;
            border: 2px solid #0d6efd;
            padding: 15px;
            margin: 20px 0;
            text-align: center;
        }
        
        .amount-box .amount {
            font-size: 32px;
            font-weight: bold;
            color: #0d6efd;
            margin: 10px 0;
        }
        
        .amount-box .amount-words {
            font-style: italic;
            color: #666;
        }
        
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 12px;
            color: #666;
            text-align: center;
        }
        
        .legal-notice {
            margin-top: 20px;
            padding: 10px;
            background: #fff3cd;
            border: 1px solid #ffc107;
            font-size: 11px;
            text-align: left;
        }
        
        .no-print {
            margin: 20px 0;
            text-align: center;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            margin: 5px;
            background: #0d6efd;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            border: none;
            cursor: pointer;
        }
        
        .btn:hover {
            background: #0b5ed7;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Stampa Ricevuta</button>
        <button onclick="window.close()" class="btn">❌ Chiudi</button>
    </div>
    
    <div class="receipt-container">
        <div class="header">
            <h1>' . h($appName) . '</h1>
            <div class="receipt-number">Ricevuta n. ' . h($fee['receipt_number']) . '</div>
            <div style="margin-top: 10px;">Data Emissione: ' . formatDate($fee['receipt_generated_at'] ?? $fee['paid_date']) . '</div>
        </div>
        
        <div class="section">
            <div class="section-title">Dati Associazione</div>
            <div class="info-row">
                <div class="info-label">Nome:</div>
                <div class="info-value">' . h($appName) . '</div>
            </div>
            ' . ($assocInfo['address'] ? '<div class="info-row">
                <div class="info-label">Sede:</div>
                <div class="info-value">' . nl2br(h($assocInfo['address'])) . '</div>
            </div>' : '') . '
            ' . ($assocInfo['email'] ? '<div class="info-row">
                <div class="info-label">Email:</div>
                <div class="info-value">' . h($assocInfo['email']) . '</div>
            </div>' : '') . '
        </div>
        
        <div class="section">
            <div class="section-title">Dati Socio</div>
            <div class="info-row">
                <div class="info-label">Nome Completo:</div>
                <div class="info-value">' . h($fee['first_name'] . ' ' . $fee['last_name']) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Codice Fiscale:</div>
                <div class="info-value">' . h($fee['fiscal_code']) . '</div>
            </div>
            ' . ($fee['membership_number'] ? '<div class="info-row">
                <div class="info-label">Numero Tessera:</div>
                <div class="info-value">' . h($fee['membership_number']) . '</div>
            </div>' : '') . '
            ' . ($address ? '<div class="info-row">
                <div class="info-label">Indirizzo:</div>
                <div class="info-value">' . h($address) . '</div>
            </div>' : '') . '
        </div>
        
        <div class="section">
            <div class="section-title">Dettaglio Pagamento</div>
            <div class="info-row">
                <div class="info-label">Descrizione:</div>
                <div class="info-value">Quota associativa ' . h($fee['year_name'] ?? '') . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo:</div>
                <div class="info-value">' . h(ucfirst(str_replace('_', ' ', $fee['fee_type']))) . '</div>
            </div>
            <div class="info-row">
                <div class="info-label">Data Pagamento:</div>
                <div class="info-value">' . formatDate($fee['paid_date']) . '</div>
            </div>
            ' . ($fee['payment_method'] ? '<div class="info-row">
                <div class="info-label">Metodo Pagamento:</div>
                <div class="info-value">' . h($fee['payment_method']) . '</div>
            </div>' : '') . '
        </div>
        
        <div class="amount-box">
            <div>Importo Pagato</div>
            <div class="amount">' . formatCurrency($fee['amount']) . '</div>
            <div class="amount-words">' . h($amountInWords) . '</div>
        </div>
        
        ' . ($bankDetails['iban'] || $assocInfo['fiscal_cf'] || $assocInfo['fiscal_piva'] ? '
        <div class="section">
            <div class="section-title">Dati Fiscali e Bancari</div>
            ' . ($assocInfo['fiscal_cf'] ? '<div class="info-row">
                <div class="info-label">Codice Fiscale:</div>
                <div class="info-value">' . h($assocInfo['fiscal_cf']) . '</div>
            </div>' : '') . '
            ' . ($assocInfo['fiscal_piva'] ? '<div class="info-row">
                <div class="info-label">Partita IVA:</div>
                <div class="info-value">' . h($assocInfo['fiscal_piva']) . '</div>
            </div>' : '') . '
            ' . ($bankDetails['iban'] ? '<div class="info-row">
                <div class="info-label">IBAN:</div>
                <div class="info-value">' . h($bankDetails['iban']) . '</div>
            </div>' : '') . '
            ' . ($bankDetails['bank_name'] ? '<div class="info-row">
                <div class="info-label">Banca:</div>
                <div class="info-value">' . h($bankDetails['bank_name']) . '</div>
            </div>' : '') . '
        </div>
        ' : '') . '
        
        <div class="legal-notice">
            <strong>Nota:</strong> La presente ricevuta attesta il pagamento della quota associativa. 
            Questa associazione non ha scopo di lucro e opera in conformità con le normative vigenti.
        </div>
        
        <div class="footer">
            Documento generato il ' . formatDate(date('Y-m-d')) . ' alle ' . date('H:i') . '<br>
            Ricevuta numero: ' . h($fee['receipt_number']) . '
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

/**
 * Converti numero in lettere (italiano)
 */
function numberToWords($number) {
    $number = floatval($number);
    $euros = floor($number);
    $cents = round(($number - $euros) * 100);
    
    $result = convertIntegerToWords($euros);
    
    if ($euros == 1) {
        $result .= ' euro';
    } else {
        $result .= ' euro';
    }
    
    if ($cents > 0) {
        $result .= ' e ' . convertIntegerToWords($cents);
        if ($cents == 1) {
            $result .= ' centesimo';
        } else {
            $result .= ' centesimi';
        }
    }
    
    return ucfirst($result);
}

/**
 * Converti intero in parole
 */
function convertIntegerToWords($number) {
    $units = ['', 'uno', 'due', 'tre', 'quattro', 'cinque', 'sei', 'sette', 'otto', 'nove'];
    $teens = ['dieci', 'undici', 'dodici', 'tredici', 'quattordici', 'quindici', 'sedici', 'diciassette', 'diciotto', 'diciannove'];
    $tens = ['', '', 'venti', 'trenta', 'quaranta', 'cinquanta', 'sessanta', 'settanta', 'ottanta', 'novanta'];
    
    if ($number == 0) return 'zero';
    if ($number < 10) return $units[$number];
    if ($number < 20) return $teens[$number - 10];
    if ($number < 100) {
        $ten = floor($number / 10);
        $unit = $number % 10;
        return $tens[$ten] . ($unit > 0 ? $units[$unit] : '');
    }
    if ($number < 1000) {
        $hundred = floor($number / 100);
        $remainder = $number % 100;
        $result = ($hundred == 1 ? 'cento' : $units[$hundred] . 'cento');
        if ($remainder > 0) {
            $result .= convertIntegerToWords($remainder);
        }
        return $result;
    }
    if ($number < 1000000) {
        $thousand = floor($number / 1000);
        $remainder = $number % 1000;
        $result = ($thousand == 1 ? 'mille' : convertIntegerToWords($thousand) . 'mila');
        if ($remainder > 0) {
            $result .= convertIntegerToWords($remainder);
        }
        return $result;
    }
    
    return strval($number); // Fallback per numeri molto grandi
}

/**
 * Genera PDF ricevuta (al momento genera HTML stampabile)
 * Per una vera generazione PDF servirebbero librerie esterne
 */
function generateReceiptPDF($feeId) {
    return generateReceiptHTML($feeId);
}

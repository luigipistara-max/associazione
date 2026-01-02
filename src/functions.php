<?php
/**
 * Utility Functions
 */

/**
 * Escape HTML output (sanitization)
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Escape HTML output (alias)
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function formatDate($date) {
    if (empty($date)) return '';
    return date('d/m/Y', strtotime($date));
}

/**
 * Format amount for display
 */
function formatAmount($amount) {
    return number_format($amount, 2, ',', '.') . ' €';
}

/**
 * Format currency (alias)
 */
function formatCurrency($amount) {
    return formatAmount($amount);
}

/**
 * Validate Italian Fiscal Code (Codice Fiscale) with complete check digit validation
 */
function validateFiscalCode($fiscalCode) {
    $fiscalCode = strtoupper(trim($fiscalCode));
    
    // Basic format check: 16 characters, alphanumeric
    if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $fiscalCode)) {
        return false;
    }
    
    // Check digit validation - complete algorithm
    $odd = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23
    ];
    
    $even = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25
    ];
    
    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $c = $fiscalCode[$i];
        if ($i % 2 == 0) {
            $sum += $odd[$c];
        } else {
            $sum += $even[$c];
        }
    }
    
    $control = chr(65 + ($sum % 26));
    return $fiscalCode[15] === $control;
}

/**
 * Validate Italian Tax Code (alias)
 */
function validateTaxCode($taxCode) {
    return validateFiscalCode($taxCode);
}

/**
 * Get current social year
 */
function getCurrentSocialYear() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM " . table('social_years') . " WHERE is_current = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get all active social years
 */
function getSocialYears() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM " . table('social_years') . " ORDER BY start_date DESC");
    return $stmt->fetchAll();
}

/**
 * Get income categories
 */
function getIncomeCategories($activeOnly = true) {
    global $pdo;
    $sql = "SELECT * FROM " . table('income_categories');
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get expense categories
 */
function getExpenseCategories($activeOnly = true) {
    global $pdo;
    $sql = "SELECT * FROM " . table('expense_categories');
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY sort_order, name";
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Set flash message
 */
function setFlash($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Set flash message (alias)
 */
function setFlashMessage($message, $type = 'success') {
    setFlash($message, $type);
}

/**
 * Get flash message
 */
function getFlash() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        $type = $_SESSION['flash_type'] ?? 'success';
        unset($_SESSION['flash_message']);
        unset($_SESSION['flash_type']);
        return ['message' => $message, 'type' => $type];
    }
    return null;
}

/**
 * Get and clear flash message (alias)
 */
function getFlashMessage() {
    return getFlash();
}

/**
 * Display flash message HTML
 */
function displayFlash() {
    $flash = getFlash();
    if ($flash) {
        echo '<div class="alert alert-' . h($flash['type']) . ' alert-dismissible fade show" role="alert">';
        echo h($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

/**
 * Escape LIKE special characters
 * Prevents % and _ from being interpreted as wildcards
 */
function escapeLike($string) {
    return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $string);
}

/**
 * Redirect to a page
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Parse CSV file into array
 */
function parseCsvFile($filePath, $delimiter = ',', $hasHeader = true) {
    if (!file_exists($filePath)) {
        return false;
    }
    
    $data = [];
    $headers = [];
    
    if (($handle = fopen($filePath, 'r')) !== false) {
        if ($hasHeader) {
            $headers = fgetcsv($handle, 0, $delimiter);
            if ($headers === false) {
                fclose($handle);
                return false;
            }
        }
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($hasHeader && !empty($headers)) {
                // Skip rows that don't match header count
                if (count($row) !== count($headers)) {
                    continue;
                }
                $data[] = array_combine($headers, $row);
            } else {
                $data[] = $row;
            }
        }
        
        fclose($handle);
    }
    
    return $data;
}

/**
 * Export data to CSV
 */
function exportCsv($filename, $data, $headers = null) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // Add BOM for Excel UTF-8 support
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($headers) {
        fputcsv($output, $headers);
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit;
}

/**
 * Get member fees for a specific member
 */
function getMemberFees($memberId, $yearId = null) {
    global $pdo;
    
    $sql = "SELECT mf.*, sy.name as year_name 
            FROM " . table('member_fees') . " mf
            LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
            WHERE mf.member_id = ?";
    
    $params = [$memberId];
    
    if ($yearId !== null) {
        $sql .= " AND mf.social_year_id = ?";
        $params[] = $yearId;
    }
    
    $sql .= " ORDER BY mf.due_date DESC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get member fee status for a specific year
 */
function getMemberFeeStatus($memberId, $yearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM " . table('member_fees') . "
        WHERE member_id = ? AND social_year_id = ?
        GROUP BY status
    ");
    $stmt->execute([$memberId, $yearId]);
    
    $statuses = [];
    while ($row = $stmt->fetch()) {
        $statuses[$row['status']] = $row['count'];
    }
    
    if (isset($statuses['paid']) && $statuses['paid'] > 0) {
        return 'paid';
    } elseif (isset($statuses['overdue'])) {
        return 'overdue';
    } elseif (isset($statuses['pending'])) {
        return 'pending';
    }
    
    return 'none';
}

/**
 * Check if member has paid fee for year
 */
function isFeePaid($memberId, $yearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM " . table('member_fees') . "
        WHERE member_id = ? AND social_year_id = ? AND status = 'paid'
    ");
    $stmt->execute([$memberId, $yearId]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

/**
 * Get overdue fees
 */
function getOverdueFees($yearId = null) {
    global $pdo;
    
    $sql = "SELECT mf.*, m.first_name, m.last_name, sy.name as year_name
            FROM " . table('member_fees') . " mf
            JOIN " . table('members') . " m ON mf.member_id = m.id
            LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
            WHERE mf.status = 'overdue'";
    
    if ($yearId !== null) {
        $sql .= " AND mf.social_year_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$yearId]);
    } else {
        $stmt = $pdo->query($sql);
    }
    
    return $stmt->fetchAll();
}

/**
 * Update overdue statuses for fees past due date
 */
function updateOverdueStatuses() {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('member_fees') . "
        SET status = 'overdue'
        WHERE status = 'pending' AND due_date < CURDATE()
    ");
    $stmt->execute();
    
    return $stmt->rowCount();
}

/**
 * Get active members (with paid fee for year)
 */
function getActiveMembers($socialYearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.*
        FROM " . table('members') . " m
        INNER JOIN " . table('member_fees') . " mf ON m.id = mf.member_id
        WHERE mf.social_year_id = ? AND mf.status = 'paid'
        ORDER BY m.last_name, m.first_name
    ");
    $stmt->execute([$socialYearId]);
    return $stmt->fetchAll();
}

/**
 * Get members with unpaid/overdue fees (morosi)
 */
function getMorosi($socialYearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.*, mf.status as fee_status, mf.due_date, mf.amount
        FROM " . table('members') . " m
        INNER JOIN " . table('member_fees') . " mf ON m.id = mf.member_id
        WHERE mf.social_year_id = ? AND mf.status IN ('pending', 'overdue')
        ORDER BY mf.due_date ASC, m.last_name, m.first_name
    ");
    $stmt->execute([$socialYearId]);
    return $stmt->fetchAll();
}

/**
 * Export active members to CSV
 */
function exportActiveMembersCsv($socialYearId, $fields) {
    $members = getActiveMembers($socialYearId);
    
    // Build headers
    $headers = [];
    $fieldMap = [
        'membership_number' => 'Numero Tessera',
        'first_name' => 'Nome',
        'last_name' => 'Cognome',
        'fiscal_code' => 'Codice Fiscale',
        'email' => 'Email',
        'phone' => 'Telefono',
        'paid_date' => 'Data Pagamento',
        'amount' => 'Importo'
    ];
    
    foreach ($fields as $field) {
        if (isset($fieldMap[$field])) {
            $headers[] = $fieldMap[$field];
        }
    }
    
    // Build data rows
    $data = [];
    foreach ($members as $member) {
        $row = [];
        foreach ($fields as $field) {
            if (in_array($field, ['paid_date', 'amount'])) {
                // Get fee data
                $stmt = $GLOBALS['pdo']->prepare("
                    SELECT paid_date, amount
                    FROM " . table('member_fees') . "
                    WHERE member_id = ? AND social_year_id = ? AND status = 'paid'
                    ORDER BY paid_date DESC
                    LIMIT 1
                ");
                $stmt->execute([$member['id'], $socialYearId]);
                $fee = $stmt->fetch();
                
                if ($field === 'paid_date' && $fee) {
                    $row[] = formatDate($fee['paid_date']);
                } elseif ($field === 'amount' && $fee) {
                    $row[] = number_format($fee['amount'], 2, ',', '.');
                } else {
                    $row[] = '';
                }
            } else {
                $row[] = $member[$field] ?? '';
            }
        }
        $data[] = $row;
    }
    
    return ['headers' => $headers, 'data' => $data];
}

/**
 * Get fees expiring soon
 */
function getFeesExpiringSoon($days = 30) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT mf.*, m.first_name, m.last_name, m.membership_number, sy.name as year_name
        FROM " . table('member_fees') . " mf
        JOIN " . table('members') . " m ON mf.member_id = m.id
        LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
        WHERE mf.status = 'pending' 
        AND mf.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
        ORDER BY mf.due_date ASC
    ");
    $stmt->execute([$days]);
    return $stmt->fetchAll();
}

/**
 * Count members with overdue fees (morosi)
 */
function countMorosi($socialYearId = null) {
    global $pdo;
    
    if ($socialYearId !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(DISTINCT member_id) as count
            FROM " . table('member_fees') . "
            WHERE status IN ('pending', 'overdue') AND social_year_id = ?
        ");
        $stmt->execute([$socialYearId]);
    } else {
        $stmt = $pdo->query("
            SELECT COUNT(DISTINCT member_id) as count
            FROM " . table('member_fees') . "
            WHERE status IN ('pending', 'overdue')
        ");
    }
    
    $result = $stmt->fetch();
    return $result['count'];
}

/**
 * Get total pending fees amount
 */
function getTotalPendingFees($socialYearId = null) {
    global $pdo;
    
    if ($socialYearId !== null) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM " . table('member_fees') . "
            WHERE status IN ('pending', 'overdue') AND social_year_id = ?
        ");
        $stmt->execute([$socialYearId]);
    } else {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM " . table('member_fees') . "
            WHERE status IN ('pending', 'overdue')
        ");
    }
    
    $result = $stmt->fetch();
    return $result['total'];
}

/**
 * Get total collected fees amount
 */
function getTotalCollectedFees($socialYearId = null) {
    global $pdo;
    
    if ($socialYearId !== null) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM " . table('member_fees') . "
            WHERE status = 'paid' AND social_year_id = ?
        ");
        $stmt->execute([$socialYearId]);
    } else {
        $stmt = $pdo->query("
            SELECT COALESCE(SUM(amount), 0) as total
            FROM " . table('member_fees') . "
            WHERE status = 'paid'
        ");
    }
    
    $result = $stmt->fetch();
    return $result['total'];
}

/**
 * Verifica se socio ha già quota per anno
 */
function memberHasFeeForYear($memberId, $socialYearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM " . table('member_fees') . "
        WHERE member_id = ? AND social_year_id = ?
    ");
    $stmt->execute([$memberId, $socialYearId]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

/**
 * Ottieni soci senza quota per anno
 */
function getMembersWithoutFee($socialYearId, $status = 'attivo') {
    global $pdo;
    
    $sql = "
        SELECT m.*
        FROM " . table('members') . " m
        WHERE m.status = ?
        AND NOT EXISTS (
            SELECT 1 FROM " . table('member_fees') . " mf
            WHERE mf.member_id = m.id AND mf.social_year_id = ?
        )
        ORDER BY m.last_name, m.first_name
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$status, $socialYearId]);
    
    return $stmt->fetchAll();
}

/**
 * Copia importo quota da anno precedente
 */
function getPreviousFeeAmount($memberId, $currentYearId) {
    global $pdo;
    
    // Trova anno precedente
    $stmt = $pdo->prepare("
        SELECT id, start_date 
        FROM " . table('social_years') . " 
        WHERE id = ?
    ");
    $stmt->execute([$currentYearId]);
    $currentYear = $stmt->fetch();
    
    if (!$currentYear) {
        return null;
    }
    
    // Trova l'anno sociale precedente
    $stmt = $pdo->prepare("
        SELECT id 
        FROM " . table('social_years') . " 
        WHERE start_date < ?
        ORDER BY start_date DESC
        LIMIT 1
    ");
    $stmt->execute([$currentYear['start_date']]);
    $previousYear = $stmt->fetch();
    
    if (!$previousYear) {
        return null;
    }
    
    // Trova importo quota dell'anno precedente
    $stmt = $pdo->prepare("
        SELECT amount 
        FROM " . table('member_fees') . "
        WHERE member_id = ? AND social_year_id = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$memberId, $previousYear['id']]);
    $previousFee = $stmt->fetch();
    
    return $previousFee ? $previousFee['amount'] : null;
}

/**
 * Genera quote massivamente
 * 
 * @param array $memberIds Array di ID soci
 * @param int $socialYearId ID anno sociale
 * @param float $amount Importo quota
 * @param string $dueDate Data scadenza
 * @param string $feeType Tipo quota
 * @param bool $sendEmail Invia email notifica
 * @return array Statistiche creazione
 */
function bulkCreateFees($memberIds, $socialYearId, $amount, $dueDate, $feeType = 'quota_associativa', $sendEmail = false) {
    global $pdo;
    
    $stats = [
        'created' => 0,
        'skipped' => 0,
        'emails_sent' => 0,
        'errors' => []
    ];
    
    foreach ($memberIds as $memberId) {
        // Verifica se ha già una quota per questo anno
        if (memberHasFeeForYear($memberId, $socialYearId)) {
            $stats['skipped']++;
            continue;
        }
        
        try {
            // Crea quota
            $stmt = $pdo->prepare("
                INSERT INTO " . table('member_fees') . "
                (member_id, social_year_id, fee_type, amount, due_date, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$memberId, $socialYearId, $feeType, $amount, $dueDate]);
            $stats['created']++;
            
            // Invia email se richiesto
            if ($sendEmail) {
                // Recupera dati socio e anno
                $memberStmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
                $memberStmt->execute([$memberId]);
                $member = $memberStmt->fetch();
                
                $yearStmt = $pdo->prepare("SELECT * FROM " . table('social_years') . " WHERE id = ?");
                $yearStmt->execute([$socialYearId]);
                $year = $yearStmt->fetch();
                
                if ($member && $member['email'] && $year) {
                    require_once __DIR__ . '/email.php';
                    
                    $variables = [
                        'nome' => $member['first_name'],
                        'cognome' => $member['last_name'],
                        'anno' => $year['name'],
                        'importo' => formatCurrency($amount),
                        'scadenza' => formatDate($dueDate)
                    ];
                    
                    if (sendEmailFromTemplate($member['email'], 'new_fee_notification', $variables)) {
                        $stats['emails_sent']++;
                    }
                }
            }
        } catch (PDOException $e) {
            $stats['errors'][] = "Errore creazione quota per socio ID $memberId: " . $e->getMessage();
        }
    }
    
    return $stats;
}

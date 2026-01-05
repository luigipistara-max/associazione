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
 * Get fee amount for a social year
 */
function getSocialYearFeeAmount($yearId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT fee_amount FROM " . table('social_years') . " WHERE id = ?");
    $stmt->execute([$yearId]);
    $result = $stmt->fetch();
    
    return $result ? floatval($result['fee_amount']) : 0;
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
 * Check if category already exists
 */
function categoryExists($name, $type, $excludeId = null) {
    global $pdo;
    
    // Validate type parameter to prevent SQL injection
    if (!in_array($type, ['income', 'expense'], true)) {
        throw new InvalidArgumentException('Invalid category type. Must be "income" or "expense".');
    }
    
    $table = $type === 'income' ? table('income_categories') : table('expense_categories');
    
    $sql = "SELECT COUNT(*) as count FROM $table WHERE name = ?";
    $params = [$name];
    
    // Exclude current ID when editing
    if ($excludeId !== null) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
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
        SELECT mf.*, m.first_name, m.last_name, m.email, m.membership_number, sy.name as year_name
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

/**
 * Genera token univoco per tessera socio
 */
function generateCardToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Verifica se socio ha quota pagata per anno corrente
 */
function isMemberActive($memberId) {
    global $pdo;
    $currentYear = getCurrentSocialYear();
    if (!$currentYear) return false;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM " . table('member_fees') . "
        WHERE member_id = ? AND social_year_id = ? AND status = 'paid'
    ");
    $stmt->execute([$memberId, $currentYear['id']]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Verifica tessera tramite token
 */
function verifyMemberCard($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, 
               (SELECT COUNT(*) FROM " . table('member_fees') . " mf 
                JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id 
                WHERE mf.member_id = m.id AND mf.status = 'paid' AND sy.is_current = 1) as has_paid_current
        FROM " . table('members') . " m
        WHERE m.card_token = ?
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Andamento finanziario ultimi N mesi
 */
function getFinancialTrend($months = 12) {
    global $pdo;
    
    $data = ['labels' => [], 'income' => [], 'expenses' => []];
    
    for ($i = $months - 1; $i >= 0; $i--) {
        $date = date('Y-m', strtotime("-$i months"));
        $data['labels'][] = date('M Y', strtotime("-$i months"));
        
        // Entrate del mese
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM " . table('income') . "
            WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ");
        $stmt->execute([$date]);
        $data['income'][] = floatval($stmt->fetch()['total']);
        
        // Uscite del mese
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) as total 
            FROM " . table('expenses') . "
            WHERE DATE_FORMAT(transaction_date, '%Y-%m') = ?
        ");
        $stmt->execute([$date]);
        $data['expenses'][] = floatval($stmt->fetch()['total']);
    }
    
    return $data;
}

/**
 * Entrate per categoria (anno corrente)
 */
function getIncomeByCategory($yearId = null) {
    global $pdo;
    
    if (!$yearId) {
        $currentYear = getCurrentSocialYear();
        $yearId = $currentYear['id'] ?? null;
    }
    
    if ($yearId) {
        $sql = "
            SELECT ic.name, COALESCE(SUM(i.amount), 0) as total
            FROM " . table('income_categories') . " ic
            LEFT JOIN " . table('income') . " i ON ic.id = i.category_id AND i.social_year_id = ?
            GROUP BY ic.id, ic.name 
            ORDER BY total DESC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$yearId]);
    } else {
        $sql = "
            SELECT ic.name, COALESCE(SUM(i.amount), 0) as total
            FROM " . table('income_categories') . " ic
            LEFT JOIN " . table('income') . " i ON ic.id = i.category_id
            GROUP BY ic.id, ic.name 
            ORDER BY total DESC
        ";
        $stmt = $pdo->query($sql);
    }
    
    $results = $stmt->fetchAll();
    
    return [
        'labels' => array_column($results, 'name'),
        'data' => array_map('floatval', array_column($results, 'total'))
    ];
}

/**
 * Soci per stato
 */
function getMembersByStatus() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM " . table('members') . "
        GROUP BY status
    ");
    
    $results = [];
    while ($row = $stmt->fetch()) {
        $results[$row['status']] = intval($row['count']);
    }
    
    return [
        'labels' => ['Attivi', 'Sospesi', 'Cessati'],
        'data' => [
            $results['attivo'] ?? 0,
            $results['sospeso'] ?? 0,
            $results['cessato'] ?? 0
        ],
        'colors' => ['#28a745', '#ffc107', '#dc3545']
    ];
}

/**
 * Stato quote anno corrente
 */
function getFeesStatus($yearId = null) {
    global $pdo;
    
    if (!$yearId) {
        $currentYear = getCurrentSocialYear();
        $yearId = $currentYear['id'] ?? null;
    }
    
    if (!$yearId) {
        return ['labels' => [], 'data' => [], 'colors' => []];
    }
    
    $stmt = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM " . table('member_fees') . "
        WHERE social_year_id = ?
        GROUP BY status
    ");
    $stmt->execute([$yearId]);
    
    $results = [];
    while ($row = $stmt->fetch()) {
        $results[$row['status']] = intval($row['count']);
    }
    
    return [
        'labels' => ['Pagate', 'In attesa', 'Scadute'],
        'data' => [
            $results['paid'] ?? 0,
            $results['pending'] ?? 0,
            $results['overdue'] ?? 0
        ],
        'colors' => ['#28a745', '#ffc107', '#dc3545']
    ];
}

// ============================================================================
// EVENT MANAGEMENT FUNCTIONS
// ============================================================================

/**
 * Get events with optional filters
 */
function getEvents($filters = [], $limit = 20, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT * FROM " . table('events') . " WHERE 1=1";
    $params = [];
    
    if (!empty($filters['status'])) {
        $sql .= " AND status = ?";
        $params[] = $filters['status'];
    }
    
    if (!empty($filters['event_mode'])) {
        $sql .= " AND event_mode = ?";
        $params[] = $filters['event_mode'];
    }
    
    if (!empty($filters['from_date'])) {
        $sql .= " AND event_date >= ?";
        $params[] = $filters['from_date'];
    }
    
    if (!empty($filters['to_date'])) {
        $sql .= " AND event_date <= ?";
        $params[] = $filters['to_date'];
    }
    
    $sql .= " ORDER BY event_date ASC, event_time ASC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get single event by ID
 */
function getEvent($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('events') . " WHERE id = ?");
    $stmt->execute([$eventId]);
    return $stmt->fetch();
}

/**
 * Create new event
 */
function createEvent($data) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    $stmt = $pdo->prepare("
        INSERT INTO " . table('events') . " 
        (title, description, event_date, event_time, end_date, end_time,
         event_mode, location, address, city,
         online_link, online_platform, online_instructions, online_password,
         max_participants, registration_deadline, cost, status, target_type, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['event_date'],
        $data['event_time'] ?? null,
        $data['end_date'] ?? null,
        $data['end_time'] ?? null,
        $data['event_mode'] ?? 'in_person',
        $data['location'] ?? null,
        $data['address'] ?? null,
        $data['city'] ?? null,
        $data['online_link'] ?? null,
        $data['online_platform'] ?? null,
        $data['online_instructions'] ?? null,
        $data['online_password'] ?? null,
        $data['max_participants'] ?? 0,
        $data['registration_deadline'] ?? null,
        $data['cost'] ?? 0,
        $data['status'] ?? 'draft',
        $data['target_type'] ?? 'all',
        $data['created_by'] ?? null
    ]);
    
    $eventId = $pdo->lastInsertId();
    
    logCreate('event', $eventId, $data['title'], $data);
    
    return $eventId;
}

/**
 * Update event
 */
function updateEvent($eventId, $data) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    $oldEvent = getEvent($eventId);
    
    $stmt = $pdo->prepare("
        UPDATE " . table('events') . " 
        SET title = ?, description = ?, event_date = ?, event_time = ?,
            end_date = ?, end_time = ?, event_mode = ?,
            location = ?, address = ?, city = ?,
            online_link = ?, online_platform = ?, online_instructions = ?, online_password = ?,
            max_participants = ?, registration_deadline = ?, cost = ?, status = ?, target_type = ?
        WHERE id = ?
    ");
    
    $result = $stmt->execute([
        $data['title'],
        $data['description'] ?? null,
        $data['event_date'],
        $data['event_time'] ?? null,
        $data['end_date'] ?? null,
        $data['end_time'] ?? null,
        $data['event_mode'] ?? 'in_person',
        $data['location'] ?? null,
        $data['address'] ?? null,
        $data['city'] ?? null,
        $data['online_link'] ?? null,
        $data['online_platform'] ?? null,
        $data['online_instructions'] ?? null,
        $data['online_password'] ?? null,
        $data['max_participants'] ?? 0,
        $data['registration_deadline'] ?? null,
        $data['cost'] ?? 0,
        $data['status'] ?? 'draft',
        $data['target_type'] ?? 'all',
        $eventId
    ]);
    
    if ($oldEvent) {
        logUpdate('event', $eventId, $data['title'], $oldEvent, $data);
    }
    
    return $result;
}

/**
 * Delete event
 */
function deleteEvent($eventId) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    $event = getEvent($eventId);
    
    // Delete registrations first
    $stmt = $pdo->prepare("DELETE FROM " . table('event_registrations') . " WHERE event_id = ?");
    $stmt->execute([$eventId]);
    
    // Delete event
    $stmt = $pdo->prepare("DELETE FROM " . table('events') . " WHERE id = ?");
    $result = $stmt->execute([$eventId]);
    
    if ($event) {
        logDelete('event', $eventId, $event['title'], $event);
    }
    
    return $result;
}

/**
 * Get upcoming events
 */
function getUpcomingEvents($limit = 5) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('events') . " 
        WHERE status = 'published' AND event_date >= CURDATE()
        ORDER BY event_date ASC, event_time ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

/**
 * Get events by mode
 */
function getEventsByMode($mode) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('events') . " 
        WHERE event_mode = ? AND status = 'published'
        ORDER BY event_date ASC
    ");
    $stmt->execute([$mode]);
    return $stmt->fetchAll();
}

/**
 * Register member for event
 */
function registerForEvent($eventId, $memberId) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    $event = getEvent($eventId);
    if (!$event) {
        return false;
    }
    
    // Check if already registered
    if (isRegisteredForEvent($eventId, $memberId)) {
        return false;
    }
    
    // Check available spots
    $availableSpots = getAvailableSpots($eventId);
    if ($availableSpots !== null && $availableSpots <= 0) {
        // Add to waitlist
        $attendanceStatus = 'waitlist';
    } else {
        $attendanceStatus = 'registered';
    }
    
    // Determine payment status
    $paymentStatus = ($event['cost'] > 0) ? 'pending' : 'not_required';
    
    $stmt = $pdo->prepare("
        INSERT INTO " . table('event_registrations') . " 
        (event_id, member_id, payment_status, attendance_status)
        VALUES (?, ?, ?, ?)
    ");
    $result = $stmt->execute([$eventId, $memberId, $paymentStatus, $attendanceStatus]);
    
    if ($result) {
        logCreate('event_registration', $pdo->lastInsertId(), "Event {$eventId} - Member {$memberId}", [
            'event_id' => $eventId,
            'member_id' => $memberId
        ]);
    }
    
    return $result;
}

/**
 * Unregister from event
 */
function unregisterFromEvent($eventId, $memberId) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    // Get registration ID before deletion
    $stmt = $pdo->prepare("
        SELECT id FROM " . table('event_registrations') . " 
        WHERE event_id = ? AND member_id = ?
    ");
    $stmt->execute([$eventId, $memberId]);
    $registration = $stmt->fetch();
    $registrationId = $registration ? $registration['id'] : null;
    
    $stmt = $pdo->prepare("
        DELETE FROM " . table('event_registrations') . " 
        WHERE event_id = ? AND member_id = ?
    ");
    $result = $stmt->execute([$eventId, $memberId]);
    
    if ($result && $registrationId) {
        logDelete('event_registration', $registrationId, "Event {$eventId} - Member {$memberId}", [
            'event_id' => $eventId,
            'member_id' => $memberId
        ]);
    }
    
    return $result;
}

/**
 * Get event registrations
 */
function getEventRegistrations($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, m.email, m.membership_number
        FROM " . table('event_registrations') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        WHERE er.event_id = ?
        ORDER BY er.registered_at ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Get member registrations
 */
function getMemberRegistrations($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, e.title, e.event_date, e.event_time, e.event_mode, e.status
        FROM " . table('event_registrations') . " er
        JOIN " . table('events') . " e ON er.event_id = e.id
        WHERE er.member_id = ?
        ORDER BY e.event_date DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

/**
 * Check if member is registered for event
 */
function isRegisteredForEvent($eventId, $memberId) {
    global $pdo;
    
    // Check if member has an approved registration (said 'yes' and was approved)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM " . table('event_responses') . " 
        WHERE event_id = ? AND member_id = ? AND registration_status = 'approved'
    ");
    $stmt->execute([$eventId, $memberId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Get available spots for event
 */
function getAvailableSpots($eventId) {
    global $pdo;
    
    $event = getEvent($eventId);
    if (!$event || $event['max_participants'] == 0) {
        return null; // Unlimited
    }
    
    // Count approved registrations (members who said 'yes' and were approved)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM " . table('event_responses') . " 
        WHERE event_id = ? AND registration_status = 'approved'
    ");
    $stmt->execute([$eventId]);
    $result = $stmt->fetch();
    
    return max(0, $event['max_participants'] - $result['count']);
}

/**
 * Get waitlist position
 */
function getWaitlistPosition($eventId, $memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) + 1 as position FROM " . table('event_registrations') . " 
        WHERE event_id = ? 
        AND attendance_status = 'waitlist'
        AND registered_at < (
            SELECT registered_at FROM " . table('event_registrations') . " 
            WHERE event_id = ? AND member_id = ?
        )
    ");
    $stmt->execute([$eventId, $eventId, $memberId]);
    $result = $stmt->fetch();
    return $result['position'] ?? null;
}

/**
 * Send event confirmation email
 */
function sendEventConfirmation($eventId, $memberId) {
    global $pdo;
    require_once __DIR__ . '/email.php';
    
    $event = getEvent($eventId);
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch();
    
    if (!$event || !$member || !$member['email']) {
        return false;
    }
    
    $detailsMode = '';
    if ($event['event_mode'] == 'in_person') {
        $detailsMode = '<p><strong>Luogo:</strong> ' . h($event['location']) . '<br>';
        if ($event['address']) $detailsMode .= h($event['address']) . ', ';
        if ($event['city']) $detailsMode .= h($event['city']);
        $detailsMode .= '</p>';
    } elseif ($event['event_mode'] == 'online') {
        $detailsMode = '<p><strong>Modalità:</strong> Online su ' . h($event['online_platform']) . '<br>';
        $detailsMode .= 'Il link sarà inviato prima dell\'evento.</p>';
    } elseif ($event['event_mode'] == 'hybrid') {
        $detailsMode = '<p><strong>Modalità:</strong> Ibrido (In presenza e Online)</p>';
    }
    
    $variables = [
        'nome' => $member['first_name'],
        'cognome' => $member['last_name'],
        'titolo' => $event['title'],
        'data' => formatDate($event['event_date']),
        'ora' => $event['event_time'] ? substr($event['event_time'], 0, 5) : 'TBD',
        'dettagli_modalita' => $detailsMode
    ];
    
    return sendEmailFromTemplate($member['email'], 'event_registration', $variables);
}

/**
 * Send event reminder to all registered members
 */
function sendEventReminder($eventId) {
    global $pdo;
    require_once __DIR__ . '/email.php';
    
    $event = getEvent($eventId);
    if (!$event) {
        return 0;
    }
    
    // Get approved registrations from event_responses
    $registrations = getApprovedEventRegistrations($eventId);
    $sent = 0;
    
    foreach ($registrations as $reg) {
        if (!$reg['email']) continue;
        
        $detailsMode = '';
        if ($event['event_mode'] == 'in_person') {
            $detailsMode = '<p><strong>Luogo:</strong> ' . h($event['location']) . '<br>';
            if ($event['address']) $detailsMode .= h($event['address']) . ', ';
            if ($event['city']) $detailsMode .= h($event['city']);
            $detailsMode .= '</p>';
        } elseif ($event['event_mode'] == 'online') {
            $detailsMode = '<p><strong>Modalità:</strong> Online</p>';
        }
        
        $variables = [
            'nome' => $reg['first_name'],
            'cognome' => $reg['last_name'],
            'titolo' => $event['title'],
            'data' => formatDate($event['event_date']),
            'ora' => $event['event_time'] ? substr($event['event_time'], 0, 5) : 'TBD',
            'dettagli_modalita' => $detailsMode
        ];
        
        if (sendEmailFromTemplate($reg['email'], 'event_reminder', $variables)) {
            $sent++;
        }
    }
    
    return $sent;
}

/**
 * Send online link to registered members
 */
function sendOnlineLinkToRegistrants($eventId) {
    global $pdo;
    require_once __DIR__ . '/email.php';
    
    $event = getEvent($eventId);
    if (!$event || !in_array($event['event_mode'], ['online', 'hybrid'])) {
        return 0;
    }
    
    // Get approved registrations from event_responses
    $registrations = getApprovedEventRegistrations($eventId);
    $sent = 0;
    
    foreach ($registrations as $reg) {
        if (!$reg['email']) continue;
        
        $passwordInfo = '';
        if ($event['online_password']) {
            $passwordInfo = '<p><strong>Password:</strong> ' . h($event['online_password']) . '</p>';
        }
        
        $instructions = '';
        if ($event['online_instructions']) {
            $instructions = '<p><strong>Istruzioni:</strong><br>' . nl2br(h($event['online_instructions'])) . '</p>';
        }
        
        $variables = [
            'nome' => $reg['first_name'],
            'cognome' => $reg['last_name'],
            'titolo' => $event['title'],
            'data' => formatDate($event['event_date']),
            'ora' => $event['event_time'] ? substr($event['event_time'], 0, 5) : 'TBD',
            'piattaforma' => $event['online_platform'] ?? 'Piattaforma Online',
            'link' => $event['online_link'] ?? '',
            'password_info' => $passwordInfo,
            'istruzioni' => $instructions
        ];
        
        if (sendEmailFromTemplate($reg['email'], 'event_online_link', $variables)) {
            $sent++;
        }
    }
    
    return $sent;
}

// ============================================================================
// MASS EMAIL FUNCTIONS
// ============================================================================

/**
 * Get mass email recipients based on filter
 */
function getMassEmailRecipients($filter, $params = []) {
    global $pdo;
    
    $sql = "SELECT DISTINCT m.id, m.first_name, m.last_name, m.email, m.membership_number 
            FROM " . table('members') . " m WHERE m.email IS NOT NULL AND m.email != ''";
    $queryParams = [];
    
    switch ($filter) {
        case 'all':
            // All members with email
            break;
            
        case 'active_paid':
            // Members with paid fee for current year
            $currentYear = getCurrentSocialYear();
            if ($currentYear) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM " . table('member_fees') . " mf 
                    WHERE mf.member_id = m.id 
                    AND mf.social_year_id = ? 
                    AND mf.status = 'paid'
                )";
                $queryParams[] = $currentYear['id'];
            }
            break;
            
        case 'overdue':
            // Members with overdue fees
            $currentYear = getCurrentSocialYear();
            if ($currentYear) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM " . table('member_fees') . " mf 
                    WHERE mf.member_id = m.id 
                    AND mf.social_year_id = ? 
                    AND mf.status = 'overdue'
                )";
                $queryParams[] = $currentYear['id'];
            }
            break;
            
        case 'no_fee_current_year':
            // Members without fee for current year
            $currentYear = getCurrentSocialYear();
            if ($currentYear) {
                $sql .= " AND NOT EXISTS (
                    SELECT 1 FROM " . table('member_fees') . " mf 
                    WHERE mf.member_id = m.id 
                    AND mf.social_year_id = ?
                )";
                $queryParams[] = $currentYear['id'];
            }
            break;
            
        case 'event_registered':
            // Members registered for specific event
            if (!empty($params['event_id'])) {
                $sql .= " AND EXISTS (
                    SELECT 1 FROM " . table('event_registrations') . " er 
                    WHERE er.member_id = m.id 
                    AND er.event_id = ?
                )";
                $queryParams[] = $params['event_id'];
            }
            break;
            
        case 'manual':
            // Specific member IDs
            if (!empty($params['member_ids']) && is_array($params['member_ids'])) {
                if (count($params['member_ids']) === 0) {
                    // Return empty result for empty array
                    return [];
                }
                $placeholders = str_repeat('?,', count($params['member_ids']) - 1) . '?';
                $sql .= " AND m.id IN ($placeholders)";
                $queryParams = array_merge($queryParams, $params['member_ids']);
            } else {
                // No valid member IDs provided, return empty result
                return [];
            }
            break;
    }
    
    $sql .= " ORDER BY m.last_name, m.first_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    return $stmt->fetchAll();
}

/**
 * Count mass email recipients
 */
function countMassEmailRecipients($filter, $params = []) {
    $recipients = getMassEmailRecipients($filter, $params);
    return count($recipients);
}

// ============================================================================
// INCOME FROM FEES FUNCTIONS
// ============================================================================

/**
 * Get the ID of "Quote associative" income category
 * Creates it if it doesn't exist
 */
function getQuoteAssociativeCategory() {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT id FROM " . table('income_categories') . " WHERE name = 'Quote associative' LIMIT 1");
    $stmt->execute();
    $category = $stmt->fetch();
    
    if ($category) {
        return $category['id'];
    }
    
    // Get next available sort_order
    $stmt = $pdo->query("SELECT COALESCE(MAX(sort_order), 0) + 1 as next_order FROM " . table('income_categories'));
    $nextOrder = $stmt->fetch()['next_order'];
    
    // Create category if not exists
    $stmt = $pdo->prepare("INSERT INTO " . table('income_categories') . " (name, sort_order, is_active) VALUES ('Quote associative', ?, 1)");
    $stmt->execute([$nextOrder]);
    return $pdo->lastInsertId();
}

/**
 * Create income movement for a paid fee
 */
function createIncomeFromFee($feeData, $paymentDate = null) {
    global $pdo;
    
    $categoryId = getQuoteAssociativeCategory();
    $date = $paymentDate ?? $feeData['paid_date'] ?? date('Y-m-d');
    $feeId = $feeData['id'] ?? $feeData['fee_id'] ?? null;
    
    if (!$feeId) {
        throw new Exception("Fee ID is required to create income movement");
    }
    
    // Default payment method
    $defaultPaymentMethod = 'Contanti';
    
    $stmt = $pdo->prepare("
        INSERT INTO " . table('income') . " 
        (social_year_id, category_id, member_id, amount, transaction_date, payment_method, notes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $feeData['social_year_id'],
        $categoryId,
        $feeData['member_id'],
        $feeData['amount'],
        $date,
        $feeData['payment_method'] ?? $defaultPaymentMethod,
        'Quota associativa - Fee #' . $feeId
    ]);
    
    return $pdo->lastInsertId();
}

/**
 * Delete income movement linked to a fee
 */
function deleteIncomeFromFee($feeId) {
    global $pdo;
    
    // Use exact pattern match to avoid accidental deletions
    $stmt = $pdo->prepare("DELETE FROM " . table('income') . " WHERE notes = ?");
    $stmt->execute(['Quota associativa - Fee #' . $feeId]);
    
    return $stmt->rowCount();
}

/**
 * Queue mass email batch
 */
function queueMassEmail($recipientIds, $subject, $bodyHtml, $senderId) {
    global $pdo;
    require_once __DIR__ . '/audit.php';
    
    // Create batch record
    $stmt = $pdo->prepare("
        INSERT INTO " . table('mass_email_batches') . " 
        (subject, body_html, filter_type, total_recipients, created_by, status)
        VALUES (?, ?, 'manual', ?, ?, 'pending')
    ");
    $stmt->execute([$subject, $bodyHtml, count($recipientIds), $senderId]);
    $batchId = $pdo->lastInsertId();
    
    // Queue individual emails
    $recipients = getMassEmailRecipients('manual', ['member_ids' => $recipientIds]);
    
    foreach ($recipients as $recipient) {
        // Replace variables in subject and body
        $personalizedSubject = str_replace(
            ['{nome}', '{cognome}', '{email}', '{tessera}'],
            [$recipient['first_name'], $recipient['last_name'], $recipient['email'], $recipient['membership_number']],
            $subject
        );
        
        $personalizedBody = str_replace(
            ['{nome}', '{cognome}', '{email}', '{tessera}'],
            [h($recipient['first_name']), h($recipient['last_name']), h($recipient['email']), h($recipient['membership_number'])],
            $bodyHtml
        );
        
        queueEmail($recipient['email'], $personalizedSubject, $personalizedBody);
    }
    
    logCreate('mass_email_batch', $batchId, $subject, [
        'total_recipients' => count($recipientIds),
        'subject' => $subject
    ]);
    
    return $batchId;
}

/**
 * Get mass email batch status
 */
function getMassEmailStatus($batchId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('mass_email_batches') . " WHERE id = ?");
    $stmt->execute([$batchId]);
    return $stmt->fetch();
}

// =====================================================
// MEMBER GROUPS FUNCTIONS
// =====================================================

/**
 * Get a single member by ID
 */
function getMember($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ?");
    $stmt->execute([$memberId]);
    return $stmt->fetch();
}

/**
 * Get all member groups
 */
function getGroups($activeOnly = true) {
    global $pdo;
    
    $sql = "SELECT * FROM " . table('member_groups');
    if ($activeOnly) {
        $sql .= " WHERE is_active = 1";
    }
    $sql .= " ORDER BY name";
    
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get a single group by ID
 */
function getGroup($groupId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('member_groups') . " WHERE id = ?");
    $stmt->execute([$groupId]);
    return $stmt->fetch();
}

/**
 * Create a new member group
 */
function createGroup($data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        INSERT INTO " . table('member_groups') . " 
        (name, description, color, is_active, is_hidden, is_restricted) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $data['name'],
        $data['description'] ?? null,
        $data['color'] ?? '#6c757d',
        $data['is_active'] ?? true,
        $data['is_hidden'] ?? false,
        $data['is_restricted'] ?? false
    ]);
    
    $groupId = $pdo->lastInsertId();
    
    // Log the action
    logAudit('create', 'member_group', $groupId, $data['name']);
    
    return $groupId;
}

/**
 * Update a member group
 */
function updateGroup($groupId, $data) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('member_groups') . " 
        SET name = ?, description = ?, color = ?, is_active = ?, is_hidden = ?, is_restricted = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $data['name'],
        $data['description'] ?? null,
        $data['color'] ?? '#6c757d',
        $data['is_active'] ?? true,
        $data['is_hidden'] ?? false,
        $data['is_restricted'] ?? false,
        $groupId
    ]);
    
    // Log the action
    logAudit('update', 'member_group', $groupId, $data['name']);
    
    return true;
}

/**
 * Delete a member group
 */
function deleteGroup($groupId) {
    global $pdo;
    
    $group = getGroup($groupId);
    if (!$group) {
        return false;
    }
    
    $stmt = $pdo->prepare("DELETE FROM " . table('member_groups') . " WHERE id = ?");
    $stmt->execute([$groupId]);
    
    // Log the action
    logAudit('delete', 'member_group', $groupId, $group['name']);
    
    return true;
}

/**
 * Get members in a group
 */
function getGroupMembers($groupId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.*, mgm.added_at
        FROM " . table('members') . " m
        INNER JOIN " . table('member_group_members') . " mgm ON m.id = mgm.member_id
        WHERE mgm.group_id = ?
        ORDER BY m.last_name, m.first_name
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * Add a member to a group
 */
function addMemberToGroup($groupId, $memberId) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('member_group_members') . " 
            (group_id, member_id) 
            VALUES (?, ?)
        ");
        $stmt->execute([$groupId, $memberId]);
        
        // Log the action
        $group = getGroup($groupId);
        $member = getMember($memberId);
        logAudit('add_member_to_group', 'member_group', $groupId, 
                 $group['name'] . ' <- ' . $member['first_name'] . ' ' . $member['last_name']);
        
        return true;
    } catch (PDOException $e) {
        // Ignore duplicate key errors
        if ($e->getCode() == '23000') {
            return true;
        }
        throw $e;
    }
}

/**
 * Remove a member from a group
 */
function removeMemberFromGroup($groupId, $memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        DELETE FROM " . table('member_group_members') . " 
        WHERE group_id = ? AND member_id = ?
    ");
    $stmt->execute([$groupId, $memberId]);
    
    // Log the action
    $group = getGroup($groupId);
    $member = getMember($memberId);
    logAudit('remove_member_from_group', 'member_group', $groupId, 
             $group['name'] . ' -> ' . $member['first_name'] . ' ' . $member['last_name']);
    
    return true;
}

/**
 * Get groups that a member belongs to
 */
function getMemberGroups($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT g.*, mgm.added_at
        FROM " . table('member_groups') . " g
        INNER JOIN " . table('member_group_members') . " mgm ON g.id = mgm.group_id
        WHERE mgm.member_id = ?
        ORDER BY g.name
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

/**
 * Check if a member is in a group
 */
function isMemberInGroup($groupId, $memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM " . table('member_group_members') . "
        WHERE group_id = ? AND member_id = ?
    ");
    $stmt->execute([$groupId, $memberId]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
}

/**
 * Get target groups for an event
 */
function getEventTargetGroups($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT g.*
        FROM " . table('member_groups') . " g
        INNER JOIN " . table('event_target_groups') . " etg ON g.id = etg.group_id
        WHERE etg.event_id = ?
        ORDER BY g.name
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Set target groups for an event
 */
function setEventTargetGroups($eventId, $groupIds) {
    global $pdo;
    
    // First, delete existing target groups
    $stmt = $pdo->prepare("DELETE FROM " . table('event_target_groups') . " WHERE event_id = ?");
    $stmt->execute([$eventId]);
    
    // Then, add the new target groups
    if (!empty($groupIds)) {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('event_target_groups') . " 
            (event_id, group_id) 
            VALUES (?, ?)
        ");
        
        foreach ($groupIds as $groupId) {
            $stmt->execute([$eventId, $groupId]);
        }
    }
    
    return true;
}

/**
 * Get members who are targets of an event (based on target_type and groups)
 */
function getEventTargetMembers($eventId) {
    global $pdo;
    
    $event = getEvent($eventId);
    if (!$event) {
        return [];
    }
    
    // If target_type is 'all', return all active members with email
    if ($event['target_type'] == 'all') {
        $stmt = $pdo->prepare("
            SELECT * FROM " . table('members') . "
            WHERE status = 'attivo' AND email IS NOT NULL AND email != ''
            ORDER BY last_name, first_name
        ");
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    // If target_type is 'groups', return members in the target groups
    $stmt = $pdo->prepare("
        SELECT DISTINCT m.*
        FROM " . table('members') . " m
        INNER JOIN " . table('member_group_members') . " mgm ON m.id = mgm.member_id
        INNER JOIN " . table('event_target_groups') . " etg ON mgm.group_id = etg.group_id
        WHERE etg.event_id = ? 
          AND m.status = 'attivo' 
          AND m.email IS NOT NULL 
          AND m.email != ''
        ORDER BY m.last_name, m.first_name
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Export group members to CSV
 */
function exportGroupMembersCsv($groupId) {
    $group = getGroup($groupId);
    if (!$group) {
        return false;
    }
    
    $members = getGroupMembers($groupId);
    
    $filename = 'gruppo_' . preg_replace('/[^a-z0-9]+/', '_', strtolower($group['name'])) . '_' . date('Y-m-d') . '.csv';
    
    $headers = ['Nome', 'Cognome', 'Email', 'Telefono', 'Numero Tessera', 'Data Iscrizione'];
    
    $data = [];
    foreach ($members as $member) {
        $data[] = [
            $member['first_name'],
            $member['last_name'],
            $member['email'] ?? '',
            $member['phone'] ?? '',
            $member['membership_number'] ?? '',
            formatDate($member['registration_date'])
        ];
    }
    
    exportCsv($filename, $data, $headers);
}

/**
 * Get member count for a group
 */
function getGroupMemberCount($groupId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM " . table('member_group_members') . "
        WHERE group_id = ?
    ");
    $stmt->execute([$groupId]);
    $result = $stmt->fetch();
    return $result['count'];
}

// ============================================================================
// SETTINGS FUNCTIONS
// ============================================================================

/**
 * Get a single setting value
 * 
 * @param string $key Setting key
 * @param mixed $default Default value if setting not found
 * @return mixed Setting value or default
 */
function getSetting($key, $default = null) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM " . table('settings') . " WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        return $default;
    }
}

/**
 * Get all settings as associative array
 * 
 * @return array Associative array of all settings
 */
function getAllSettings() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM " . table('settings'));
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Get settings by group
 * 
 * @param string $group Setting group name
 * @return array Associative array of settings in the group
 */
function getSettingsByGroup($group) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM " . table('settings') . " WHERE setting_group = ?");
        $stmt->execute([$group]);
        $settings = [];
        
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        
        return $settings;
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Set a single setting value
 * 
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param string $group Setting group (default: 'general')
 * @return bool Success
 */
function setSetting($key, $value, $group = 'general') {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('settings') . " (setting_key, setting_value, setting_group)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE setting_value = ?, setting_group = ?
        ");
        $stmt->execute([$key, $value, $group, $value, $group]);
        return true;
    } catch (PDOException $e) {
        error_log("Error setting value: " . $e->getMessage());
        return false;
    }
}

/**
 * Set multiple settings at once
 * 
 * @param array $settings Associative array of settings [key => value] or [[key, value, group], ...]
 * @return bool Success
 */
function setSettings($settings) {
    $allSuccess = true;
    foreach ($settings as $key => $value) {
        if (is_array($value)) {
            // Format: [key, value, group]
            $result = setSetting($value[0], $value[1], $value[2] ?? 'general');
        } else {
            // Format: key => value
            $result = setSetting($key, $value);
        }
        if (!$result) {
            $allSuccess = false;
        }
    }
    return $allSuccess;
}

/**
 * Get formatted association address (multiline)
 * 
 * @return string Formatted address
 */
function getAssociationAddress() {
    $parts = [];
    
    if ($street = getSetting('address_street')) {
        $parts[] = $street;
    }
    
    $cityLine = '';
    if ($cap = getSetting('address_cap')) {
        $cityLine .= $cap . ' ';
    }
    if ($city = getSetting('address_city')) {
        $cityLine .= $city;
    }
    if ($province = getSetting('address_province')) {
        $cityLine .= ' (' . $province . ')';
    }
    if ($cityLine) {
        $parts[] = trim($cityLine);
    }
    
    return implode("\n", $parts);
}

/**
 * Get formatted association data for receipts/emails
 * 
 * @return array Association information
 */
function getAssociationInfo() {
    return [
        'name' => getSetting('association_name', 'Associazione'),
        'full_name' => getSetting('association_full_name'),
        'logo' => getSetting('association_logo'),
        'slogan' => getSetting('association_slogan'),
        'address' => getAssociationAddress(),
        'phone' => getSetting('contact_phone'),
        'email' => getSetting('contact_email'),
        'pec' => getSetting('contact_pec'),
        'website' => getSetting('contact_website'),
        'fiscal_cf' => getSetting('fiscal_cf'),
        'fiscal_piva' => getSetting('fiscal_piva'),
        'fiscal_rea' => getSetting('fiscal_rea'),
        'fiscal_registry' => getSetting('fiscal_registry'),
        'legal_representative_name' => getSetting('legal_representative_name'),
        'legal_representative_role' => getSetting('legal_representative_role'),
    ];
}

/**
 * Get bank details formatted
 * 
 * @return array Bank details
 */
function getBankDetails() {
    return [
        'iban' => getSetting('bank_iban'),
        'holder' => getSetting('bank_holder'),
        'bank_name' => getSetting('bank_name'),
        'bic' => getSetting('bank_bic'),
    ];
}

/**
 * Get email footer with all association info
 * 
 * @return string HTML formatted email footer
 */
function getEmailFooter() {
    $info = getAssociationInfo();
    
    $footer = '<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; font-size: 12px; color: #666;">';
    
    // Custom signature if set
    if ($signature = getSetting('email_signature')) {
        $footer .= '<p>' . nl2br(h($signature)) . '</p>';
    }
    
    // Association info
    $footer .= '<p><strong>' . h($info['name']) . '</strong><br>';
    
    if ($info['address']) {
        $footer .= nl2br(h($info['address'])) . '<br>';
    }
    
    if ($info['phone']) {
        $footer .= 'Tel: ' . h($info['phone']) . '<br>';
    }
    
    if ($info['email']) {
        $footer .= 'Email: ' . h($info['email']) . '<br>';
    }
    
    if ($info['website']) {
        $footer .= 'Web: ' . h($info['website']) . '<br>';
    }
    
    $footer .= '</p>';
    
    // Custom footer if set
    if ($customFooter = getSetting('email_footer')) {
        $footer .= '<p style="font-size: 11px;">' . nl2br(h($customFooter)) . '</p>';
    }
    
    $footer .= '</div>';
    
    return $footer;
}

/**
 * Get base URL for the application
 * 
 * @return string Base URL with protocol and host
 */
function getBaseUrl() {
    global $config;
    
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
                ($_SERVER['SERVER_PORT'] ?? 80) == 443 ? 'https://' : 'http://';
    
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $basePath = $config['app']['base_path'] ?? '/';
    
    return $protocol . $host . $basePath;
}

/**
 * Get member by ID
 * 
 * @param int $memberId Member ID
 * @return array|false Member data or false if not found
 */
/**
 * Send portal activation email
 * 
 * @param int $memberId Member ID
 * @return bool Success
 */
function sendPortalActivationEmail($memberId) {
    global $pdo;
    
    $member = getMember($memberId);
    if (!$member || empty($member['email'])) {
        return false;
    }
    
    require_once __DIR__ . '/../public/portal/inc/auth.php';
    $token = generatePortalToken($memberId);
    $activationLink = getBaseUrl() . 'portal/register.php?token=' . $token;
    
    // Send email
    $subject = 'Attiva il tuo account - ' . getSetting('association_name', 'Associazione');
    $body = "Ciao {$member['first_name']},<br><br>";
    $body .= "Clicca sul link seguente per attivare il tuo accesso all'area soci:<br><br>";
    $body .= "<a href='{$activationLink}'>{$activationLink}</a><br><br>";
    $body .= "Il link scade tra 24 ore.<br><br>";
    $body .= "Se non hai richiesto questo link, ignora questa email.";
    $body .= getEmailFooter();
    
    return sendEmail($member['email'], $subject, $body);
}

/**
 * Send portal password reset email
 * 
 * @param int $memberId Member ID
 * @return bool Success
 */
function sendPortalPasswordResetEmail($memberId) {
    global $pdo;
    
    $member = getMember($memberId);
    if (!$member || empty($member['email'])) {
        return false;
    }
    
    require_once __DIR__ . '/../public/portal/inc/auth.php';
    $token = generatePortalToken($memberId);
    $resetLink = getBaseUrl() . 'portal/reset_password.php?token=' . $token;
    
    // Send email
    $subject = 'Recupero Password - ' . getSetting('association_name', 'Associazione');
    $body = "Ciao {$member['first_name']},<br><br>";
    $body .= "Hai richiesto il recupero della password per l'accesso al portale soci.<br><br>";
    $body .= "Clicca sul link seguente per reimpostare la password:<br><br>";
    $body .= "<a href='{$resetLink}'>{$resetLink}</a><br><br>";
    $body .= "Il link scade tra 24 ore.<br><br>";
    $body .= "Se non hai richiesto questo recupero, ignora questa email.";
    $body .= getEmailFooter();
    
    return sendEmail($member['email'], $subject, $body);
}


/**
 * =============================================================================
 * EVENT RESPONSES FUNCTIONS (Portal Soci - Part 2)
 * =============================================================================
 */

/**
 * Get events visible to a member (all + their groups)
 * 
 * @param int $memberId Member ID
 * @param bool $upcomingOnly Only show upcoming events (default: true)
 * @return array List of visible events
 */
function getMemberVisibleEvents($memberId, $upcomingOnly = true) {
    global $pdo;
    
    // Get member's groups
    $memberGroups = getMemberGroups($memberId);
    $groupIds = array_column($memberGroups, 'id');
    
    // Build query to get events for "all" OR events for member's groups
    $sql = "SELECT DISTINCT e.* FROM " . table('events') . " e
            WHERE e.status = 'published'";
    
    if ($upcomingOnly) {
        $sql .= " AND e.event_date >= CURDATE()";
    }
    
    $sql .= " AND (e.target_type = 'all'";
    
    // If member has groups, include events targeted to those groups
    if (!empty($groupIds)) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql .= " OR (e.target_type = 'groups' AND e.id IN (
                    SELECT event_id FROM " . table('event_target_groups') . " 
                    WHERE group_id IN ($placeholders)
                  ))";
    }
    
    $sql .= ") ORDER BY e.event_date ASC, e.event_time ASC";
    
    $stmt = $pdo->prepare($sql);
    if (!empty($groupIds)) {
        $stmt->execute($groupIds);
    } else {
        $stmt->execute();
    }
    
    return $stmt->fetchAll();
}

/**
 * Get member's response to an event
 * 
 * @param int $eventId Event ID
 * @param int $memberId Member ID
 * @return array|false Event response or false if not found
 */
function getMemberEventResponse($eventId, $memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('event_responses') . "
        WHERE event_id = ? AND member_id = ?
    ");
    $stmt->execute([$eventId, $memberId]);
    return $stmt->fetch();
}

/**
 * Set member's response to an event
 * 
 * @param int $eventId Event ID
 * @param int $memberId Member ID
 * @param string $response Response type: 'yes', 'no', or 'maybe'
 * @param string|null $notes Optional notes
 * @return bool Success
 */
function setMemberEventResponse($eventId, $memberId, $response, $notes = null) {
    global $pdo;
    
    // Validate response
    if (!in_array($response, ['yes', 'no', 'maybe'])) {
        return false;
    }
    
    // Check if response already exists
    $existing = getMemberEventResponse($eventId, $memberId);
    
    if ($existing) {
        // Update existing response
        $stmt = $pdo->prepare("
            UPDATE " . table('event_responses') . "
            SET response = ?, notes = ?, updated_at = NOW()
            WHERE event_id = ? AND member_id = ?
        ");
        return $stmt->execute([$response, $notes, $eventId, $memberId]);
    } else {
        // Insert new response
        $stmt = $pdo->prepare("
            INSERT INTO " . table('event_responses') . "
            (event_id, member_id, response, notes)
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$eventId, $memberId, $response, $notes]);
    }
}

/**
 * Get all responses for an event (admin view)
 * 
 * @param int $eventId Event ID
 * @return array List of responses with member info
 */
function getEventResponses($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, m.email
        FROM " . table('event_responses') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        WHERE er.event_id = ?
        ORDER BY er.responded_at DESC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Count responses by type for an event
 * 
 * @param int $eventId Event ID
 * @return array Counts by response type
 */
function countEventResponses($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT response, COUNT(*) as count
        FROM " . table('event_responses') . "
        WHERE event_id = ?
        GROUP BY response
    ");
    $stmt->execute([$eventId]);
    
    $counts = ['yes' => 0, 'no' => 0, 'maybe' => 0];
    while ($row = $stmt->fetch()) {
        $counts[$row['response']] = (int)$row['count'];
    }
    
    return $counts;
}

/**
 * Delete an event response by ID
 * 
 * @param int $responseId Response ID
 * @return bool Success
 */
function deleteEventResponse($responseId) {
    global $pdo;
    
    // Get response details for logging
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, e.title
        FROM " . table('event_responses') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        JOIN " . table('events') . " e ON er.event_id = e.id
        WHERE er.id = ?
    ");
    $stmt->execute([$responseId]);
    $response = $stmt->fetch();
    
    if (!$response) {
        return false;
    }
    
    // Delete the response
    $stmt = $pdo->prepare("DELETE FROM " . table('event_responses') . " WHERE id = ?");
    $result = $stmt->execute([$responseId]);
    
    // Log the action
    if ($result) {
        logAudit('delete', 'event_response', $responseId, 
                 $response['title'] . ' - ' . $response['first_name'] . ' ' . $response['last_name']);
    }
    
    return $result;
}

/**
 * Approve a single event registration
 */
function approveEventRegistration($responseId, $userId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('event_responses') . "
        SET registration_status = 'approved', 
            approved_by = ?,
            approved_at = NOW()
        WHERE id = ?
    ");
    return $stmt->execute([$userId, $responseId]);
}

/**
 * Reject a single event registration
 */
function rejectEventRegistration($responseId, $userId = null, $reason = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('event_responses') . "
        SET registration_status = 'rejected', 
            approved_by = ?,
            approved_at = NOW(),
            rejection_reason = ?
        WHERE id = ?
    ");
    return $stmt->execute([$userId, $reason, $responseId]);
}

/**
 * Approve all pending registrations for an event (only 'yes' responses)
 */
function approveAllEventRegistrations($eventId, $userId = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('event_responses') . "
        SET registration_status = 'approved', 
            approved_by = ?,
            approved_at = NOW()
        WHERE event_id = ? 
        AND response = 'yes' 
        AND registration_status = 'pending'
    ");
    $stmt->execute([$userId, $eventId]);
    return $stmt->rowCount();
}

/**
 * Reject all pending registrations for an event
 */
function rejectAllEventRegistrations($eventId, $userId = null, $reason = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('event_responses') . "
        SET registration_status = 'rejected', 
            approved_by = ?,
            approved_at = NOW(),
            rejection_reason = ?
        WHERE event_id = ? 
        AND registration_status = 'pending'
    ");
    $stmt->execute([$userId, $reason, $eventId]);
    return $stmt->rowCount();
}

/**
 * Get approved registrations for an event
 */
function getApprovedEventRegistrations($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, m.email, m.membership_number,
               u.full_name as approved_by_name
        FROM " . table('event_responses') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        LEFT JOIN " . table('users') . " u ON er.approved_by = u.id
        WHERE er.event_id = ? AND er.registration_status = 'approved'
        ORDER BY er.approved_at DESC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Get pending registrations for an event
 */
function getPendingEventRegistrations($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, m.email, m.membership_number
        FROM " . table('event_responses') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        WHERE er.event_id = ? AND er.registration_status = 'pending'
        ORDER BY er.responded_at ASC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Get rejected registrations for an event
 */
function getRejectedEventRegistrations($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT er.*, m.first_name, m.last_name, m.email, m.membership_number,
               u.full_name as approved_by_name
        FROM " . table('event_responses') . " er
        JOIN " . table('members') . " m ON er.member_id = m.id
        LEFT JOIN " . table('users') . " u ON er.approved_by = u.id
        WHERE er.event_id = ? AND er.registration_status = 'rejected'
        ORDER BY er.approved_at DESC
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

/**
 * Revoke an approved registration (back to pending)
 */
function revokeEventRegistration($responseId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('event_responses') . "
        SET registration_status = 'pending', 
            approved_by = NULL,
            approved_at = NULL,
            rejection_reason = NULL
        WHERE id = ?
    ");
    return $stmt->execute([$responseId]);
}

/**
 * Count pending registrations for an event
 */
function countPendingEventRegistrations($eventId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count
        FROM " . table('event_responses') . "
        WHERE event_id = ? AND registration_status = 'pending'
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetch()['count'];
}

/**
 * Get member's registration status for an event
 */
function getMemberEventRegistrationStatus($eventId, $memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT registration_status, response, rejection_reason
        FROM " . table('event_responses') . "
        WHERE event_id = ? AND member_id = ?
    ");
    $stmt->execute([$eventId, $memberId]);
    return $stmt->fetch();
}

/**
 * Get members NOT in a specific group (for add dropdown)
 * 
 * @param int $groupId Group ID
 * @return array List of members not in the group
 */
function getMembersNotInGroup($groupId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT m.* 
        FROM " . table('members') . " m
        WHERE m.status = 'attivo'
        AND m.id NOT IN (
            SELECT member_id FROM " . table('member_group_members') . " WHERE group_id = ?
        )
        ORDER BY m.last_name, m.first_name
    ");
    $stmt->execute([$groupId]);
    return $stmt->fetchAll();
}

/**
 * =============================================================================
 * GROUP REQUEST FUNCTIONS (Portal Soci - Part 2)
 * =============================================================================
 */

/**
 * Get public groups (not hidden, not restricted)
 * 
 * @return array List of public groups
 */
function getPublicGroups() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT * FROM " . table('member_groups') . " 
        WHERE is_active = 1 AND is_hidden = 0 AND is_restricted = 0
        ORDER BY name
    ");
    return $stmt->fetchAll();
}

/**
 * Create a group join request
 * 
 * @param int $memberId Member ID
 * @param int $groupId Group ID
 * @param string|null $message Optional message from member
 * @return bool Success
 */
function createGroupRequest($memberId, $groupId, $message = null) {
    global $pdo;
    
    // Check if member is already in the group
    if (isMemberInGroup($groupId, $memberId)) {
        return false;
    }
    
    // Check if there's already a pending request
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM " . table('member_group_requests') . "
        WHERE member_id = ? AND group_id = ? AND status = 'pending'
    ");
    $stmt->execute([$memberId, $groupId]);
    $result = $stmt->fetch();
    
    if ($result['count'] > 0) {
        return false; // Already has pending request
    }
    
    // Create the request
    $stmt = $pdo->prepare("
        INSERT INTO " . table('member_group_requests') . "
        (member_id, group_id, message)
        VALUES (?, ?, ?)
    ");
    return $stmt->execute([$memberId, $groupId, $message]);
}

/**
 * Get pending requests for a member
 * 
 * @param int $memberId Member ID
 * @return array List of pending requests with group info
 */
function getMemberGroupRequests($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT gr.*, g.name as group_name, g.description as group_description
        FROM " . table('member_group_requests') . " gr
        JOIN " . table('member_groups') . " g ON gr.group_id = g.id
        WHERE gr.member_id = ?
        ORDER BY gr.requested_at DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

/**
 * Get pending group requests (admin view)
 * 
 * @return array List of pending requests with member and group info
 */
function getPendingGroupRequests() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT gr.*, 
               m.first_name, m.last_name, m.email, m.membership_number,
               g.name as group_name
        FROM " . table('member_group_requests') . " gr
        JOIN " . table('members') . " m ON gr.member_id = m.id
        JOIN " . table('member_groups') . " g ON gr.group_id = g.id
        WHERE gr.status = 'pending'
        ORDER BY gr.requested_at ASC
    ");
    return $stmt->fetchAll();
}

/**
 * Count pending group requests
 * 
 * @return int Number of pending requests
 */
function countPendingGroupRequests() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM " . table('member_group_requests') . "
        WHERE status = 'pending'
    ");
    $result = $stmt->fetch();
    return (int)$result['count'];
}

/**
 * Approve group request
 * 
 * @param int $requestId Request ID
 * @param int $adminId Admin user ID
 * @param string|null $notes Optional admin notes
 * @return bool Success
 */
function approveGroupRequest($requestId, $adminId, $notes = null) {
    global $pdo;
    
    // Get request details
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('member_group_requests') . "
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$requestId]);
    $request = $stmt->fetch();
    
    if (!$request) {
        return false;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Add member to group
        $stmt = $pdo->prepare("
            INSERT INTO " . table('member_group_members') . "
            (group_id, member_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$request['group_id'], $request['member_id']]);
        
        // Update request status
        $stmt = $pdo->prepare("
            UPDATE " . table('member_group_requests') . "
            SET status = 'approved', 
                processed_at = NOW(), 
                processed_by = ?,
                admin_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $notes, $requestId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Reject group request
 * 
 * @param int $requestId Request ID
 * @param int $adminId Admin user ID
 * @param string|null $notes Optional admin notes
 * @return bool Success
 */
function rejectGroupRequest($requestId, $adminId, $notes = null) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('member_group_requests') . "
        SET status = 'rejected', 
            processed_at = NOW(), 
            processed_by = ?,
            admin_notes = ?
        WHERE id = ? AND status = 'pending'
    ");
    return $stmt->execute([$adminId, $notes, $requestId]);
}

/**
 * =============================================================================
 * PAYMENT FUNCTIONS (Portal Soci - Part 2)
 * =============================================================================
 */

/**
 * Confirm offline payment
 * 
 * @param int $feeId Fee ID
 * @param int $adminId Admin user ID
 * @return bool Success
 */
function confirmOfflinePayment($feeId, $adminId) {
    global $pdo;
    require_once __DIR__ . '/pdf.php';
    
    // Start transaction
    $pdo->beginTransaction();
    
    try {
        // Update fee status
        $stmt = $pdo->prepare("
            UPDATE " . table('member_fees') . " 
            SET status = 'paid',
                paid_date = NOW(),
                payment_pending = 0,
                payment_confirmed_by = ?,
                payment_confirmed_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$adminId, $feeId]);
        
        // Get fee details
        $stmt = $pdo->prepare("
            SELECT f.*, m.first_name, m.last_name, sy.name as year_name
            FROM " . table('member_fees') . " f
            JOIN " . table('members') . " m ON f.member_id = m.id
            LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
            WHERE f.id = ?
        ");
        $stmt->execute([$feeId]);
        $fee = $stmt->fetch();
        
        if (!$fee) {
            $pdo->rollBack();
            return false;
        }
        
        // Generate receipt if not already generated (using new receipts table)
        // Check if receipt already exists in new receipts table
        $stmt = $pdo->prepare("SELECT id FROM " . table('receipts') . " WHERE member_fee_id = ?");
        $stmt->execute([$feeId]);
        if (!$stmt->fetch()) {
            // Generate receipt with bank_transfer as payment method (offline payment)
            $paymentMethod = $fee['payment_method'] ?? 'bank_transfer';
            generateReceipt($feeId, $paymentMethod, null, $adminId);
        }
        
        // Create financial movement (income)
        $stmt = $pdo->prepare("
            INSERT INTO " . table('income') . "
            (social_year_id, category_id, member_id, amount, payment_method, 
             receipt_number, transaction_date, notes)
            SELECT f.social_year_id, 
                   (SELECT id FROM " . table('income_categories') . " WHERE name = 'Quote associative' LIMIT 1),
                   f.member_id,
                   f.amount,
                   COALESCE(f.payment_method, 'Bonifico'),
                   f.receipt_number,
                   f.paid_date,
                   CONCAT('Quota associativa - ', sy.name)
            FROM " . table('member_fees') . " f
            LEFT JOIN " . table('social_years') . " sy ON f.social_year_id = sy.id
            WHERE f.id = ?
        ");
        $stmt->execute([$feeId]);
        
        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error confirming payment: " . $e->getMessage());
        return false;
    }
}

/**
 * Count pending payments
 * 
 * @return int Number of pending payments
 */
function countPendingPayments() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT COUNT(*) as count FROM " . table('member_fees') . "
        WHERE payment_pending = 1
    ");
    $result = $stmt->fetch();
    return (int)$result['count'];
}

/**
 * Generate a secure token for receipt viewing
 * 
 * @param int $receiptId Receipt/Fee ID
 * @param int $memberId Member ID
 * @return string Secure token
 */
function generateReceiptToken($receiptId, $memberId) {
    // Simple token generation - can be enhanced with more security
    return hash('sha256', $receiptId . '-' . $memberId . '-receipt-' . date('Y-m-d'));
}

/**
 * Get logo URL handling both external and local paths
 * 
 * @param string|null $logoPath Logo path from settings
 * @param string $basePath Base path for local files
 * @return string Logo URL or empty string
 */
function getLogoUrl($logoPath, $basePath) {
    if (empty($logoPath)) {
        return '';
    }
    
    // Check if it's an external URL (http:// or https://)
    if (preg_match('/^https?:\/\//', $logoPath)) {
        return $logoPath;
    }
    
    // Local path - remove leading slashes and prepend basePath
    $cleanPath = ltrim($logoPath, '/');
    return $basePath . $cleanPath;
}

/**
 * Validate and sanitize image URL to prevent XSS
 * 
 * @param string|null $url Image URL to validate
 * @return string|null Validated URL or null if invalid
 */
function validateImageUrl($url) {
    if (empty($url)) {
        return null;
    }
    
    // Check for valid HTTP/HTTPS URLs
    if (preg_match('/^https?:\/\//i', $url)) {
        // Validate URL format
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }
        
        // Parse and verify scheme is http or https only
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return null;
        }
        
        return $url;
    }
    
    // Check for local paths (must start with / and not contain scheme-like patterns)
    if (preg_match('/^\/[^:]*$/i', $url)) {
        // Decode URL to check for malicious patterns
        $decoded = urldecode($url);
        
        // Reject if decoded version contains suspicious patterns
        if (strpos($decoded, '..') !== false || // Directory traversal
            strpos($decoded, "\0") !== false || // Null bytes
            strpos($decoded, "\n") !== false || // Newlines
            strpos($decoded, "\r") !== false) { // Carriage returns
            return null;
        }
        
        return $url;
    }
    
    // Reject everything else (javascript:, data:, etc.)
    return null;
}

/**
 * Mask fiscal code for privacy
 * Shows first 3 characters, asterisks, and last 2 characters
 * Fully masks codes shorter than 5 characters
 * 
 * @param string|null $fiscalCode Fiscal code to mask
 * @return string Masked fiscal code
 */
function maskFiscalCode($fiscalCode) {
    if (empty($fiscalCode)) {
        return '';
    }
    
    $fcLen = strlen($fiscalCode);
    
    // For codes with 5 or more characters, show first 3 and last 2
    if ($fcLen >= 5) {
        return substr($fiscalCode, 0, 3) . str_repeat('*', $fcLen - 5) . substr($fiscalCode, -2);
    }
    
    // For shorter codes, fully mask for safety
    return str_repeat('*', $fcLen);
}

// ============================================================================
// NEWS/BLOG FUNCTIONS
// ============================================================================

/**
 * Generate a URL-friendly slug from text
 * 
 * @param string $text Text to convert to slug
 * @param int|null $newsId News ID to exclude when checking uniqueness
 * @return string Unique slug
 */
function generateSlug($text, $newsId = null) {
    global $pdo;
    
    // Convert to lowercase
    $slug = strtolower($text);
    
    // Replace spaces and special characters with hyphens
    $slug = preg_replace('/[^\p{L}\p{N}]+/u', '-', $slug);
    
    // Remove leading/trailing hyphens
    $slug = trim($slug, '-');
    
    // Check if slug exists and make it unique if needed
    $originalSlug = $slug;
    $counter = 1;
    
    while (true) {
        $sql = "SELECT COUNT(*) as count FROM " . table('news') . " WHERE slug = ?";
        $params = [$slug];
        
        if ($newsId) {
            $sql .= " AND id != ?";
            $params[] = $newsId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        
        if ($result['count'] == 0) {
            break;
        }
        
        $slug = $originalSlug . '-' . $counter;
        $counter++;
    }
    
    return $slug;
}

/**
 * Save news (create or update)
 * 
 * @param array $data News data
 * @param int|null $newsId News ID for update, null for create
 * @return int News ID
 */
function saveNews($data, $newsId = null) {
    global $pdo;
    
    // Generate slug if not provided
    if (empty($data['slug'])) {
        $data['slug'] = generateSlug($data['title'], $newsId);
    }
    
    // Set published_at if status is published and not already set
    if ($data['status'] === 'published' && empty($data['published_at'])) {
        $data['published_at'] = date('Y-m-d H:i:s');
    }
    
    if ($newsId) {
        // Update existing news
        $stmt = $pdo->prepare("
            UPDATE " . table('news') . " 
            SET title = ?, slug = ?, content = ?, excerpt = ?, cover_image = ?,
                author_id = ?, target_type = ?, status = ?, published_at = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['content'],
            $data['excerpt'] ?? null,
            $data['cover_image'] ?? null,
            $data['author_id'],
            $data['target_type'] ?? 'all',
            $data['status'] ?? 'draft',
            $data['published_at'] ?? null,
            $newsId
        ]);
    } else {
        // Create new news
        $stmt = $pdo->prepare("
            INSERT INTO " . table('news') . " 
            (title, slug, content, excerpt, cover_image, author_id, target_type, status, published_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $data['slug'],
            $data['content'],
            $data['excerpt'] ?? null,
            $data['cover_image'] ?? null,
            $data['author_id'],
            $data['target_type'] ?? 'all',
            $data['status'] ?? 'draft',
            $data['published_at'] ?? null
        ]);
        $newsId = $pdo->lastInsertId();
    }
    
    // Handle target groups
    if ($data['target_type'] === 'groups' && isset($data['group_ids'])) {
        // Delete existing groups
        $stmt = $pdo->prepare("DELETE FROM " . table('news_groups') . " WHERE news_id = ?");
        $stmt->execute([$newsId]);
        
        // Insert new groups
        if (!empty($data['group_ids'])) {
            $stmt = $pdo->prepare("
                INSERT INTO " . table('news_groups') . " (news_id, group_id) VALUES (?, ?)
            ");
            foreach ($data['group_ids'] as $groupId) {
                $stmt->execute([$newsId, $groupId]);
            }
        }
    }
    
    return $newsId;
}

/**
 * Get news with filters and pagination
 * 
 * @param array $filters Filter options
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return array News list
 */
function getNews($filters = [], $page = 1, $perPage = 10) {
    global $pdo;
    
    $sql = "SELECT n.*, u.full_name as author_name
            FROM " . table('news') . " n
            LEFT JOIN " . table('users') . " u ON n.author_id = u.id
            WHERE 1=1";
    $params = [];
    
    // Filter by status
    if (!empty($filters['status'])) {
        $sql .= " AND n.status = ?";
        $params[] = $filters['status'];
    }
    
    // Filter by author
    if (!empty($filters['author_id'])) {
        $sql .= " AND n.author_id = ?";
        $params[] = $filters['author_id'];
    }
    
    // Filter by target type
    if (!empty($filters['target_type'])) {
        $sql .= " AND n.target_type = ?";
        $params[] = $filters['target_type'];
    }
    
    // Search by title or content
    if (!empty($filters['search'])) {
        $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
        $searchTerm = '%' . $filters['search'] . '%';
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    $sql .= " ORDER BY n.created_at DESC";
    
    // Add pagination
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get a single news by ID
 * 
 * @param int $newsId News ID
 * @return array|false News data or false if not found
 */
function getNewsById($newsId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT n.*, u.full_name as author_name
        FROM " . table('news') . " n
        LEFT JOIN " . table('users') . " u ON n.author_id = u.id
        WHERE n.id = ?
    ");
    $stmt->execute([$newsId]);
    return $stmt->fetch();
}

/**
 * Get news by slug
 * 
 * @param string $slug News slug
 * @return array|false News data or false if not found
 */
function getNewsBySlug($slug) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT n.*, u.full_name as author_name
        FROM " . table('news') . " n
        LEFT JOIN " . table('users') . " u ON n.author_id = u.id
        WHERE n.slug = ?
    ");
    $stmt->execute([$slug]);
    return $stmt->fetch();
}

/**
 * Get news visible to a member (respects group targeting)
 * 
 * @param int $memberId Member ID
 * @param int $page Page number (1-indexed)
 * @param int $perPage Items per page
 * @return array News list
 */
function getNewsForMember($memberId, $page = 1, $perPage = 5) {
    global $pdo;
    
    // Get member's groups
    $memberGroups = getMemberGroups($memberId);
    $groupIds = array_column($memberGroups, 'id');
    
    $sql = "SELECT DISTINCT n.*, u.full_name as author_name
            FROM " . table('news') . " n
            LEFT JOIN " . table('users') . " u ON n.author_id = u.id
            WHERE n.status = 'published' AND n.published_at <= NOW()
            AND (n.target_type = 'all'";
    
    $params = [];
    
    // If member has groups, include news targeted to those groups
    if (!empty($groupIds)) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql .= " OR (n.target_type = 'groups' AND n.id IN (
                    SELECT news_id FROM " . table('news_groups') . " 
                    WHERE group_id IN ($placeholders)
                  ))";
        $params = array_merge($params, $groupIds);
    }
    
    $sql .= ") ORDER BY n.published_at DESC, n.created_at DESC";
    
    // Add pagination
    $offset = ($page - 1) * $perPage;
    $sql .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Count news for a member
 * 
 * @param int $memberId Member ID
 * @return int Total count
 */
function countNewsForMember($memberId) {
    global $pdo;
    
    // Get member's groups
    $memberGroups = getMemberGroups($memberId);
    $groupIds = array_column($memberGroups, 'id');
    
    $sql = "SELECT COUNT(DISTINCT n.id) as count
            FROM " . table('news') . " n
            WHERE n.status = 'published' AND n.published_at <= NOW()
            AND (n.target_type = 'all'";
    
    $params = [];
    
    if (!empty($groupIds)) {
        $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
        $sql .= " OR (n.target_type = 'groups' AND n.id IN (
                    SELECT news_id FROM " . table('news_groups') . " 
                    WHERE group_id IN ($placeholders)
                  ))";
        $params = array_merge($params, $groupIds);
    }
    
    $sql .= ")";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return (int)$result['count'];
}

/**
 * Delete news
 * 
 * @param int $newsId News ID
 * @return bool Success
 */
function deleteNews($newsId) {
    global $pdo;
    
    $stmt = $pdo->prepare("DELETE FROM " . table('news') . " WHERE id = ?");
    return $stmt->execute([$newsId]);
}

/**
 * Increment news views counter
 * 
 * @param int $newsId News ID
 * @return bool Success
 */
function incrementNewsViews($newsId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('news') . " 
        SET views_count = views_count + 1 
        WHERE id = ?
    ");
    return $stmt->execute([$newsId]);
}

/**
 * Get target groups for a news
 * 
 * @param int $newsId News ID
 * @return array List of groups
 */
function getNewsTargetGroups($newsId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT g.*
        FROM " . table('member_groups') . " g
        INNER JOIN " . table('news_groups') . " ng ON g.id = ng.group_id
        WHERE ng.news_id = ?
        ORDER BY g.name
    ");
    $stmt->execute([$newsId]);
    return $stmt->fetchAll();
}

/**
 * Check if a member can view a specific news
 * 
 * @param int $newsId News ID
 * @param int $memberId Member ID
 * @return bool Can view
 */
function canMemberViewNews($newsId, $memberId) {
    global $pdo;
    
    $news = getNewsById($newsId);
    if (!$news || $news['status'] !== 'published') {
        return false;
    }
    
    // If published_at is in the future, deny access
    if ($news['published_at'] && strtotime($news['published_at']) > time()) {
        return false;
    }
    
    // If target is 'all', allow
    if ($news['target_type'] === 'all') {
        return true;
    }
    
    // Check if member is in any of the target groups
    $memberGroups = getMemberGroups($memberId);
    $memberGroupIds = array_column($memberGroups, 'id');
    
    if (empty($memberGroupIds)) {
        return false;
    }
    
    $placeholders = implode(',', array_fill(0, count($memberGroupIds), '?'));
    $sql = "SELECT COUNT(*) as count FROM " . table('news_groups') . " 
            WHERE news_id = ? AND group_id IN ($placeholders)";
    
    $params = array_merge([$newsId], $memberGroupIds);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

/**
 * Send email notification for a news publication
 * 
 * @param int $newsId News ID
 * @return int Number of emails sent
 */
function sendNewsNotification($newsId) {
    global $pdo;
    require_once __DIR__ . '/email.php';
    
    // Debug log
    error_log("sendNewsNotification called for news ID: $newsId");
    
    $news = getNewsById($newsId);
    if (!$news || $news['status'] !== 'published') {
        error_log("sendNewsNotification: News not found or not published (ID: $newsId)");
        return 0;
    }
    
    // Get recipients based on target type
    $recipients = [];
    
    if ($news['target_type'] === 'all') {
        // All active members with email
        $stmt = $pdo->query("
            SELECT id, first_name, last_name, email
            FROM " . table('members') . "
            WHERE status = 'attivo' AND email IS NOT NULL AND email != ''
            ORDER BY last_name, first_name
        ");
        $recipients = $stmt->fetchAll();
        error_log("sendNewsNotification: Found " . count($recipients) . " recipients (target: all)");
    } elseif ($news['target_type'] === 'groups') {
        // Members in target groups
        $stmt = $pdo->prepare("
            SELECT DISTINCT m.id, m.first_name, m.last_name, m.email
            FROM " . table('members') . " m
            INNER JOIN " . table('member_group_members') . " mgm ON m.id = mgm.member_id
            INNER JOIN " . table('news_groups') . " ng ON mgm.group_id = ng.group_id
            WHERE ng.news_id = ? 
              AND m.status = 'attivo' 
              AND m.email IS NOT NULL 
              AND m.email != ''
            ORDER BY m.last_name, m.first_name
        ");
        $stmt->execute([$newsId]);
        $recipients = $stmt->fetchAll();
        error_log("sendNewsNotification: Found " . count($recipients) . " recipients (target: groups)");
    }
    
    if (empty($recipients)) {
        error_log("sendNewsNotification: No recipients found for news ID: $newsId");
        return 0;
    }
    
    // Prepare email content
    $assocInfo = getAssociationInfo();
    $baseUrl = getBaseUrl();
    $newsUrl = $baseUrl . 'portal/news_view.php?id=' . $newsId;
    
    // Create excerpt if not set
    $excerpt = $news['excerpt'];
    if (empty($excerpt)) {
        $excerpt = strip_tags($news['content']);
        $excerpt = mb_substr($excerpt, 0, 200) . '...';
    }
    
    $subject = 'Nuova Notizia: ' . $news['title'];
    
    $sent = 0;
    foreach ($recipients as $recipient) {
        if (empty($recipient['email'])) continue;
        
        $bodyHtml = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">';
        $bodyHtml .= '<h2 style="color: #667eea;">Nuova Notizia</h2>';
        $bodyHtml .= '<h3>' . h($news['title']) . '</h3>';
        
        if (!empty($news['cover_image'])) {
            $bodyHtml .= '<img src="' . h($news['cover_image']) . '" alt="Copertina" style="max-width: 100%; height: auto; border-radius: 8px; margin: 20px 0;">';
        }
        
        $bodyHtml .= '<p>' . h($excerpt) . '</p>';
        $bodyHtml .= '<p><a href="' . h($newsUrl) . '" style="display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0;">Leggi l\'articolo completo</a></p>';
        $bodyHtml .= '<hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">';
        $bodyHtml .= '<p style="font-size: 12px; color: #666;">Pubblicato il ' . date('d/m/Y H:i', strtotime($news['published_at'])) . '</p>';
        $bodyHtml .= getEmailFooter();
        $bodyHtml .= '</div>';
        
        $bodyText = "Nuova Notizia: {$news['title']}\n\n";
        $bodyText .= "{$excerpt}\n\n";
        $bodyText .= "Leggi l'articolo completo: {$newsUrl}\n\n";
        $bodyText .= "Pubblicato il " . date('d/m/Y H:i', strtotime($news['published_at']));
        
        if (sendOrQueueEmail($recipient['email'], $subject, $bodyHtml, $bodyText)) {
            $sent++;
        } else {
            error_log("sendNewsNotification: Failed to send/queue email to: " . $recipient['email']);
        }
    }
    
    error_log("sendNewsNotification: Successfully sent/queued $sent emails out of " . count($recipients) . " recipients");
    return $sent;
}

/**
 * Send email notification for new event
 * 
 * @param int $eventId Event ID
 * @return int Number of emails sent
 */
function sendEventNotification($eventId) {
    global $pdo;
    require_once __DIR__ . '/email.php';
    
    // Get event details
    $stmt = $pdo->prepare("SELECT * FROM " . table('events') . " WHERE id = ?");
    $stmt->execute([$eventId]);
    $event = $stmt->fetch();
    
    if (!$event) {
        return 0;
    }
    
    // Get recipients based on target groups
    $recipients = [];
    
    // Check if event has target groups
    $stmt = $pdo->prepare("SELECT group_id FROM " . table('event_target_groups') . " WHERE event_id = ?");
    $stmt->execute([$eventId]);
    $eventGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($eventGroups)) {
        // No specific groups = all active members
        $stmt = $pdo->query("
            SELECT id, email, first_name, last_name 
            FROM " . table('members') . " 
            WHERE email IS NOT NULL AND email != '' AND status = 'attivo'
        ");
        $recipients = $stmt->fetchAll();
    } else {
        // Only members in target groups
        $placeholders = implode(',', array_fill(0, count($eventGroups), '?'));
        $stmt = $pdo->prepare("
            SELECT DISTINCT m.id, m.email, m.first_name, m.last_name
            FROM " . table('members') . " m
            JOIN " . table('member_group_members') . " mgm ON m.id = mgm.member_id
            WHERE mgm.group_id IN ($placeholders)
            AND m.email IS NOT NULL AND m.email != '' AND m.status = 'attivo'
        ");
        $stmt->execute($eventGroups);
        $recipients = $stmt->fetchAll();
    }
    
    if (empty($recipients)) {
        return 0;
    }
    
    // Build email
    $assocInfo = getAssociationInfo();
    $assocName = h($assocInfo['name'] ?? 'Associazione');
    $baseUrl = getBaseUrl();
    $eventUrl = $baseUrl . 'portal/events.php';
    
    // Sanitize subject line (remove newlines to prevent header injection)
    $subjectTitle = str_replace(["\r", "\n"], ' ', $event['title']);
    $subject = "[{$assocName}] Nuovo evento: {$subjectTitle}";
    
    // Format date
    $eventDate = date('d/m/Y', strtotime($event['event_date']));
    $eventTime = $event['event_time'] ? date('H:i', strtotime($event['event_time'])) : '';
    
    $bodyHtml = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center;'>
                <h2 style='margin: 0;'>{$assocName}</h2>
            </div>
            
            <div style='padding: 30px; background: #f8f9fa;'>
                <h1 style='color: #333; margin-top: 0;'>📅 Nuovo Evento</h1>
                
                <div style='background: white; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h2 style='color: #667eea; margin-top: 0;'>" . h($event['title']) . "</h2>
                    
                    <p style='color: #333;'>
                        <strong>📆 Data:</strong> {$eventDate}" . ($eventTime ? " alle {$eventTime}" : "") . "
                    </p>
                    
                    " . (!empty($event['location']) ? "<p style='color: #333;'><strong>📍 Luogo:</strong> " . h($event['location']) . "</p>" : "") . "
                    
                    " . (!empty($event['description']) ? "<p style='color: #666; line-height: 1.6;'>" . nl2br(h(substr($event['description'], 0, 300))) . (strlen($event['description']) > 300 ? '...' : '') . "</p>" : "") . "
                </div>
                
                <div style='text-align: center; margin-top: 30px;'>
                    <a href='{$eventUrl}' style='background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; display: inline-block; font-weight: bold;'>
                        Visualizza Evento e Conferma Partecipazione
                    </a>
                </div>
            </div>
            
            <div style='padding: 20px; text-align: center; color: #999; font-size: 12px;'>
                <p>Ricevi questa email perché sei iscritto a {$assocName}</p>
                <p><a href='{$baseUrl}portal/' style='color: #667eea;'>Accedi al Portale Soci</a></p>
            </div>
        </div>
    ";
    
    // Send or queue emails for each recipient based on settings
    $sentCount = 0;
    foreach ($recipients as $member) {
        if (sendOrQueueEmail($member['email'], $subject, $bodyHtml)) {
            $sentCount++;
        }
    }
    
    return $sentCount;
}

/**
 * =============================================================================
 * RECEIPT FUNCTIONS
 * =============================================================================
 */

/**
 * Get next receipt number for current year
 * Format: RIC-YYYY-NNNNN (es. RIC-2026-00001)
 * Uses SELECT FOR UPDATE to prevent race conditions
 * Checks both new receipts table and legacy member_fees table to avoid conflicts
 * 
 * @return string Next receipt number
 */
function getNextReceiptNumber() {
    global $pdo;
    
    $year = date('Y');
    $prefix = 'RIC-' . $year . '-';
    
    // Use transaction with FOR UPDATE to prevent race conditions
    $pdo->beginTransaction();
    
    try {
        // Check new receipts table
        $stmt = $pdo->prepare("
            SELECT receipt_number 
            FROM " . table('receipts') . " 
            WHERE receipt_number LIKE ? 
            ORDER BY receipt_number DESC 
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$prefix . '%']);
        $lastNew = $stmt->fetchColumn();
        
        // Check legacy member_fees table (also lock to prevent race conditions)
        $stmt = $pdo->prepare("
            SELECT receipt_number 
            FROM " . table('member_fees') . " 
            WHERE receipt_number LIKE ? 
            ORDER BY receipt_number DESC 
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute([$prefix . '%']);
        $lastLegacy = $stmt->fetchColumn();
        
        // Get the highest number from both tables
        $lastNumberNew = 0;
        $lastNumberLegacy = 0;
        
        if ($lastNew) {
            $lastNumberNew = (int) substr($lastNew, strlen($prefix));
        }
        
        if ($lastLegacy) {
            $lastNumberLegacy = (int) substr($lastLegacy, strlen($prefix));
        }
        
        $nextNumber = max($lastNumberNew, $lastNumberLegacy) + 1;
        
        $receiptNumber = $prefix . str_pad($nextNumber, 5, '0', STR_PAD_LEFT);
        
        $pdo->commit();
        return $receiptNumber;
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error generating receipt number: " . $e->getMessage());
        return $prefix . '00001'; // Fallback to first number
    }
}

/**
 * Generate receipt for a paid fee
 * 
 * @param int $memberFeeId Member fee ID
 * @param string $paymentMethod Payment method (cash, bank_transfer, card, paypal, other)
 * @param string|null $paymentDetails Payment method details (optional)
 * @param int|null $createdBy User ID who created the receipt (optional)
 * @return int|false Receipt ID on success, false on failure
 */
function generateReceipt($memberFeeId, $paymentMethod = 'cash', $paymentDetails = null, $createdBy = null) {
    global $pdo;
    
    // Check if receipt already exists first (optimization)
    $stmt = $pdo->prepare("SELECT id FROM " . table('receipts') . " WHERE member_fee_id = ?");
    $stmt->execute([$memberFeeId]);
    if ($stmt->fetch()) {
        return false; // Receipt already exists
    }
    
    // Get fee details
    $stmt = $pdo->prepare("
        SELECT mf.*, m.first_name, m.last_name, m.fiscal_code, m.address, m.city,
               sy.name as year_name
        FROM " . table('member_fees') . " mf
        JOIN " . table('members') . " m ON mf.member_id = m.id
        LEFT JOIN " . table('social_years') . " sy ON mf.social_year_id = sy.id
        WHERE mf.id = ?
    ");
    $stmt->execute([$memberFeeId]);
    $fee = $stmt->fetch();
    
    if (!$fee) {
        return false;
    }
    
    // Default payment details
    if ($paymentDetails === null) {
        switch ($paymentMethod) {
            case 'cash':
                $paymentDetails = 'In contanti presso la sede sociale';
                break;
            case 'bank_transfer':
                $paymentDetails = 'Bonifico bancario';
                break;
            case 'card':
                $paymentDetails = 'Pagamento con carta';
                break;
            case 'paypal':
                $paymentDetails = 'Pagamento PayPal';
                break;
            default:
                $paymentDetails = 'Altro metodo di pagamento';
        }
    }
    
    // Build description
    $description = 'Quota associativa';
    if (!empty($fee['year_name'])) {
        $description .= ' - Anno sociale ' . $fee['year_name'];
    }
    
    // Generate receipt number (handles its own transaction)
    $receiptNumber = getNextReceiptNumber();
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('receipts') . " 
            (receipt_number, member_id, member_fee_id, amount, description, payment_method, payment_method_details, issue_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), ?)
        ");
        
        $stmt->execute([
            $receiptNumber,
            $fee['member_id'],
            $memberFeeId,
            $fee['amount'],
            $description,
            $paymentMethod,
            $paymentDetails,
            $createdBy
        ]);
        
        return $pdo->lastInsertId();
    } catch (Exception $e) {
        error_log("Error generating receipt: " . $e->getMessage());
        return false;
    }
}

/**
 * Get receipts for a member
 * 
 * @param int $memberId Member ID
 * @return array Array of receipts
 */
function getMemberReceipts($memberId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT r.*, m.first_name, m.last_name
        FROM " . table('receipts') . " r
        JOIN " . table('members') . " m ON r.member_id = m.id
        WHERE r.member_id = ?
        ORDER BY r.issue_date DESC, r.id DESC
    ");
    $stmt->execute([$memberId]);
    return $stmt->fetchAll();
}

/**
 * Get single receipt by ID
 * 
 * @param int $receiptId Receipt ID
 * @return array|false Receipt data or false if not found
 */
function getReceipt($receiptId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT r.*, 
               m.first_name, m.last_name, m.fiscal_code, m.address, m.city, m.postal_code, m.email
        FROM " . table('receipts') . " r
        JOIN " . table('members') . " m ON r.member_id = m.id
        WHERE r.id = ?
    ");
    $stmt->execute([$receiptId]);
    return $stmt->fetch();
}

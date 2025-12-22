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
        }
        
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($hasHeader && !empty($headers)) {
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

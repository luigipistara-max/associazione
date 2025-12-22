<?php
/**
 * Utility Functions
 */

/**
 * Escape HTML output
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
 * Validate Italian Tax Code (Codice Fiscale)
 */
function validateTaxCode($taxCode) {
    $taxCode = strtoupper(trim($taxCode));
    
    // Basic format check: 16 characters, alphanumeric
    if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $taxCode)) {
        return false;
    }
    
    // Control character validation
    $odd = ['0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9, '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
            'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9, 'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
            'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11, 'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
            'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23];
    
    $even = ['0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4, '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
             'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4, 'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
             'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14, 'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
             'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25];
    
    $sum = 0;
    for ($i = 0; $i < 15; $i++) {
        $c = $taxCode[$i];
        if ($i % 2 == 0) {
            $sum += $odd[$c];
        } else {
            $sum += $even[$c];
        }
    }
    
    $control = chr(65 + ($sum % 26));
    return $taxCode[15] === $control;
}

/**
 * Get current social year
 */
function getCurrentSocialYear() {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM social_years WHERE is_current = 1 LIMIT 1");
    return $stmt->fetch();
}

/**
 * Get all active social years
 */
function getSocialYears() {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT * FROM social_years ORDER BY start_date DESC");
    return $stmt->fetchAll();
}

/**
 * Get income categories
 */
function getIncomeCategories($activeOnly = true) {
    $pdo = getDbConnection();
    $sql = "SELECT * FROM income_categories";
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
    $pdo = getDbConnection();
    $sql = "SELECT * FROM expense_categories";
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
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

/**
 * Get and clear flash message
 */
function getFlashMessage() {
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
 * Redirect to a page
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

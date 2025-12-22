<?php
/**
 * Utility functions
 */

/**
 * Sanitize output for HTML
 */
function h(?string $string): string {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function formatDate(?string $date, string $format = 'd/m/Y'): string {
    if (!$date) return '';
    try {
        return (new DateTime($date))->format($format);
    } catch (Exception $e) {
        return '';
    }
}

/**
 * Format currency
 */
function formatCurrency(?float $amount): string {
    return number_format($amount ?? 0, 2, ',', '.') . ' €';
}

/**
 * Validate Italian fiscal code
 */
function validateFiscalCode(string $cf): bool {
    $cf = strtoupper(trim($cf));
    
    // Check length
    if (strlen($cf) !== 16) {
        return false;
    }
    
    // Check format
    if (!preg_match('/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/', $cf)) {
        return false;
    }
    
    // Check control character
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
        $char = $cf[$i];
        if ($i % 2 === 0) {
            $sum += $odd[$char];
        } else {
            $sum += $even[$char];
        }
    }
    
    $control = chr(65 + ($sum % 26));
    return $control === $cf[15];
}

/**
 * Get base URL
 */
function getBaseUrl(): string {
    global $config;
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $basePath = rtrim($config['app']['base_path'], '/');
    return $protocol . '://' . $host . $basePath;
}

/**
 * Redirect to URL
 */
function redirect(string $path) {
    $basePath = rtrim($GLOBALS['config']['app']['base_path'], '/');
    header('Location: ' . $basePath . '/' . ltrim($path, '/'));
    exit;
}

/**
 * Parse CSV file
 */
function parseCsvFile(string $filePath, string $delimiter = ';'): array {
    $rows = [];
    if (($handle = fopen($filePath, 'r')) !== false) {
        while (($data = fgetcsv($handle, 1000, $delimiter)) !== false) {
            $rows[] = $data;
        }
        fclose($handle);
    }
    return $rows;
}

/**
 * Export array to CSV
 */
function exportCsv(array $data, string $filename, array $headers = []) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if (!empty($headers)) {
        fputcsv($output, $headers, ';');
    }
    
    foreach ($data as $row) {
        fputcsv($output, $row, ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Flash message functions
 */
function setFlash(string $type, string $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): ?array {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function displayFlash() {
    $flash = getFlash();
    if ($flash) {
        $alertClass = $flash['type'] === 'success' ? 'alert-success' : 'alert-danger';
        echo '<div class="alert ' . $alertClass . ' alert-dismissible fade show" role="alert">';
        echo h($flash['message']);
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        echo '</div>';
    }
}

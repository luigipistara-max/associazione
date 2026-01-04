<?php
/**
 * Two-Factor Authentication (2FA) Functions
 * TOTP (Time-based One-Time Password) implementation
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator
 */

/**
 * Generate a random secret key for TOTP
 * 
 * @return string Base32-encoded secret (16 characters)
 */
function generateTotpSecret() {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // Base32 alphabet
    $secret = '';
    for ($i = 0; $i < 16; $i++) {
        $secret .= $chars[random_int(0, 31)];
    }
    return $secret;
}

/**
 * Generate TOTP code for a given secret and time
 * 
 * @param string $secret Base32-encoded secret
 * @param int|null $time Unix timestamp (null = current time)
 * @return string 6-digit OTP code
 */
function getTotpCode($secret, $time = null) {
    if ($time === null) {
        $time = time();
    }
    
    // Convert base32 secret to binary
    $secret = base32Decode($secret);
    
    // Calculate time step (30 seconds)
    $timeSlice = floor($time / 30);
    
    // Pack time as 64-bit big-endian
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    
    // Generate HMAC-SHA1
    $hash = hash_hmac('sha1', $time, $secret, true);
    
    // Dynamic truncation
    $offset = ord($hash[19]) & 0x0f;
    $code = (
        ((ord($hash[$offset + 0]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % 1000000;
    
    return str_pad($code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code
 * 
 * @param string $secret Base32-encoded secret
 * @param string $code User-provided code
 * @param int $window Number of time windows to check (default: 1 = ±30 seconds)
 * @return bool Valid code
 */
function verifyTotpCode($secret, $code, $window = 1) {
    $time = time();
    
    // Check current time and adjacent windows
    for ($i = -$window; $i <= $window; $i++) {
        $testTime = $time + ($i * 30);
        if (getTotpCode($secret, $testTime) === $code) {
            return true;
        }
    }
    
    return false;
}

/**
 * Generate QR code URL for Google Authenticator
 * 
 * @param string $secret Base32-encoded secret
 * @param string $label Account label (e.g., username or email)
 * @param string|null $issuer Issuer name (e.g., "MyAssociation")
 * @return string QR code image URL
 */
function getTotpQrCodeUrl($secret, $label, $issuer = null) {
    // Build otpauth URL
    $otpauthUrl = 'otpauth://totp/' . rawurlencode($label) . '?secret=' . $secret;
    
    if ($issuer) {
        $otpauthUrl .= '&issuer=' . rawurlencode($issuer);
    }
    
    // Generate QR code using Google Charts API
    $qrCodeUrl = 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . urlencode($otpauthUrl);
    
    return $qrCodeUrl;
}

/**
 * Decode base32 string
 * 
 * @param string $secret Base32-encoded string
 * @return string Binary string
 */
function base32Decode($secret) {
    $secret = strtoupper($secret);
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = '';
    
    for ($i = 0; $i < strlen($secret); $i++) {
        $char = $secret[$i];
        $pos = strpos($alphabet, $char);
        if ($pos === false) continue;
        $binary .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    
    $result = '';
    for ($i = 0; $i < strlen($binary); $i += 8) {
        $byte = substr($binary, $i, 8);
        if (strlen($byte) == 8) {
            $result .= chr(bindec($byte));
        }
    }
    
    return $result;
}

/**
 * Check if 2FA is enabled globally
 * 
 * @return bool 2FA is enabled
 */
function is2faEnabled() {
    require_once __DIR__ . '/functions.php';
    return getSetting('2fa_enabled', '0') === '1';
}

/**
 * Check if 2FA is required for a user role
 * 
 * @param string $role User role (admin, operatore, etc.)
 * @return bool 2FA is required
 */
function is2faRequiredForRole($role) {
    if (!is2faEnabled()) {
        return false;
    }
    
    require_once __DIR__ . '/functions.php';
    $requiredFor = getSetting('2fa_required_for', 'none');
    
    switch ($requiredFor) {
        case 'all':
            return true;
        case 'admin':
            return $role === 'admin';
        case 'staff':
            return in_array($role, ['admin', 'operatore']);
        default:
            return false;
    }
}

/**
 * Check if a user has 2FA configured
 * 
 * @param int $userId User ID
 * @return bool Has 2FA configured
 */
function userHas2fa($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT two_factor_secret FROM " . table('users') . " WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return !empty($user['two_factor_secret']);
}

/**
 * Enable 2FA for a user
 * 
 * @param int $userId User ID
 * @param string $secret Base32-encoded secret
 * @return bool Success
 */
function enable2faForUser($userId, $secret) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('users') . " 
        SET two_factor_secret = ? 
        WHERE id = ?
    ");
    return $stmt->execute([$secret, $userId]);
}

/**
 * Disable 2FA for a user
 * 
 * @param int $userId User ID
 * @return bool Success
 */
function disable2faForUser($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        UPDATE " . table('users') . " 
        SET two_factor_secret = NULL 
        WHERE id = ?
    ");
    return $stmt->execute([$userId]);
}

/**
 * Get user's 2FA secret
 * 
 * @param int $userId User ID
 * @return string|null Secret or null if not configured
 */
function getUser2faSecret($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT two_factor_secret FROM " . table('users') . " WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    return $user['two_factor_secret'] ?? null;
}

/**
 * Verify 2FA code for a user
 * 
 * @param int $userId User ID
 * @param string $code User-provided code
 * @return bool Valid code
 */
function verifyUser2faCode($userId, $code) {
    $secret = getUser2faSecret($userId);
    if (!$secret) {
        return false;
    }
    
    return verifyTotpCode($secret, $code);
}

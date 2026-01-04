<?php
/**
 * Portal Authentication Functions
 */

/**
 * Check if member is logged in to portal
 */
function isPortalLoggedIn() {
    return isset($_SESSION['portal_member_id']) && $_SESSION['portal_member_id'] > 0;
}

/**
 * Get current logged in member
 */
function getPortalMember() {
    if (!isPortalLoggedIn()) {
        return null;
    }
    
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE id = ? AND status = 'attivo'");
    $stmt->execute([$_SESSION['portal_member_id']]);
    return $stmt->fetch();
}

/**
 * Require portal login - redirect if not authenticated
 */
function requirePortalLogin() {
    if (!isPortalLoggedIn()) {
        $basePath = $GLOBALS['config']['app']['base_path'] ?? '/';
        header('Location: ' . $basePath . 'portal/login.php');
        exit;
    }
    
    // Verify member still exists and is active
    $member = getPortalMember();
    if (!$member) {
        portalLogout();
        $basePath = $GLOBALS['config']['app']['base_path'] ?? '/';
        header('Location: ' . $basePath . 'portal/login.php?error=inactive');
        exit;
    }
    
    return $member;
}

/**
 * Login member to portal
 */
function portalLogin($email, $password) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE email = ? AND status = 'attivo'");
    $stmt->execute([$email]);
    $member = $stmt->fetch();
    
    if (!$member) {
        return ['success' => false, 'error' => 'Email non trovata o account non attivo'];
    }
    
    if (empty($member['portal_password'])) {
        return ['success' => false, 'error' => 'Account non ancora attivato. Usa il link di attivazione ricevuto via email.', 'needs_activation' => true];
    }
    
    if (!password_verify($password, $member['portal_password'])) {
        return ['success' => false, 'error' => 'Password non corretta'];
    }
    
    // Set session (both old portal_member_id and new member_id for gradual migration to src/auth.php)
    $_SESSION['portal_member_id'] = $member['id'];
    $_SESSION['member_id'] = $member['id'];
    $_SESSION['portal_member_name'] = $member['first_name'] . ' ' . $member['last_name'];
    $_SESSION['portal_login_time'] = time();
    
    // Update last login
    $stmt = $pdo->prepare("UPDATE " . table('members') . " SET last_portal_login = NOW() WHERE id = ?");
    $stmt->execute([$member['id']]);
    
    return ['success' => true, 'member' => $member];
}

/**
 * Logout from portal
 */
function portalLogout() {
    unset($_SESSION['portal_member_id']);
    unset($_SESSION['member_id']); // Also unset member_id for src/auth.php compatibility
    unset($_SESSION['portal_member_name']);
    unset($_SESSION['portal_login_time']);
}

/**
 * Set portal password for member
 */
function setPortalPassword($memberId, $password) {
    global $pdo;
    
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE " . table('members') . " SET portal_password = ?, portal_token = NULL, portal_token_expires = NULL WHERE id = ?");
    return $stmt->execute([$hashedPassword, $memberId]);
}

/**
 * Generate activation/reset token
 */
function generatePortalToken($memberId) {
    global $pdo;
    
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    $stmt = $pdo->prepare("UPDATE " . table('members') . " SET portal_token = ?, portal_token_expires = ? WHERE id = ?");
    $stmt->execute([$token, $expires, $memberId]);
    
    return $token;
}

/**
 * Verify portal token
 */
function verifyPortalToken($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("SELECT * FROM " . table('members') . " WHERE portal_token = ? AND portal_token_expires > NOW() AND status = 'attivo'");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

/**
 * Validate password strength
 */
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = 'La password deve essere di almeno 8 caratteri';
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'La password deve contenere almeno una lettera maiuscola';
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'La password deve contenere almeno una lettera minuscola';
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'La password deve contenere almeno un numero';
    }
    
    return $errors;
}

<?php
/**
 * Audit Log Functions
 * Tracciamento di tutte le operazioni degli utenti
 */

require_once __DIR__ . '/db.php';

/**
 * Registra un'azione nel log di audit
 * 
 * @param string $action Azione eseguita (login, logout, create, update, delete, export)
 * @param string|null $entityType Tipo di entità (member, fee, income, expense, user, year, category)
 * @param int|null $entityId ID dell'entità
 * @param string|null $entityName Nome descrittivo dell'entità
 * @param array|null $oldValues Valori precedenti (per update/delete)
 * @param array|null $newValues Nuovi valori (per create/update)
 */
function logAudit($action, $entityType = null, $entityId = null, $entityName = null, $oldValues = null, $newValues = null) {
    global $pdo;
    
    // Ottieni informazioni utente corrente
    $userId = $_SESSION['user_id'] ?? null;
    $username = $_SESSION['username'] ?? 'system';
    
    // Ottieni IP e user agent
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Limita lunghezza user agent
    if ($userAgent && strlen($userAgent) > 500) {
        $userAgent = substr($userAgent, 0, 500);
    }
    
    // Converti array in JSON
    $oldValuesJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
    $newValuesJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO " . table('audit_log') . "
            (user_id, username, action, entity_type, entity_id, entity_name, 
             old_values, new_values, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $username,
            $action,
            $entityType,
            $entityId,
            $entityName,
            $oldValuesJson,
            $newValuesJson,
            $ipAddress,
            $userAgent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Errore log audit: " . $e->getMessage());
        return false;
    }
}

/**
 * Log login utente
 */
function logLogin($userId, $username) {
    return logAudit('login', 'user', $userId, $username);
}

/**
 * Log logout utente
 */
function logLogout($userId, $username) {
    return logAudit('logout', 'user', $userId, $username);
}

/**
 * Log creazione entità
 */
function logCreate($entityType, $entityId, $entityName, $newValues) {
    return logAudit('create', $entityType, $entityId, $entityName, null, $newValues);
}

/**
 * Log modifica entità
 */
function logUpdate($entityType, $entityId, $entityName, $oldValues, $newValues) {
    return logAudit('update', $entityType, $entityId, $entityName, $oldValues, $newValues);
}

/**
 * Log eliminazione entità
 */
function logDelete($entityType, $entityId, $entityName, $oldValues) {
    return logAudit('delete', $entityType, $entityId, $entityName, $oldValues, null);
}

/**
 * Log export dati
 */
function logExport($entityType, $description) {
    return logAudit('export', $entityType, null, $description);
}

/**
 * Log invio email
 */
function logEmail($toEmail, $subject) {
    return logAudit('email', null, null, "A: {$toEmail}, Oggetto: {$subject}");
}

/**
 * Recupera log audit con filtri
 * 
 * @param array $filters Filtri (user_id, action, entity_type, date_from, date_to)
 * @param int $limit Numero massimo di risultati
 * @param int $offset Offset per paginazione
 * @return array
 */
function getAuditLog($filters = [], $limit = 100, $offset = 0) {
    global $pdo;
    
    $sql = "SELECT * FROM " . table('audit_log') . " WHERE 1=1";
    $params = [];
    
    if (isset($filters['user_id'])) {
        $sql .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (isset($filters['action'])) {
        $sql .= " AND action = ?";
        $params[] = $filters['action'];
    }
    
    if (isset($filters['entity_type'])) {
        $sql .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
    }
    
    if (isset($filters['date_from'])) {
        $sql .= " AND created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (isset($filters['date_to'])) {
        $sql .= " AND created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    return $stmt->fetchAll();
}

/**
 * Conta totale log audit con filtri
 */
function countAuditLog($filters = []) {
    global $pdo;
    
    $sql = "SELECT COUNT(*) as count FROM " . table('audit_log') . " WHERE 1=1";
    $params = [];
    
    if (isset($filters['user_id'])) {
        $sql .= " AND user_id = ?";
        $params[] = $filters['user_id'];
    }
    
    if (isset($filters['action'])) {
        $sql .= " AND action = ?";
        $params[] = $filters['action'];
    }
    
    if (isset($filters['entity_type'])) {
        $sql .= " AND entity_type = ?";
        $params[] = $filters['entity_type'];
    }
    
    if (isset($filters['date_from'])) {
        $sql .= " AND created_at >= ?";
        $params[] = $filters['date_from'] . ' 00:00:00';
    }
    
    if (isset($filters['date_to'])) {
        $sql .= " AND created_at <= ?";
        $params[] = $filters['date_to'] . ' 23:59:59';
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    
    return $result['count'];
}

/**
 * Recupera storico di un'entità specifica
 */
function getEntityHistory($entityType, $entityId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM " . table('audit_log') . "
        WHERE entity_type = ? AND entity_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$entityType, $entityId]);
    
    return $stmt->fetchAll();
}

/**
 * Formatta differenze tra valori vecchi e nuovi
 */
function formatValueDiff($oldValues, $newValues) {
    $diff = [];
    
    if (!$oldValues) $oldValues = [];
    if (!$newValues) $newValues = [];
    
    // Trova tutte le chiavi
    $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
    
    foreach ($allKeys as $key) {
        $oldVal = $oldValues[$key] ?? null;
        $newVal = $newValues[$key] ?? null;
        
        if ($oldVal !== $newVal) {
            $diff[$key] = [
                'old' => $oldVal,
                'new' => $newVal
            ];
        }
    }
    
    return $diff;
}

/**
 * Traduzione nomi azioni in italiano
 */
function translateAction($action) {
    $translations = [
        'login' => 'Login',
        'logout' => 'Logout',
        'create' => 'Creazione',
        'update' => 'Modifica',
        'delete' => 'Eliminazione',
        'export' => 'Esportazione',
        'email' => 'Invio Email'
    ];
    
    return $translations[$action] ?? ucfirst($action);
}

/**
 * Traduzione nomi entità in italiano
 */
function translateEntityType($entityType) {
    $translations = [
        'member' => 'Socio',
        'fee' => 'Quota',
        'income' => 'Entrata',
        'expense' => 'Uscita',
        'user' => 'Utente',
        'year' => 'Anno Sociale',
        'category' => 'Categoria'
    ];
    
    return $translations[$entityType] ?? ucfirst($entityType);
}

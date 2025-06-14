<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si l'utilisateur est authentifié
 * Redirige vers la page de login si non authentifié
 */
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header('Location: login.php');
        exit();
    }
    
    // Vérification de l'expiration de la session
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > 1800)) {
        session_unset();
        session_destroy();
        header('Location: login.php?timeout=1');
        exit();
    }
    $_SESSION['LAST_ACTIVITY'] = time();
}

// Sécurisation des sessions
function secureSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        session_regenerate_id(true);
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

/**
 * Vérifie si l'utilisateur a un des rôles autorisés
 * @param array $allowedRoles IDs des rôles autorisés
 */
function checkRole($allowedRoles) {
    checkAuth();
    
    if (!isset($_SESSION['role_id']) || !in_array($_SESSION['role_id'], $allowedRoles)) {
        header('Location: unauthorized.php');
        exit();
    }
}

/**
 * Vérifie les droits d'accès admin (admin ou super_admin)
 */
function checkAdminAccess() {
    checkAuth();
    
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] > 1) {
        header('Location: unauthorized.php');
        exit();
    }
}

/**
 * Vérifie si l'utilisateur est super admin
 */
function isSuperAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin';
}

/**
 * Vérifie si l'utilisateur est admin (inclut super admin)
 */
function isAdmin() {
    return isset($_SESSION['role_id']) && $_SESSION['role_id'] <= 2;
}

/**
 * Vérifie si l'utilisateur a un rôle spécifique
 * @param string|int $requiredRole Nom ou ID du rôle requis
 */
function hasRole($requiredRole) {
    checkAuth();
    
    // Si c'est un ID de rôle
    if (is_numeric($requiredRole)) {
        return isset($_SESSION['role_id']) && $_SESSION['role_id'] == $requiredRole;
    }
    
    // Si c'est un nom de rôle
    if (!isset($_SESSION['role_name'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT r.name FROM users u
                              JOIN roles r ON u.role_id = r.id
                              WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['role_name'] = $stmt->fetchColumn();
    }
    
    return strtolower($_SESSION['role_name']) === strtolower($requiredRole);
}

/**
 * Vérifie les permissions avec un système de capacités
 * @param string $capability Capacité requise (ex: 'manage_users')
 */
function currentUserCan($capability) {
    checkAuth();
    
    // Récupérer les permissions du rôle
    if (!isset($_SESSION['role_permissions'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT permissions FROM roles WHERE id = ?");
        $stmt->execute([$_SESSION['role_id']]);
        $_SESSION['role_permissions'] = json_decode($stmt->fetchColumn() ?? '[]', true);
    }
    
    return in_array($capability, $_SESSION['role_permissions']);
}

// Fonction pour obtenir les informations de l'utilisateur courant
function currentUser() {
    checkAuth();
    
    if (!isset($_SESSION['user_data'])) {
        global $pdo;
        $stmt = $pdo->prepare("SELECT u.*, r.name as role_name 
                              FROM users u 
                              JOIN roles r ON u.role_id = r.id 
                              WHERE u.id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $_SESSION['user_data'] = $stmt->fetch();
    }
    
    return $_SESSION['user_data'];
}

// Exemples d'utilisation :
// checkAuth(); // Juste vérifier l'authentification
// checkRole([1, 2]); // Accès réservé aux rôles avec ID 1 ou 2
// checkAdminAccess(); // Vérifie admin ou super_admin
// if (isSuperAdmin()) { ... }
// if (hasRole('professeur')) { ... }
// if (currentUserCan('edit_courses')) { ... }
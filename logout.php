<?php
require_once 'config/db_connection.php';

// Journaliser la déconnexion
if (isset($_SESSION['email'])) {
    logEvent("Déconnexion de l'utilisateur: " . $_SESSION['email']);
}

// Supprimer le cookie "Se souvenir de moi" s'il existe
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
    
    // Nettoyer la base de données
    try {
        $stmt = $pdo->prepare("UPDATE users SET remember_token = NULL, token_expiry = NULL WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
    } catch (PDOException $e) {
        logEvent("Erreur lors de la suppression du token: " . $e->getMessage());
    }
}

session_start();

// Détruire la session
$_SESSION = array();
session_destroy();

// Rediriger vers la page de connexion
header('Location: login.php');
exit();
?>
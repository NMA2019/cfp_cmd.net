<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID utilisateur manquant']);
    exit();
}

$id = (int)$_GET['id'];

try {
    $user = $pdo->query("
        SELECT u.*, r.id AS role_id 
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE u.id = $id
    ")->fetch();
    
    if (!$user) {
        echo json_encode(['error' => 'Utilisateur non trouvé']);
        exit();
    }
    
    echo json_encode($user);
    
} catch (PDOException $e) {
    logEvent("Erreur get_user: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>
<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit();
}

if (!isset($_POST['id'])) {
    echo json_encode(['success' => false, 'message' => 'ID étudiant manquant']);
    exit();
}

$id = (int)$_POST['id'];

try {
    $pdo->beginTransaction();
    
    // 1. Mettre à jour le statut de l'étudiant
    $pdo->exec("UPDATE students SET status = 'inscrit', inscription_date = CURDATE() WHERE id = $id");
    
    // 2. Ajouter une notification pour l'étudiant
    $student = $pdo->query("SELECT user_id FROM students WHERE id = $id")->fetch();
    $userId = $student['user_id'];
    
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link) 
                          VALUES (?, 'Inscription validée', 'Votre inscription a été validée. Bienvenue au CFP-CMD!', 'profile.php')");
    $stmt->execute([$userId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    logEvent("Erreur validate_registration: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>
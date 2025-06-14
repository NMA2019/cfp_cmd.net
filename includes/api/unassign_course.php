<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Vérification des droits
if (!isSuperAdmin()) {
    echo json_encode(['success' => false, 'message' => 'Accès non autorisé']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("DELETE FROM teacher_assignments WHERE id = ?");
    $stmt->execute([$data['assignment_id']]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Cours désassigné avec succès']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    logEvent("Erreur désassignation cours: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>
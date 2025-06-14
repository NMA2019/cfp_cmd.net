<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

// Vérification des droits
if (!isAdmin()) {
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
    
    $stmt = $pdo->prepare("INSERT INTO teacher_assignments 
                          (teacher_id, module_id, formation_id, start_date, end_date, hours_assigned)
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['teacher_id'],
        $data['module_id'],
        $data['formation_id'],
        $data['start_date'],
        $data['end_date'] ?? null,
        $data['hours_assigned']
    ]);
    
    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Cours assigné avec succès']);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    logEvent("Erreur assignation cours: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Erreur de base de données']);
}
?>
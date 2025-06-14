<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'ID de module invalide']));
}

$id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$id]);
    $module = $stmt->fetch();
    
    if (!$module) {
        http_response_code(404);
        die(json_encode(['error' => 'Module non trouvé']));
    }
    
    echo json_encode($module);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de base de données']));
}
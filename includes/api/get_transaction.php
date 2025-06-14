<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'ID invalide']));
}

$id = intval($_GET['id']);

try {
    $stmt = $pdo->prepare("SELECT c.*, p.reference as payment_reference
                         FROM cash c
                         LEFT JOIN payments p ON c.payment_id = p.id
                         WHERE c.id = ?");
    $stmt->execute([$id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        http_response_code(404);
        die(json_encode(['error' => 'Transaction non trouvée']));
    }
    
    echo json_encode($transaction);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de base de données']));
}
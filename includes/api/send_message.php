<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['type']) || !isset($data['id']) || !isset($data['content'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Données invalides']));
}

$userId = $_SESSION['user_id'];

try {
    if ($data['type'] === 'private') {
        // Message privé
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (sender_id, recipient_id, content)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $data['id'], $data['content']]);
    } elseif ($data['type'] === 'group') {
        // Message de groupe
        $stmt = $pdo->prepare("
            INSERT INTO chat_messages (sender_id, group_id, content)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$userId, $data['id'], $data['content']]);
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Type de conversation invalide']));
    }
    
    $messageId = $pdo->lastInsertId();
    
    // Récupérer le message complet pour le retour
    $stmt = $pdo->prepare("
        SELECT m.*, u.first_name AS sender_name, u.photo AS sender_photo
        FROM chat_messages m
        JOIN users u ON m.sender_id = u.id
        WHERE m.id = ?
    ");
    $stmt->execute([$messageId]);
    $message = $stmt->fetch();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de base de données']));
}
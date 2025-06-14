<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['type']) || !isset($data['chat_id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Données invalides']));
}

$userId = $_SESSION['user_id'];

try {
    if ($data['type'] === 'private') {
        // Marquer comme lus les messages privés
        $stmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = TRUE 
            WHERE recipient_id = ? AND sender_id = ? AND is_read = FALSE
        ");
        $stmt->execute([$userId, $data['chat_id']]);
    } elseif ($data['type'] === 'group') {
        // Marquer comme lus les messages de groupe
        $stmt = $pdo->prepare("
            UPDATE chat_messages 
            SET is_read = TRUE 
            WHERE group_id = ? AND sender_id != ? AND is_read = FALSE
        ");
        $stmt->execute([$data['chat_id'], $userId]);
    }
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de base de données']));
}
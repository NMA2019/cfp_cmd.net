<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['type']) || !isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Paramètres invalides']));
}

$userId = $_SESSION['user_id'];
$type = $_GET['type'];
$id = intval($_GET['id']);

try {
    if ($type === 'private') {
        // Conversation privée
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name AS sender_name, u.photo AS sender_photo
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE ((m.sender_id = ? AND m.recipient_id = ?) OR (m.sender_id = ? AND m.recipient_id = ?))
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$userId, $id, $id, $userId]);
    } elseif ($type === 'group') {
        // Conversation de groupe
        $stmt = $pdo->prepare("
            SELECT m.*, u.first_name AS sender_name, u.photo AS sender_photo
            FROM chat_messages m
            JOIN users u ON m.sender_id = u.id
            WHERE m.group_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$id]);
    } else {
        http_response_code(400);
        die(json_encode(['error' => 'Type de conversation invalide']));
    }
    
    $messages = $stmt->fetchAll();
    echo json_encode($messages);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur de base de données']));
}
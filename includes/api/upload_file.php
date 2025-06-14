<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['file'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Requête invalide']));
}

$userId = $_SESSION['user_id'];
$type = $_POST['type'] ?? '';
$chatId = $_POST['chat_id'] ?? 0;

// Vérification des paramètres
if (!in_array($type, ['private', 'group']) || !is_numeric($chatId)) {
    http_response_code(400);
    die(json_encode(['error' => 'Paramètres invalides']));
}

// Configuration du répertoire de stockage
$uploadDir = '../uploads/chat/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Validation du fichier
$file = $_FILES['file'];
$maxSize = 10 * 1024 * 1024; // 10MB
$allowedTypes = [
    'image/jpeg', 'image/png', 'image/gif',
    'application/pdf',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain'
];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Erreur lors du téléchargement']));
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    die(json_encode(['error' => 'Fichier trop volumineux (max 10MB)']));
}

if (!in_array($file['type'], $allowedTypes)) {
    http_response_code(400);
    die(json_encode(['error' => 'Type de fichier non autorisé']));
}

// Générer un nom de fichier unique
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = uniqid('chat_') . '.' . $extension;
$filepath = $uploadDir . $filename;

// Déplacer le fichier
if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Enregistrer en base de données
    try {
        $stmt = $pdo->prepare("
            INSERT INTO chat_attachments (original_name, file_path, file_type, file_size, uploaded_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $file['name'],
            'chat/' . $filename,
            $file['type'],
            $file['size'],
            $userId
        ]);
        
        $attachmentId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'file_name' => $file['name'],
            'file_path' => 'chat/' . $filename,
            'file_type' => $file['type'],
            'file_size' => $file['size'],
            'attachment_id' => $attachmentId
        ]);
    } catch (PDOException $e) {
        unlink($filepath); // Supprimer le fichier en cas d'erreur
        http_response_code(500);
        die(json_encode(['error' => 'Erreur de base de données']));
    }
} else {
    http_response_code(500);
    die(json_encode(['error' => 'Erreur lors du déplacement du fichier']));
}
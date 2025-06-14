<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['q']) || !isset($_GET['current_user'])) {
    http_response_code(400);
    die(json_encode(['error' => 'Paramètres manquants']));
}

$query = '%'.$_GET['q'].'%';
$currentUser = intval($_GET['current_user']);

try {
    $stmt = $pdo->prepare("
        SELECT u.id, u.first_name, u.last_name, u.photo, r.name AS role_name
        FROM users u
        JOIN roles r ON u.role_id = r.id
        WHERE (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)
        AND u.id != ?
        ORDER BY u.last_name, u.first_name
        LIMIT 10
    ");
    $stmt->execute([$query, $query, $query, $currentUser]);
    
    echo json_encode($stmt->fetchAll());
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur de base de données']);
}
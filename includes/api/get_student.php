<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

header('Content-Type: application/json');

if (!isset($_GET['id'])) {
    echo json_encode(['error' => 'ID étudiant manquant']);
    exit();
}

$id = (int)$_GET['id'];

try {
    $student = $pdo->query("
        SELECT s.*, u.first_name, u.last_name, u.email, u.phone, u.address, u.photo,
               f.name AS formation, fi.name AS filiere,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON s.formation_id = f.id
        JOIN filieres fi ON f.filiere_id = fi.id
        WHERE s.id = $id
    ")->fetch();
    
    if (!$student) {
        echo json_encode(['error' => 'Étudiant non trouvé']);
        exit();
    }
    
    $student['full_name'] = $student['first_name'] . ' ' . $student['last_name'];
    echo json_encode($student);
    
} catch (PDOException $e) {
    logEvent("Erreur get_student: " . $e->getMessage());
    echo json_encode(['error' => 'Erreur de base de données']);
}
?>
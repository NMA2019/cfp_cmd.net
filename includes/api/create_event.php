<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

// Vérifier les droits d'accès
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('HTTP/1.1 403 Forbidden');
    exit("Accès interdit");
}

header('Content-Type: application/json');

try {
    // Validation des données
    $required = ['title', 'start', 'type'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Le champ $field est requis");
        }
    }

    $title = htmlspecialchars($_POST['title']);
    $start = date('Y-m-d H:i:s', strtotime($_POST['start']));
    $end = !empty($_POST['end']) ? date('Y-m-d H:i:s', strtotime($_POST['end'])) : null;
    $type = in_array($_POST['type'], ['formation', 'soutenance', 'reunion', 'autre']) ? $_POST['type'] : 'autre';
    $description = !empty($_POST['description']) ? htmlspecialchars($_POST['description']) : null;

    // Insertion dans la base de données
    $stmt = $pdo->prepare("INSERT INTO events (title, start_date, end_date, type, description, created_by) 
                          VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$title, $start, $end, $type, $description, $_SESSION['user_id']]);

    // Journalisation
    logEvent("Événement créé: $title par " . $_SESSION['username']);

    echo json_encode(['success' => true, 'message' => 'Événement créé avec succès']);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db_connection.php';

try {
    // Récupérer les formations actives
    $stmt = $pdo->query("
        SELECT f.id, f.nom, f.description, f.duree_mois, f.pension, 
               fi.nom_filiere, fi.option_filiere,
               CONCAT('formation-', f.id, '.jpg') as image
        FROM formation f
        LEFT JOIN filiere fi ON f.id = fi.id
        WHERE f.status = 'Actif'
        ORDER BY f.created_at DESC
        LIMIT 6
    ");

    $formations = $stmt->fetchAll();

    // Formatage des données pour la réponse JSON
    $response = [
        'success' => true,
        'data' => $formations,
        'count' => count($formations)
    ];

    echo json_encode($response);
} catch (PDOException $e) {
    // En cas d'erreur, retourner un message d'erreur
    $response = [
        'success' => false,
        'message' => 'Erreur lors de la récupération des formations',
        'error' => $e->getMessage()
    ];

    http_response_code(500);
    echo json_encode($response);

    // Loguer l'erreur
    logEvent("Erreur API get_formations: " . $e->getMessage());
}

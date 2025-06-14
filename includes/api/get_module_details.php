<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die('<div class="alert alert-danger">ID de module invalide</div>');
}

$id = intval($_GET['id']);

try {
    // Récupération des infos de base du module
    $stmt = $pdo->prepare("SELECT m.*, f.name AS filiere_name 
                          FROM modules m
                          JOIN filieres f ON m.filiere_id = f.id
                          WHERE m.id = ?");
    $stmt->execute([$id]);
    $module = $stmt->fetch();
    
    if (!$module) {
        die('<div class="alert alert-danger">Module non trouvé</div>');
    }
    
    // Récupération des enseignants associés
    $teachers = $pdo->prepare("SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name, 
                              ta.start_date, ta.end_date, ta.hours_assigned
                              FROM teacher_assignments ta
                              JOIN staff s ON ta.teacher_id = s.id
                              JOIN users u ON s.user_id = u.id
                              WHERE ta.module_id = ?
                              ORDER BY ta.start_date DESC");
    $teachers->execute([$id]);
    
    // Récupération des étudiants inscrits
    $students = $pdo->prepare("SELECT sm.id, s.id AS student_id, CONCAT(u.first_name, ' ', u.last_name) AS name,
                              sm.status, sm.start_date, sm.end_date, sm.note
                              FROM student_modules sm
                              JOIN students s ON sm.student_id = s.id
                              JOIN users u ON s.user_id = u.id
                              WHERE sm.module_id = ?
                              ORDER BY sm.status, u.last_name");
    $students->execute([$id]);
    
    // Affichage des détails
    echo '<div class="row">';
    echo '<div class="col-md-6">';
    echo '<h4>Informations de base</h4>';
    echo '<table class="table table-bordered">';
    echo '<tr><th>Code</th><td>' . htmlspecialchars($module['code']) . '</td></tr>';
    echo '<tr><th>Nom</th><td>' . htmlspecialchars($module['name']) . '</td></tr>';
    echo '<tr><th>Filière</th><td>' . htmlspecialchars($module['filiere_name']) . '</td></tr>';
    echo '<tr><th>Durée</th><td>' . htmlspecialchars($module['duration_hours']) . ' heures</td></tr>';
    echo '</table>';
    
    if (!empty($module['description'])) {
        echo '<h5>Description</h5>';
        echo '<div class="p-3 bg-light rounded">' . nl2br(htmlspecialchars($module['description'])) . '</div>';
    }
    echo '</div>';
    
    echo '<div class="col-md-6">';
    echo '<h4>Enseignants</h4>';
    if ($teachers->rowCount() > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Nom</th><th>Heures</th><th>Période</th></tr></thead>';
        echo '<tbody>';
        foreach ($teachers->fetchAll() as $teacher) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($teacher['name']) . '</td>';
            echo '<td>' . htmlspecialchars($teacher['hours_assigned']) . '</td>';
            echo '<td>' . date('d/m/Y', strtotime($teacher['start_date'])) . ' - ';
            echo $teacher['end_date'] ? date('d/m/Y', strtotime($teacher['end_date'])) : 'En cours';
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p class="text-muted">Aucun enseignant assigné</p>';
    }
    
    echo '<h4 class="mt-4">Étudiants inscrits</h4>';
    if ($students->rowCount() > 0) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm">';
        echo '<thead><tr><th>Nom</th><th>Statut</th><th>Note</th></tr></thead>';
        echo '<tbody>';
        foreach ($students->fetchAll() as $student) {
            $statusClass = [
                'non_commence' => 'secondary',
                'en_cours' => 'info',
                'valide' => 'success',
                'echec' => 'danger'
            ][$student['status']] ?? 'secondary';
            
            echo '<tr>';
            echo '<td>' . htmlspecialchars($student['name']) . '</td>';
            echo '<td><span class="badge bg-' . $statusClass . '">';
            echo ucfirst(str_replace('_', ' ', $student['status']));
            echo '</span></td>';
            echo '<td>' . ($student['note'] ?? '-') . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table></div>';
    } else {
        echo '<p class="text-muted">Aucun étudiant inscrit</p>';
    }
    echo '</div></div>';
    
} catch (PDOException $e) {
    die('<div class="alert alert-danger">Erreur de base de données: ' . htmlspecialchars($e->getMessage()) . '</div>');
}
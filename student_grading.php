<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits professeur
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'professeur') {
    header('Location: login.php');
    exit();
}

$pageTitle = "Évaluation des Étudiants - CFP-CMD";
include_once 'includes/header2.php';

try {
    // Récupérer l'ID du professeur
    $stmt = $pdo->prepare("SELECT id FROM staff WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $teacher_id = $stmt->fetchColumn();
    
    if (!$teacher_id) {
        throw new Exception("Professeur non trouvé");
    }

    // Récupérer les modules enseignés par ce professeur
    $stmt = $pdo->prepare("
        SELECT m.id, m.code, m.name, f.name AS formation
        FROM teacher_assignments ta
        JOIN modules m ON ta.module_id = m.id
        JOIN formations f ON ta.formation_id = f.id
        WHERE ta.teacher_id = ? AND (ta.end_date IS NULL OR ta.end_date >= CURDATE())
    ");
    $stmt->execute([$teacher_id]);
    $modules = $stmt->fetchAll();

    // Initialisation des variables
    $selected_module = null;
    $students = [];
    $error = null;
    
    // Si un module est sélectionné
    if (isset($_GET['module_id']) && is_numeric($_GET['module_id'])) {
        $stmt = $pdo->prepare("
            SELECT m.id, m.code, m.name, m.duration_hours, f.name AS formation
            FROM modules m
            JOIN teacher_assignments ta ON m.id = ta.module_id
            JOIN formations f ON ta.formation_id = f.id
            WHERE m.id = ? AND ta.teacher_id = ?
        ");
        $stmt->execute([$_GET['module_id'], $teacher_id]);
        $selected_module = $stmt->fetch();
        
        if ($selected_module) {
            $stmt = $pdo->prepare("
                SELECT sm.id, s.id AS student_id, 
                       CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                       sm.status, sm.note, sm.start_date, sm.end_date
                FROM student_modules sm
                JOIN students s ON sm.student_id = s.id
                JOIN users u ON s.user_id = u.id
                WHERE sm.module_id = ? AND sm.teacher_id = ?
                ORDER BY student_name
            ");
            $stmt->execute([$_GET['module_id'], $teacher_id]);
            $students = $stmt->fetchAll();
        }
    }

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<main class="grading-container">
    <h1><i class="fas fa-check-circle"></i> Évaluation des Étudiants</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="module-selection">
        <h2><i class="fas fa-book"></i> Sélectionnez un Module</h2>
        <?php if (empty($modules)): ?>
            <div class="alert alert-info">Aucun module assigné à ce professeur.</div>
        <?php else: ?>
            <div class="module-list">
                <?php foreach ($modules as $module): ?>
                    <a href="?module_id=<?= htmlspecialchars($module['id']) ?>" class="module-card <?= $selected_module && $selected_module['id'] == $module['id'] ? 'active' : '' ?>">
                        <h3><?= htmlspecialchars($module['code']) ?></h3>
                        <p><?= htmlspecialchars($module['name']) ?></p>
                        <small><?= htmlspecialchars($module['formation']) ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($selected_module): ?>
    <section class="grading-section">
        <h2>
            <i class="fas fa-chalkboard-teacher"></i> 
            <?= htmlspecialchars($selected_module['name']) ?> 
            <small>(<?= htmlspecialchars($selected_module['code']) ?>)</small>
        </h2>
        <p class="module-info">
            Formation: <?= htmlspecialchars($selected_module['formation']) ?> | 
            Durée: <?= htmlspecialchars($selected_module['duration_hours']) ?> heures
        </p>

        <?php if (empty($students)): ?>
            <div class="alert alert-info">Aucun étudiant inscrit à ce module.</div>
        <?php else: ?>
            <form method="POST" action="api/save_grades.php">
                <input type="hidden" name="module_id" value="<?= htmlspecialchars($selected_module['id']) ?>">
                <input type="hidden" name="teacher_id" value="<?= htmlspecialchars($teacher_id) ?>">
                
                <table class="grading-table">
                    <thead>
                        <tr>
                            <th>Étudiant</th>
                            <th>Statut</th>
                            <th>Note (/20)</th>
                            <th>Date début</th>
                            <th>Date fin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= htmlspecialchars($student['student_name']) ?></td>
                            <td>
                                <select name="status[<?= htmlspecialchars($student['student_id']) ?>]" class="form-control">
                                    <option value="non_commence" <?= $student['status'] === 'non_commence' ? 'selected' : '' ?>>Non commencé</option>
                                    <option value="en_cours" <?= $student['status'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                    <option value="valide" <?= $student['status'] === 'valide' ? 'selected' : '' ?>>Validé</option>
                                    <option value="echec" <?= $student['status'] === 'echec' ? 'selected' : '' ?>>Échec</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="note[<?= htmlspecialchars($student['student_id']) ?>]" 
                                       value="<?= htmlspecialchars($student['note']) ?>" min="0" max="20" step="0.5" class="form-control">
                            </td>
                            <td>
                                <input type="date" name="start_date[<?= htmlspecialchars($student['student_id']) ?>]" 
                                       value="<?= htmlspecialchars($student['start_date']) ?>" class="form-control">
                            </td>
                            <td>
                                <input type="date" name="end_date[<?= htmlspecialchars($student['student_id']) ?>]" 
                                       value="<?= htmlspecialchars($student['end_date']) ?>" class="form-control">
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer les modifications
                    </button>
                    <a href="student_grading.php" class="btn btn-secondary">Annuler</a>
                </div>
            </form>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>

<?php include_once 'includes/footer.php'; ?>
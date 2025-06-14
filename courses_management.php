<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès
if (!hasRole('professeur')) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Gestion des Cours - CFP-CMD";
include 'includes/header2.php';

// Récupérer les cours assignés au professeur
$teacherId = $_SESSION['user_id'];
$courses = $pdo->prepare("
    SELECT ta.id, m.code, m.name AS module_name, m.duration_hours,
           f.name AS formation, fi.name AS filiere,
           ta.start_date, ta.end_date, ta.hours_assigned,
           COUNT(sm.id) AS student_count
    FROM teacher_assignments ta
    JOIN modules m ON ta.module_id = m.id
    JOIN formations f ON ta.formation_id = f.id
    JOIN filieres fi ON f.filiere_id = fi.id
    LEFT JOIN student_modules sm ON sm.module_id = m.id AND sm.formation_id = f.id
    WHERE ta.teacher_id = (SELECT id FROM staff WHERE user_id = ?)
    AND (ta.end_date IS NULL OR ta.end_date >= CURDATE())
    GROUP BY ta.id
    ORDER BY ta.start_date DESC
");
$courses->execute([$teacherId]);
$courses = $courses->fetchAll();
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-chalkboard-teacher"></i> Mes Cours</h1>
    
    <div class="card">
        <div class="card-header">
            <h2>Modules Assignés</h2>
        </div>
        <div class="card-body">
            <?php if (count($courses) > 0): ?>
                <div class="courses-grid">
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <div class="course-header">
                                <h3><?= htmlspecialchars($course['module_name']) ?></h3>
                                <span class="course-code"><?= htmlspecialchars($course['code']) ?></span>
                            </div>
                            <div class="course-details">
                                <p><i class="fas fa-book"></i> <?= htmlspecialchars($course['formation']) ?> (<?= $course['filiere'] ?>)</p>
                                <p><i class="fas fa-clock"></i> <?= $course['hours_assigned'] ?> heures / <?= $course['duration_hours'] ?> heures totales</p>
                                <p><i class="fas fa-users"></i> <?= $course['student_count'] ?> étudiants</p>
                                <p><i class="fas fa-calendar-alt"></i> 
                                    <?= date('d/m/Y', strtotime($course['start_date'])) ?>
                                    <?= $course['end_date'] ? ' - ' . date('d/m/Y', strtotime($course['end_date'])) : '' ?>
                                </p>
                            </div>
                            <div class="course-actions">
                                <a href="course_details.php?id=<?= $course['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-info-circle"></i> Détails
                                </a>
                                <a href="course_students.php?module=<?= $course['id'] ?>" class="btn btn-outline">
                                    <i class="fas fa-user-graduate"></i> Étudiants
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aucun module ne vous est actuellement assigné.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.courses-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.course-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 20px;
    border-left: 4px solid #0c6fb5;
}

.course-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.course-header h3 {
    margin: 0;
    color: #333;
}

.course-code {
    background: #f0f0f0;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 14px;
    color: #666;
}

.course-details p {
    margin: 8px 0;
    color: #555;
    font-size: 14px;
}

.course-details i {
    width: 20px;
    color: #0c6fb5;
}

.course-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .courses-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
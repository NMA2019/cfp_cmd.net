<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits
if (!hasRole(['admin', 'super_admin'])) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Vérification des Professeurs";
include 'includes/header.php';

// Requête des professeurs avec leurs charges
$professors = $pdo->query("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name, u.email, u.phone,
           COUNT(ta.id) AS assigned_courses,
           SUM(ta.hours_assigned) AS total_hours,
           s.status
    FROM staff s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN teacher_assignments ta ON ta.teacher_id = s.id 
           AND (ta.end_date IS NULL OR ta.end_date >= CURDATE())
    WHERE s.type = 'enseignant' AND u.is_active = 1
    GROUP BY s.id
    ORDER BY name
")->fetchAll();
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-chalkboard-teacher"></i> Vérification des Professeurs</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="professorsTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom complet</th>
                            <th>Contact</th>
                            <th>Cours assignés</th>
                            <th>Heures totales</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($professors as $prof): ?>
                        <tr>
                            <td><?= $prof['name'] ?></td>
                            <td>
                                <?= $prof['email'] ?><br>
                                <?= $prof['phone'] ?>
                            </td>
                            <td><?= $prof['assigned_courses'] ?></td>
                            <td><?= $prof['total_hours'] ?></td>
                            <td>
                                <span class="badge badge-<?= $prof['status'] === 'actif' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($prof['status']) ?>
                                </span>
                            </td>
                            <td>
                                <a href="professor_details.php?id=<?= $prof['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Détails
                                </a>
                                <a href="professor_schedule.php?id=<?= $prof['id'] ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-calendar-alt"></i> Emploi du temps
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#professorsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
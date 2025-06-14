<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits
if (!hasRole('2') && !hasRole('3') && !hasRole('4')) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Gestion des Soutenances";
include 'includes/header.php';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'controllers/soutenance_controller.php';
}

// Récupération des données
$soutenances = $pdo->query("
    SELECT s.*, 
           stu.matricule, CONCAT(u.first_name, ' ', u.last_name) AS student_name,
           f.name AS formation_name,
           CONCAT(prof.first_name, ' ', prof.last_name) AS professor_name,
           CONCAT(co_prof.first_name, ' ', co_prof.last_name) AS co_professor_name
    FROM soutenances s
    JOIN students stu ON s.student_id = stu.id
    JOIN users u ON stu.user_id = u.id
    JOIN formations f ON s.formation_id = f.id
    JOIN staff prof_staff ON s.teacher_id = prof_staff.id
    JOIN users prof ON prof_staff.user_id = prof.id
    LEFT JOIN staff co_prof_staff ON s.co_teacher_id = co_prof_staff.id
    LEFT JOIN users co_prof ON co_prof_staff.user_id = co_prof.id
    ORDER BY s.presentation_date DESC
")->fetchAll();

$students = $pdo->query("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name, f.name AS formation
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN formations f ON s.formation_id = f.id
    WHERE s.status IN ('formation', 'soutenance')
")->fetchAll();

$professors = $pdo->query("
    SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name
    FROM staff s
    JOIN users u ON s.user_id = u.id
    WHERE s.type = 'enseignant' AND s.status = 'actif'
")->fetchAll();
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-graduation-cap"></i> Gestion des Soutenances</h2>
            <button class="btn btn-light" data-toggle="modal" data-target="#addSoutenanceModal">
                <i class="fas fa-plus"></i> Nouvelle soutenance
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="soutenancesTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Titre</th>
                            <th>Encadrant</th>
                            <th>Co-encadrant</th>
                            <th>Statut</th>
                            <th>Note</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($soutenances as $soutenance): ?>
                        <tr>
                            <td><?= date('d/m/Y H:i', strtotime($soutenance['presentation_date'])) ?></td>
                            <td><?= $soutenance['student_name'] ?> (<?= $soutenance['matricule'] ?>)</td>
                            <td><?= $soutenance['formation_name'] ?></td>
                            <td><?= $soutenance['title'] ?></td>
                            <td><?= $soutenance['professor_name'] ?></td>
                            <td><?= $soutenance['co_professor_name'] ?? 'N/A' ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $soutenance['status'] === 'planifiee' ? 'warning' : 
                                    ($soutenance['status'] === 'terminee' ? 'success' : 'danger') 
                                ?>">
                                    <?= ucfirst($soutenance['status']) ?>
                                </span>
                            </td>
                            <td><?= $soutenance['note'] ?? 'N/A' ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" data-id="<?= $soutenance['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if(isSuperAdmin()): ?>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $soutenance['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modals -->
<div class="modal fade" id="addSoutenanceModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="soutenance.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Ajouter une soutenance</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Étudiant</label>
                            <select class="form-control" name="student_id" required>
                                <?php foreach ($students as $student): ?>
                                <option value="<?= $student['id'] ?>">
                                    <?= $student['name'] ?> - <?= $student['formation'] ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Formation</label>
                            <input type="text" class="form-control" value="<?= $student['formation'] ?>" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Titre de la soutenance</label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Encadrant principal</label>
                            <select class="form-control" name="teacher_id" required>
                                <?php foreach ($professors as $professor): ?>
                                <option value="<?= $professor['id'] ?>"><?= $professor['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Co-encadrant (optionnel)</label>
                            <select class="form-control" name="co_teacher_id">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($professors as $professor): ?>
                                <option value="<?= $professor['id'] ?>"><?= $professor['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Date et heure</label>
                            <input type="datetime-local" class="form-control" name="presentation_date" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Salle</label>
                            <input type="text" class="form-control" name="room">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <input type="hidden" name="action" value="add">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal (similaire à add mais pré-rempli) -->
<!-- Delete Confirmation Modal -->

<script>
$(document).ready(function() {
    $('#soutenancesTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
        }
    });

    // Gestion de l'édition
    $('.edit-btn').click(function() {
        const id = $(this).data('id');
        // Charger les données via AJAX et afficher le modal d'édition
    });

    // Gestion de la suppression
    $('.delete-btn').click(function() {
        if(confirm("Voulez-vous vraiment supprimer cette soutenance ?")) {
            window.location = 'soutenance.php?action=delete&id=' + $(this).data('id');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
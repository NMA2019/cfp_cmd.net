<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits
if (!hasRole(['admin', 'super_admin', 'professeur'])) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Vérification des Étudiants";
include 'includes/header.php';

// Filtres
$formation = $_GET['formation'] ?? '';
$status = $_GET['status'] ?? '';

// Requête de base
$query = "
    SELECT s.id, s.matricule, CONCAT(u.first_name, ' ', u.last_name) AS name, 
           u.email, u.phone, s.status, f.name AS formation,
           COUNT(sm.id) AS modules_completed
    FROM students s
    JOIN users u ON s.user_id = u.id
    JOIN formations f ON s.formation_id = f.id
    LEFT JOIN student_modules sm ON sm.student_id = s.id AND sm.status = 'valide'
    WHERE u.is_active = 1
";

// Application des filtres
$params = [];
if (!empty($formation)) {
    $query .= " AND f.id = ?";
    $params[] = $formation;
}
if (!empty($status)) {
    $query .= " AND s.status = ?";
    $params[] = $status;
}

$query .= " GROUP BY s.id ORDER BY s.status, name";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();

$formations = $pdo->query("SELECT id, name FROM formations WHERE is_active = 1")->fetchAll();
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white">
            <h2><i class="fas fa-user-graduate"></i> Vérification des Étudiants</h2>
        </div>
        <div class="card-body">
            <!-- Filtres -->
            <form method="GET" class="mb-4">
                <div class="form-row">
                    <div class="form-group col-md-5">
                        <label>Formation</label>
                        <select class="form-control" name="formation">
                            <option value="">Toutes les formations</option>
                            <?php foreach ($formations as $f): ?>
                            <option value="<?= $f['id'] ?>" <?= $formation == $f['id'] ? 'selected' : '' ?>>
                                <?= $f['name'] ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-5">
                        <label>Statut</label>
                        <select class="form-control" name="status">
                            <option value="">Tous les statuts</option>
                            <option value="preinscrit" <?= $status == 'preinscrit' ? 'selected' : '' ?>>Préinscrit</option>
                            <option value="inscrit" <?= $status == 'inscrit' ? 'selected' : '' ?>>Inscrit</option>
                            <option value="formation" <?= $status == 'formation' ? 'selected' : '' ?>>En formation</option>
                            <option value="soutenance" <?= $status == 'soutenance' ? 'selected' : '' ?>>En soutenance</option>
                            <option value="diplome" <?= $status == 'diplome' ? 'selected' : '' ?>>Diplômé</option>
                        </select>
                    </div>
                    <div class="form-group col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </div>
            </form>

            <!-- Résultats -->
            <div class="table-responsive">
                <table class="table table-bordered" id="studentsTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Matricule</th>
                            <th>Nom complet</th>
                            <th>Formation</th>
                            <th>Contact</th>
                            <th>Statut</th>
                            <th>Modules validés</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= $student['matricule'] ?></td>
                            <td><?= $student['name'] ?></td>
                            <td><?= $student['formation'] ?></td>
                            <td>
                                <?= $student['email'] ?><br>
                                <?= $student['phone'] ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $student['status'] === 'diplome' ? 'success' : 
                                    ($student['status'] === 'formation' ? 'primary' : 'warning') 
                                ?>">
                                    <?= ucfirst($student['status']) ?>
                                </span>
                            </td>
                            <td><?= $student['modules_completed'] ?></td>
                            <td>
                                <a href="student_details.php?id=<?= $student['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i> Détails
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
    $('#studentsTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.25/i18n/French.json"
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
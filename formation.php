<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

checkAdminAccess();

$pageTitle = "Gestion des Formations";
include 'includes/header.php';

// Traitement CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'controllers/formation_controller.php';
}

// Récupération des données
$formations = $pdo->query("
    SELECT f.*, fi.name AS filiere_name, ft.name AS type_name
    FROM formations f
    JOIN filieres fi ON f.filiere_id = fi.id
    JOIN formation_types ft ON f.type_id = ft.id
    ORDER BY f.name
")->fetchAll();

$filieres = $pdo->query("SELECT * FROM filieres ORDER BY name")->fetchAll();
$types = $pdo->query("SELECT * FROM formation_types ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-graduation-cap"></i> Gestion des Formations</h2>
            <button class="btn btn-light" data-toggle="modal" data-target="#addFormationModal">
                <i class="fas fa-plus"></i> Nouvelle formation
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="formationsTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Nom</th>
                            <th>Filière</th>
                            <th>Type</th>
                            <th>Durée (mois)</th>
                            <th>Prix</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($formations as $formation): ?>
                        <tr>
                            <td><?= $formation['name'] ?></td>
                            <td><?= $formation['filiere_name'] ?></td>
                            <td><?= $formation['type_name'] ?></td>
                            <td><?= $formation['duration_months'] ?></td>
                            <td><?= number_format($formation['price'], 0, ',', ' ') ?> MGA</td>
                            <td>
                                <span class="badge badge-<?= $formation['is_active'] ? 'success' : 'secondary' ?>">
                                    <?= $formation['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" data-id="<?= $formation['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if(isSuperAdmin()): ?>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $formation['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                                <a href="formation_modules.php?id=<?= $formation['id'] ?>" class="btn btn-sm btn-info">
                                    <i class="fas fa-list"></i> Modules
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

<!-- Add Formation Modal -->
<div class="modal fade" id="addFormationModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" action="formations.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Ajouter une formation</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Nom de la formation</label>
                            <input type="text" class="form-control" name="name" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Filière</label>
                            <select class="form-control" name="filiere_id" required>
                                <?php foreach ($filieres as $filiere): ?>
                                <option value="<?= $filiere['id'] ?>"><?= $filiere['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Type de formation</label>
                            <select class="form-control" name="type_id" required>
                                <?php foreach ($types as $type): ?>
                                <option value="<?= $type['id'] ?>"><?= $type['name'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Durée (mois)</label>
                            <input type="number" class="form-control" name="duration_months" min="1" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-md-6">
                            <label>Prix (MGA)</label>
                            <input type="number" class="form-control" name="price" min="0" required>
                        </div>
                        <div class="form-group col-md-6">
                            <label>Capacité</label>
                            <input type="number" class="form-control" name="capacity" min="1">
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" checked>
                            <label class="form-check-label" for="is_active">
                                Formation active
                            </label>
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
    $('#formationsTable').DataTable({
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
        if(confirm("Voulez-vous vraiment supprimer cette formation ?")) {
            window.location = 'formations.php?action=delete&id=' + $(this).data('id');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

checkAdminAccess();

$pageTitle = "Gestion des Filières";
include 'includes/header.php';

// Traitement CRUD
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require 'controllers/filiere_controller.php';
}

// Récupération des données
$filieres = $pdo->query("SELECT * FROM filieres ORDER BY name")->fetchAll();
?>

<div class="container-fluid">
    <div class="card shadow mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h2><i class="fas fa-sitemap"></i> Gestion des Filières</h2>
            <button class="btn btn-light" data-toggle="modal" data-target="#addFiliereModal">
                <i class="fas fa-plus"></i> Nouvelle filière
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="filieresTable" width="100%" cellspacing="0">
                    <thead class="table-dark">
                        <tr>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Description</th>
                            <th>Durée (mois)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filieres as $filiere): ?>
                        <tr>
                            <td><?= $filiere['code'] ?></td>
                            <td><?= $filiere['name'] ?></td>
                            <td><?= $filiere['description'] ?></td>
                            <td><?= $filiere['duration_months'] ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary edit-btn" data-id="<?= $filiere['id'] ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if(isSuperAdmin()): ?>
                                <button class="btn btn-sm btn-danger delete-btn" data-id="<?= $filiere['id'] ?>">
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

<!-- Add Filiere Modal -->
<div class="modal fade" id="addFiliereModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" action="filieres.php">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Ajouter une filière</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Code</label>
                        <input type="text" class="form-control" name="code" required maxlength="10">
                    </div>
                    <div class="form-group">
                        <label>Nom</label>
                        <input type="text" class="form-control" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea class="form-control" name="description" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Durée (mois)</label>
                        <input type="number" class="form-control" name="duration_months" min="1" required>
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
    $('#filieresTable').DataTable({
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
        if(confirm("Voulez-vous vraiment supprimer cette filière ?")) {
            window.location = 'filieres.php?action=delete&id=' + $(this).data('id');
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
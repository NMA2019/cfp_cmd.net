<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérifier les droits d'accès - Seuls les admins et professeurs peuvent gérer les modules
checkRole([1, 2, 3]); // Super Admin, Admin, Professeur

// Récupérer la liste des filières pour les selects
try {
    $filieres = $pdo->query("SELECT id, name FROM filieres ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    logEvent("Erreur filières: " . $e->getMessage());
    $error = "Erreur lors du chargement des filières";
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id = $_POST['id'] ?? 0;
    $filiere_id = $_POST['filiere_id'] ?? '';
    $code = $_POST['code'] ?? '';
    $name = $_POST['name'] ?? '';
    $duration_hours = $_POST['duration_hours'] ?? '';
    $description = $_POST['description'] ?? '';

    // Validation des données
    $errors = [];
    if (empty($filiere_id)) $errors[] = "La filière est obligatoire";
    if (empty($code)) $errors[] = "Le code du module est obligatoire";
    if (empty($name)) $errors[] = "Le nom du module est obligatoire";
    if (!is_numeric($duration_hours) || $duration_hours <= 0) $errors[] = "La durée doit être un nombre positif";

    if (empty($errors)) {
        try {
            if ($action === 'add') {
                // Ajout d'un nouveau module
                $stmt = $pdo->prepare("INSERT INTO modules (filiere_id, code, name, duration_hours, description) 
                                      VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$filiere_id, $code, $name, $duration_hours, $description]);
                $success = "Module ajouté avec succès";
                logEvent("Ajout module: " . $name . " (" . $code . ")");
            } elseif ($action === 'edit') {
                // Modification d'un module existant
                $stmt = $pdo->prepare("UPDATE modules SET filiere_id=?, code=?, name=?, duration_hours=?, description=? 
                                      WHERE id=?");
                $stmt->execute([$filiere_id, $code, $name, $duration_hours, $description, $id]);
                $success = "Module mis à jour avec succès";
                logEvent("Modification module ID: " . $id);
            }
        } catch (PDOException $e) {
            logEvent("Erreur module: " . $e->getMessage());
            $error = "Erreur lors de l'opération sur le module";
            if ($e->getCode() == 23000) {
                $error = "Ce code de module existe déjà";
            }
        }
    } else {
        $error = implode("<br>", $errors);
    }
}

// Traitement de la suppression
if (isset($_GET['delete'])) {
    $id = intval($_GET['delete']);
    try {
        $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
        $stmt->execute([$id]);
        $success = "Module supprimé avec succès";
        logEvent("Suppression module ID: " . $id);
    } catch (PDOException $e) {
        logEvent("Erreur suppression module: " . $e->getMessage());
        $error = "Impossible de supprimer ce module car il est lié à d'autres données";
    }
}

// Récupération des modules avec pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Construction de la requête avec recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$where = [];
$params = [];

if (!empty($search)) {
    $where[] = "(m.name LIKE ? OR m.code LIKE ? OR f.name LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%", "%$search%"]);
}

$whereClause = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

// Requête principale
$sql = "SELECT m.*, f.name AS filiere_name 
        FROM modules m
        JOIN filieres f ON m.filiere_id = f.id
        $whereClause
        ORDER BY f.name, m.name
        LIMIT $offset, $perPage";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $modules = $stmt->fetchAll();

    // Comptage total pour la pagination
    $countSql = "SELECT COUNT(*) FROM modules m JOIN filieres f ON m.filiere_id = f.id $whereClause";
    $total = $pdo->prepare($countSql);
    $total->execute($params);
    $totalModules = $total->fetchColumn();
    $totalPages = ceil($totalModules / $perPage);
} catch (PDOException $e) {
    logEvent("Erreur liste modules: " . $e->getMessage());
    $error = "Erreur lors du chargement des modules";
}

$pageTitle = "Gestion des Modules - CFP-CMD";
include_once 'includes/header.php';
?>

<main class="container py-5">
    <h1 class="mb-4"><i class="fas fa-book-open"></i> Gestion des Modules</h1>
    
    <!-- Affichage des messages d'erreur/succès -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0"><i class="fas fa-list"></i> Liste des Modules</h2>
                <button class="btn btn-light btn-sm" onclick="openModuleModal('add')">
                    <i class="fas fa-plus"></i> Ajouter
                </button>
            </div>
        </div>
        <div class="card-body">
            <!-- Barre de recherche -->
            <form method="get" class="mb-4">
                <div class="input-group">
                    <input type="text" name="search" class="form-control" placeholder="Rechercher un module..." 
                           value="<?= htmlspecialchars($search) ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Rechercher
                    </button>
                    <?php if (!empty($search)): ?>
                        <a href="modules.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Annuler
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Tableau des modules -->
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Code</th>
                            <th>Nom</th>
                            <th>Filière</th>
                            <th>Durée (heures)</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($modules)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Aucun module trouvé</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($modules as $index => $module): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td><?= htmlspecialchars($module['code']) ?></td>
                                    <td><?= htmlspecialchars($module['name']) ?></td>
                                    <td><?= htmlspecialchars($module['filiere_name']) ?></td>
                                    <td><?= htmlspecialchars($module['duration_hours']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" 
                                                onclick="openModuleModal('edit', <?= $module['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="modules.php?delete=<?= $module['id'] ?>" 
                                           class="btn btn-sm btn-danger" 
                                           onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce module?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <button class="btn btn-sm btn-secondary" 
                                                onclick="showModuleDetails(<?= $module['id'] ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <nav aria-label="Page navigation">
                    <ul class="pagination justify-content-center">
                        <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page-1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-angle-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page+1 ?>&search=<?= urlencode($search) ?>">
                                    <i class="fas fa-angle-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Modal pour ajouter/modifier un module -->
<div id="moduleModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="moduleModalTitle"></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="moduleForm" method="post">
                <input type="hidden" name="action" id="formAction">
                <input type="hidden" name="id" id="moduleId">
                
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="filiere_id" class="form-label">Filière *</label>
                            <select class="form-select" id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionnez une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?= $filiere['id'] ?>"><?= htmlspecialchars($filiere['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="code" class="form-label">Code du module *</label>
                            <input type="text" class="form-control" id="code" name="code" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="name" class="form-label">Nom du module *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label for="duration_hours" class="form-label">Durée (heures) *</label>
                            <input type="number" class="form-control" id="duration_hours" name="duration_hours" min="1" required>
                        </div>
                        
                        <div class="col-md-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal pour afficher les détails -->
<div id="detailsModal" class="modal fade" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">Détails du Module</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="moduleDetailsContent">
                <!-- Contenu chargé via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts JavaScript -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Fonction pour ouvrir le modal d'ajout/modification
function openModuleModal(action, id = 0) {
    const modal = new bootstrap.Modal(document.getElementById('moduleModal'));
    const form = document.getElementById('moduleForm');
    
    if (action === 'add') {
        document.getElementById('moduleModalTitle').textContent = 'Ajouter un nouveau module';
        document.getElementById('formAction').value = 'add';
        document.getElementById('moduleId').value = '';
        form.reset();
    } else if (action === 'edit' && id > 0) {
        document.getElementById('moduleModalTitle').textContent = 'Modifier le module';
        document.getElementById('formAction').value = 'edit';
        document.getElementById('moduleId').value = id;
        
        // Charger les données du module via AJAX
        fetch(`api/get_module.php?id=${id}`)
            .then(response => response.json())
            .then(data => {
                document.getElementById('filiere_id').value = data.filiere_id;
                document.getElementById('code').value = data.code;
                document.getElementById('name').value = data.name;
                document.getElementById('duration_hours').value = data.duration_hours;
                document.getElementById('description').value = data.description || '';
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors du chargement des données du module');
            });
    }
    
    modal.show();
}

// Fonction pour afficher les détails d'un module
function showModuleDetails(id) {
    const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
    
    // Charger les détails via AJAX
    fetch(`api/get_module_details.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('moduleDetailsContent').innerHTML = html;
            modal.show();
        })
        .catch(error => {
            console.error('Erreur:', error);
            document.getElementById('moduleDetailsContent').innerHTML = 
                '<div class="alert alert-danger">Erreur lors du chargement des détails</div>';
            modal.show();
        });
}

// Initialisation des tooltips
document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php
include_once 'includes/footer.php';
?>
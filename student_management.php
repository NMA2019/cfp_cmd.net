<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès


$pageTitle = "Gestion des Étudiants - CFP-CMD";
include 'includes/header2.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_student'])) {
            // Mise à jour d'un étudiant
            $stmt = $pdo->prepare("UPDATE students SET status = ?, niveau_scolaire = ? WHERE id = ?");
            $stmt->execute([
                $_POST['status'],
                $_POST['niveau_scolaire'],
                $_POST['student_id']
            ]);
            $success = "Étudiant mis à jour avec succès!";
        } elseif (isset($_POST['delete_student'])) {
            // Vérification des droits avant suppression
            if (!isSuperAdmin()) {
                throw new Exception("Seul le Super Admin peut supprimer des étudiants");
            }
            // Suppression d'un étudiant (soft delete)
            $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = (SELECT user_id FROM students WHERE id = ?)")->execute([$_POST['student_id']]);
            $success = "Étudiant désactivé avec succès!";
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        logEvent("Erreur gestion étudiant: " . $e->getMessage());
    }
}

// Récupération des étudiants avec filtres
$statusFilter = $_GET['status'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';

$query = "SELECT s.id, s.matricule, s.status, s.niveau_scolaire, s.inscription_date,
                 CONCAT(u.first_name, ' ', u.last_name) AS full_name, u.email, u.phone, u.photo,
                 f.name AS formation, fi.name AS filiere
          FROM students s
          JOIN users u ON s.user_id = u.id
          JOIN formations f ON s.formation_id = f.id
          JOIN filieres fi ON f.filiere_id = fi.id
          WHERE u.is_active = TRUE";

$params = [];
$conditions = [];

if ($statusFilter !== 'all') {
    $conditions[] = "s.status = ?";
    $params[] = $statusFilter;
}

if (!empty($searchQuery)) {
    $conditions[] = "(s.matricule LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params[] = "%$searchQuery%";
    $params[] = "%$searchQuery%";
}

if (!empty($conditions)) {
    $query .= " AND " . implode(" AND ", $conditions);
}

$query .= " ORDER BY s.status, s.inscription_date DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll();
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-user-graduate"></i> Gestion des Étudiants</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Liste des Étudiants</h2>
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <select name="status" onchange="this.form.submit()">
                            <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les statuts</option>
                            <option value="preinscrit" <?= $statusFilter === 'preinscrit' ? 'selected' : '' ?>>Préinscrit</option>
                            <option value="inscrit" <?= $statusFilter === 'inscrit' ? 'selected' : '' ?>>Inscrit</option>
                            <option value="formation" <?= $statusFilter === 'formation' ? 'selected' : '' ?>>En formation</option>
                            <option value="soutenance" <?= $statusFilter === 'soutenance' ? 'selected' : '' ?>>En soutenance</option>
                            <option value="diplome" <?= $statusFilter === 'diplome' ? 'selected' : '' ?>>Diplômé</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($searchQuery) ?>">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <button class="btn btn-primary" onclick="printStudents()"><i class="fas fa-print"></i> Imprimer</button>
                <?php if (isSuperAdmin()): ?>
                    <button class="btn btn-danger" onclick="openModal('deleteMultipleModal')">
                        <i class="fas fa-trash"></i> Supprimer multiple
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="data-table">
                    <thead>
                        <tr>
                            <?php if (isSuperAdmin()): ?>
                                <th><input type="checkbox" id="selectAll"></th>
                            <?php endif; ?>
                            <th>#</th>
                            <th>Matricule</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): 
                            $statusClass = [
                                'preinscrit' => 'warning',
                                'inscrit' => 'info',
                                'formation' => 'primary',
                                'soutenance' => 'secondary',
                                'diplome' => 'success',
                                'abandon' => 'danger',
                                'suspendu' => 'dark'
                            ][$student['status']] ?? 'secondary';
                        ?>
                            <tr>
                                <?php if (isSuperAdmin()): ?>
                                    <td><input type="checkbox" class="student-checkbox" value="<?= $student['id'] ?>"></td>
                                <?php endif; ?>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($student['matricule']) ?></td>
                                <td>
                                    <img src="assets/images/users/<?= htmlspecialchars($student['photo']) ?>" alt="Photo" class="avatar-sm">
                                    <?= htmlspecialchars($student['full_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($student['formation']) ?> (<?= $student['filiere'] ?>)</td>
                                <td><?= htmlspecialchars($student['phone']) ?></td>
                                <td><span class="badge badge-<?= $statusClass ?>"><?= ucfirst($student['status']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="viewStudent(<?= $student['id'] ?>)">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="editStudent(<?= $student['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (isSuperAdmin()): ?>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $student['id'] ?>)">
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
</main>

<!-- Modal Détails Étudiant -->
<div id="studentModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close-modal" onclick="closeModal('studentModal')">&times;</span>
        <div id="studentDetails"></div>
    </div>
</div>

<!-- Modal Édition Étudiant -->
<div id="editStudentModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editStudentModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> Modifier l'Étudiant</h2>
        
        <form method="POST">
            <input type="hidden" id="edit_student_id" name="student_id">
            
            <div class="form-group">
                <label for="edit_status"><i class="fas fa-user-tag"></i> Statut</label>
                <select id="edit_status" name="status" required>
                    <option value="preinscrit">Préinscrit</option>
                    <option value="inscrit">Inscrit</option>
                    <option value="formation">En formation</option>
                    <option value="soutenance">En soutenance</option>
                    <option value="diplome">Diplômé</option>
                    <option value="abandon">Abandon</option>
                    <option value="suspendu">Suspendu</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="edit_niveau"><i class="fas fa-graduation-cap"></i> Niveau scolaire</label>
                <input type="text" id="edit_niveau" name="niveau_scolaire" placeholder="Dernier niveau atteint">
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editStudentModal')">Annuler</button>
                <button type="submit" name="update_student" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<script>
// Fonctions pour la gestion des étudiants
function viewStudent(studentId) {
    fetch(`api/get_student.php?id=${studentId}`)
        .then(response => response.json())
        .then(student => {
            let html = `
                <h2><i class="fas fa-user-graduate"></i> Détails de l'étudiant</h2>
                <div class="student-profile">
                    <div class="profile-header">
                        <img src="assets/images/users/${student.photo}" alt="Photo" class="profile-photo">
                        <div class="profile-info">
                            <h3>${student.full_name}</h3>
                            <p>Matricule: ${student.matricule}</p>
                            <p>${student.formation} (${student.filiere})</p>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-envelope"></i> Email:</span>
                            <span class="detail-value">${student.email}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Téléphone:</span>
                            <span class="detail-value">${student.phone || 'Non renseigné'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar-day"></i> Date d'inscription:</span>
                            <span class="detail-value">${student.inscription_date}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-user-tag"></i> Statut:</span>
                            <span class="detail-value badge badge-${getStatusClass(student.status)}">${ucfirst(student.status)}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-graduation-cap"></i> Niveau scolaire:</span>
                            <span class="detail-value">${student.niveau_scolaire || 'Non renseigné'}</span>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('studentDetails').innerHTML = html;
            openModal('studentModal');
        });
}

function editStudent(studentId) {
    fetch(`api/get_student.php?id=${studentId}`)
        .then(response => response.json())
        .then(student => {
            document.getElementById('edit_student_id').value = student.id;
            document.getElementById('edit_status').value = student.status;
            document.getElementById('edit_niveau').value = student.niveau_scolaire || '';
            openModal('editStudentModal');
        });
}

function confirmDelete(studentId) {
    document.getElementById('delete_student_id').value = studentId;
    openModal('deleteModal');
}

// Gestion de la sélection multiple
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('selectAll');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            const checkboxes = document.getElementsByClassName('student-checkbox');
            for (let checkbox of checkboxes) {
                checkbox.checked = this.checked;
            }
        });
    }
});

// Fonctions utilitaires
function getStatusClass(status) {
    const classes = {
        'preinscrit': 'warning',
        'inscrit': 'info',
        'formation': 'primary',
        'soutenance': 'secondary',
        'diplome': 'success',
        'abandon': 'danger',
        'suspendu': 'dark'
    };
    return classes[status] || 'secondary';
}

function ucfirst(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
}

// Gestion des modals
window.openModal = function(modalId) {
    document.getElementById(modalId).style.display = 'block';
};

window.closeModal = function(modalId) {
    document.getElementById(modalId).style.display = 'none';
};

// Fermer la modal si on clique en dehors
window.onclick = function(event) {
    if (event.target.className === 'modal') {
        const modals = document.getElementsByClassName('modal');
        for (let modal of modals) {
            modal.style.display = 'none';
        }
    }
};
</script>

<?php include 'includes/footer.php'; ?>
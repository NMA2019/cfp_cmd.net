<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

error_log("Session debug - Role: " . ($_SESSION['role'] ?? 'non défini') . 
                    " - ID: " . ($_SESSION['user_id'] ?? 'non défini'));

// Vérification des droits d'accès - Version corrigée
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: unauthorized.php');
    exit();
}

// Initialisation des variables
$success = $error = null;
$users = $roles = [];

// Le reste du fichier reste inchangé...
$pageTitle = "Gestion des Utilisateurs - CFP-CMD";
include 'includes/header2.php';

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['add_user'])) {
            // Ajout d'un nouvel utilisateur
            $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, password, phone, photo) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?)");
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $stmt->execute([
                $_POST['role_id'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $password,
                $_POST['phone'],
                $_POST['photo'] ?? 'default.png'
            ]);
            $success = "Utilisateur ajouté avec succès!";
        } elseif (isset($_POST['update_user'])) {
            // Mise à jour d'un utilisateur
            $stmt = $pdo->prepare("UPDATE users SET role_id = ?, first_name = ?, last_name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([
                $_POST['role_id'],
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone'],
                $_POST['user_id']
            ]);
            $success = "Utilisateur mis à jour avec succès!";
        } elseif (isset($_POST['delete_user'])) {
            // Vérification des droits avant suppression
            if (!isSuperAdmin()) {
                throw new Exception("Seul le Super Admin peut supprimer des utilisateurs");
            }
            // Suppression d'un utilisateur (soft delete)
            $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ?")->execute([$_POST['user_id']]);
            $success = "Utilisateur désactivé avec succès!";
        }
    } catch (Exception $e) {
        $error = "Erreur: " . $e->getMessage();
        logEvent("Erreur gestion utilisateur: " . $e->getMessage());
    }
}

// Récupération des utilisateurs et rôles
$users = $pdo->query("
    SELECT u.id, u.first_name, u.last_name, u.email, u.phone, u.photo, u.is_active, 
           r.name AS role_name, r.id AS role_id
    FROM users u
    JOIN roles r ON u.role_id = r.id
    WHERE u.is_active = TRUE
    ORDER BY u.last_name, u.first_name
")->fetchAll();

$roles = $pdo->query("SELECT * FROM roles ORDER BY name")->fetchAll();
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-users-cog"></i> Gestion des Utilisateurs</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Liste des Utilisateurs</h2>
            <button class="btn btn-primary" onclick="openModal('addUserModal')">
                <i class="fas fa-user-plus"></i> Ajouter
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Nom</th>
                            <th>Email</th>
                            <th>Téléphone</th>
                            <th>Rôle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $index => $user): ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td>
                                    <img src="assets/images/users/<?= htmlspecialchars($user['photo']) ?>" alt="Photo" class="avatar-sm">
                                    <?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?>
                                </td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td><?= htmlspecialchars($user['phone']) ?></td>
                                <td><span class="badge badge-secondary"><?= htmlspecialchars($user['role_name']) ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="editUser(<?= $user['id'] ?>)">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php if (isSuperAdmin()): ?>
                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $user['id'] ?>)">
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

<!-- Modal Ajout Utilisateur -->
<div id="addUserModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('addUserModal')">&times;</span>
        <h2><i class="fas fa-user-plus"></i> Ajouter un Utilisateur</h2>
        
        <form method="POST">
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name"><i class="fas fa-user"></i> Prénom</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name"><i class="fas fa-user"></i> Nom</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="email" name="email" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password"><i class="fas fa-lock"></i> Mot de passe</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="phone"><i class="fas fa-phone"></i> Téléphone</label>
                    <input type="text" id="phone" name="phone">
                </div>
            </div>
            
            <div class="form-group">
                <label for="role_id"><i class="fas fa-user-tag"></i> Rôle</label>
                <select id="role_id" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('addUserModal')">Annuler</button>
                <button type="submit" name="add_user" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Édition Utilisateur -->
<div id="editUserModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('editUserModal')">&times;</span>
        <h2><i class="fas fa-user-edit"></i> Modifier l'Utilisateur</h2>
        
        <form method="POST">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="form-row">
                <div class="form-group">
                    <label for="edit_first_name"><i class="fas fa-user"></i> Prénom</label>
                    <input type="text" id="edit_first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_last_name"><i class="fas fa-user"></i> Nom</label>
                    <input type="text" id="edit_last_name" name="last_name" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="edit_email"><i class="fas fa-envelope"></i> Email</label>
                <input type="email" id="edit_email" name="email" required>
            </div>
            
            <div class="form-group">
                <label for="edit_phone"><i class="fas fa-phone"></i> Téléphone</label>
                <input type="text" id="edit_phone" name="phone">
            </div>
            
            <div class="form-group">
                <label for="edit_role_id"><i class="fas fa-user-tag"></i> Rôle</label>
                <select id="edit_role_id" name="role_id" required>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('editUserModal')">Annuler</button>
                <button type="submit" name="update_user" class="btn btn-primary">Mettre à jour</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Confirmation Suppression -->
<div id="deleteModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <span class="close-modal" onclick="closeModal('deleteModal')">&times;</span>
        <h2><i class="fas fa-exclamation-triangle"></i> Confirmation</h2>
        <p>Êtes-vous sûr de vouloir désactiver cet utilisateur ?</p>
        
        <form method="POST" id="deleteForm">
            <input type="hidden" id="delete_user_id" name="user_id">
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('deleteModal')">Annuler</button>
                <button type="submit" name="delete_user" class="btn btn-danger">Confirmer</button>
            </div>
        </form>
    </div>
</div>

<script>
// Fonctions pour la gestion des utilisateurs
function editUser(userId) {
    fetch(`api/get_user.php?id=${userId}`)
        .then(response => response.json())
        .then(user => {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_first_name').value = user.first_name;
            document.getElementById('edit_last_name').value = user.last_name;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_phone').value = user.phone;
            document.getElementById('edit_role_id').value = user.role_id;
            openModal('editUserModal');
        });
}

function confirmDelete(userId) {
    document.getElementById('delete_user_id').value = userId;
    openModal('deleteModal');
}

// Initialisation des modals
document.addEventListener('DOMContentLoaded', function() {
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
});
</script>

<?php include 'includes/footer.php'; ?>
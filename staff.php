<?php
require_once 'config/db_connection.php';
checkAuth();
checkRole(['admin', 'super_admin']); // Roles en minuscules

$pageTitle = "Gestion du Personnel";

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Récupération du personnel
try {
    // Compter le total
    $totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
    $totalPages = ceil($totalStaff / $perPage);
    
    // Récupérer le personnel avec pagination
    $stmt = $pdo->prepare("
        SELECT s.id, s.type, s.qualification, s.hire_date, s.status,
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.email, u.phone, u.photo
        FROM staff s
        JOIN users u ON s.user_id = u.id
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $staffMembers = $stmt->fetchAll();
} catch (PDOException $e) {
    logEvent("Erreur staff: " . $e->getMessage());
    die("Erreur lors de la récupération du personnel");
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-users me-2"></i>Gestion du Personnel</h1>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStaffModal">
            <i class="fas fa-plus me-2"></i>Ajouter
        </button>
    </div>

    <!-- Tableau du personnel -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th><i class="fas fa-user"></i> Nom</th>
                            <th><i class="fas fa-envelope"></i> Email</th>
                            <th><i class="fas fa-phone"></i> Téléphone</th>
                            <th><i class="fas fa-briefcase"></i> Type</th>
                            <th><i class="fas fa-calendar"></i> Date d'embauche</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($staffMembers as $index => $staff): ?>
                        <tr>
                            <td><?= $index + 1 + $offset ?></td>
                            <td>
                                <div class="d-flex align-items-center">
                                    <img src="uploads/<?= htmlspecialchars($staff['photo']) ?>" class="rounded-circle me-2" width="32" height="32">
                                    <?= htmlspecialchars($staff['full_name']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td><?= htmlspecialchars($staff['phone'] ?? 'N/A') ?></td>
                            <td>
                                <?php 
                                $typeLabels = [
                                    'administratif' => 'Administratif',
                                    'enseignant' => 'Enseignant',
                                    'consultant' => 'Consultant',
                                    'technique' => 'Technique'
                                ];
                                echo $typeLabels[$staff['type']] ?? $staff['type'];
                                ?>
                            </td>
                            <td><?= date('d/m/Y', strtotime($staff['hire_date'])) ?></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-staff" 
                                        data-id="<?= $staff['id'] ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStaffModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                <button class="btn btn-sm btn-outline-danger delete-staff" 
                                        data-id="<?= $staff['id'] ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <nav aria-label="Page navigation">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i == $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</div>

<!-- Modale Ajout Personnel -->
<div class="modal fade" id="addStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Ajouter un Membre</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStaffForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="first_name" placeholder="Prénom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" name="last_name" placeholder="Nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="email" placeholder="Email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Mot de passe" required>
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="phone" placeholder="Téléphone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <select class="form-select" name="type" required>
                                    <option value="administratif">Administratif</option>
                                    <option value="enseignant">Enseignant</option>
                                    <option value="consultant">Consultant</option>
                                    <option value="technique">Technique</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                <input type="text" class="form-control" name="qualification" placeholder="Qualification">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" name="hire_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                <select class="form-select" name="status" required>
                                    <option value="actif">Actif</option>
                                    <option value="inactif">Inactif</option>
                                    <option value="congé">En congé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modale Modification Personnel -->
<div class="modal fade" id="editStaffModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier Membre</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStaffForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="editStaffId">
                <input type="hidden" name="user_id" id="editStaffUserId">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="editFirstName" name="first_name" placeholder="Prénom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-user"></i></span>
                                <input type="text" class="form-control" id="editLastName" name="last_name" placeholder="Nom" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="editEmail" name="email" placeholder="Email" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-key"></i></span>
                                <input type="password" class="form-control" name="password" placeholder="Nouveau mot de passe (laisser vide si inchangé)">
                                <button class="btn btn-outline-secondary toggle-password" type="button">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="editPhone" name="phone" placeholder="Téléphone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                <select class="form-select" id="editType" name="type" required>
                                    <option value="administratif">Administratif</option>
                                    <option value="enseignant">Enseignant</option>
                                    <option value="consultant">Consultant</option>
                                    <option value="technique">Technique</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-graduation-cap"></i></span>
                                <input type="text" class="form-control" id="editQualification" name="qualification" placeholder="Qualification">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="editHireDate" name="hire_date" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-image"></i></span>
                                <input type="file" class="form-control" name="photo" accept="image/*">
                            </div>
                            <div class="text-center mt-2">
                                <img id="editStaffPhotoPreview" src="" class="rounded-circle" width="80" height="80" style="display: none;">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                <select class="form-select" id="editStatus" name="status" required>
                                    <option value="actif">Actif</option>
                                    <option value="inactif">Inactif</option>
                                    <option value="congé">En congé</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Annuler
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Mettre à jour
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Afficher/masquer le mot de passe
    $('.toggle-password').click(function() {
        const input = $(this).siblings('input');
        const icon = $(this).find('i');
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });

    // Ajout d'un membre
    $('#addStaffForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: 'api/staff.php',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if(response.status === 201) {
                    location.reload();
                } else {
                    alert(response.error || 'Erreur lors de l\'enregistrement');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response?.error || 'Erreur serveur');
            }
        });
    });

    // Chargement des données pour l'édition
    $('.edit-staff').click(function() {
        const staffId = $(this).data('id');
        
        $.ajax({
            url: 'api/staff.php?id=' + staffId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if(response.status === 200) {
                    const staff = response.data;
                    $('#editStaffId').val(staff.id);
                    $('#editStaffUserId').val(staff.user_id);
                    $('#editFirstName').val(staff.first_name);
                    $('#editLastName').val(staff.last_name);
                    $('#editEmail').val(staff.email);
                    $('#editPhone').val(staff.phone);
                    $('#editType').val(staff.type);
                    $('#editQualification').val(staff.qualification);
                    $('#editHireDate').val(staff.hire_date);
                    $('#editStatus').val(staff.status);
                    
                    // Afficher la photo si elle existe
                    if(staff.photo && staff.photo !== 'default.png') {
                        $('#editStaffPhotoPreview').attr('src', 'uploads/' + staff.photo).show();
                    }
                } else {
                    alert(response.error || 'Erreur lors du chargement');
                }
            },
            error: function() {
                alert('Erreur serveur');
            }
        });
    });

    // Mise à jour d'un membre
    $('#editStaffForm').submit(function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        $.ajax({
            url: 'api/staff.php',
            method: 'PUT',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                if(response.status === 200) {
                    location.reload();
                } else {
                    alert(response.error || 'Erreur lors de la mise à jour');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert(response?.error || 'Erreur serveur');
            }
        });
    });

    // Suppression d'un membre
    $('.delete-staff').click(function() {
        if (confirm('Voulez-vous vraiment supprimer ce membre ?')) {
            const staffId = $(this).data('id');
            
            $.ajax({
                url: 'api/staff.php',
                method: 'DELETE',
                data: { id: staffId },
                dataType: 'json',
                success: function(response) {
                    if(response.status === 200) {
                        location.reload();
                    } else {
                        alert(response.error || 'Erreur lors de la suppression');
                    }
                },
                error: function(xhr) {
                    const response = xhr.responseJSON;
                    alert(response?.error || 'Erreur serveur');
                }
            });
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>
<?php
require_once 'config/db_connection.php';
checkAuth();
checkRole(['admin', 'super_admin', 'professeur']); // Roles en minuscules

$pageTitle = "Gestion des Étudiants";

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $perPage;

// Récupération des étudiants
try {
    // Compter le total
    $totalStudents = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $totalPages = ceil($totalStudents / $perPage);
    
    // Récupérer les étudiants avec pagination
    $stmt = $pdo->prepare("
        SELECT s.id, s.matricule, s.date_of_birth, s.gender, s.status, 
               CONCAT(u.first_name, ' ', u.last_name) as full_name,
               u.email, u.phone, f.name as formation_name,
               TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) as age
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON s.formation_id = f.id
        ORDER BY s.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll();
    
    // Récupérer les formations pour le formulaire
    $formations = $pdo->query("SELECT id, name FROM formations")->fetchAll();
} catch (PDOException $e) {
    logEvent("Erreur students: " . $e->getMessage());
    die("Erreur lors de la récupération des étudiants");
}
?>

<?php include 'includes/header2.php'; ?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user-graduate me-2"></i>Gestion des Étudiants</h1>
        <?php if (in_array($_SESSION['role'], ['admin', 'super_admin'])): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addStudentModal">
            <i class="fas fa-plus me-2"></i>Ajouter
        </button>
        <?php endif; ?>
    </div>

    <!-- Tableau des étudiants -->
    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th><i class="fas fa-id-card"></i> Matricule</th>
                            <th><i class="fas fa-user"></i> Nom</th>
                            <th><i class="fas fa-book"></i> Formation</th>
                            <th><i class="fas fa-birthday-cake"></i> Âge</th>
                            <th><i class="fas fa-phone"></i> Téléphone</th>
                            <th><i class="fas fa-info-circle"></i> Statut</th>
                            <th><i class="fas fa-cog"></i> Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $index => $student): ?>
                        <tr>
                            <td><?= $index + 1 + $offset ?></td>
                            <td><?= htmlspecialchars($student['matricule']) ?></td>
                            <td><?= htmlspecialchars($student['full_name']) ?></td>
                            <td><?= htmlspecialchars($student['formation_name']) ?></td>
                            <td><?= $student['age'] ?></td>
                            <td><?= htmlspecialchars($student['phone'] ?? 'N/A') ?></td>
                            <td>
                                <span class="badge bg-<?= 
                                    $student['status'] === 'inscrit' ? 'success' : 
                                    ($student['status'] === 'preinscrit' ? 'warning' : 'secondary')
                                ?>">
                                    <?= $student['status'] === 'inscrit' ? 'Inscrit' : 
                                       ($student['status'] === 'preinscrit' ? 'Préinscrit' : $student['status']) ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary edit-student" 
                                        data-id="<?= $student['id'] ?>"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editStudentModal">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php if ($_SESSION['role'] === 'super_admin'): ?>
                                <button class="btn btn-sm btn-outline-danger delete-student" 
                                        data-id="<?= $student['id'] ?>">
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

<!-- Modale Ajout Étudiant -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Ajouter un Étudiant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addStudentForm">
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
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" name="date_of_birth" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                <select class="form-select" name="gender" required>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-book"></i></span>
                                <select class="form-select" name="formation_id" required>
                                    <?php foreach ($formations as $formation): ?>
                                        <option value="<?= $formation['id'] ?>"><?= htmlspecialchars($formation['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" name="phone" placeholder="Téléphone">
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

<!-- Modale Modification Étudiant -->
<div class="modal fade" id="editStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Modifier Étudiant</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editStudentForm">
                <input type="hidden" name="id" id="editStudentId">
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
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control" id="editDateOfBirth" name="date_of_birth" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-venus-mars"></i></span>
                                <select class="form-select" id="editGender" name="gender" required>
                                    <option value="M">Masculin</option>
                                    <option value="F">Féminin</option>
                                    <option value="Autre">Autre</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-book"></i></span>
                                <select class="form-select" id="editFormationId" name="formation_id" required>
                                    <?php foreach ($formations as $formation): ?>
                                        <option value="<?= $formation['id'] ?>"><?= htmlspecialchars($formation['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                <input type="tel" class="form-control" id="editPhone" name="phone" placeholder="Téléphone">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                                <select class="form-select" id="editStatus" name="status" required>
                                    <option value="preinscrit">Préinscrit</option>
                                    <option value="inscrit">Inscrit</option>
                                    <option value="formation">En formation</option>
                                    <option value="soutenance">En soutenance</option>
                                    <option value="diplome">Diplômé</option>
                                    <option value="abandon">Abandon</option>
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

    // Ajout d'un étudiant
    $('#addStudentForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'api/students.php',
            method: 'POST',
            data: formData,
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
    $('.edit-student').click(function() {
        const studentId = $(this).data('id');
        
        $.ajax({
            url: 'api/students.php?id=' + studentId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                if(response.status === 200) {
                    const student = response.data;
                    $('#editStudentId').val(student.id);
                    $('#editFirstName').val(student.first_name);
                    $('#editLastName').val(student.last_name);
                    $('#editDateOfBirth').val(student.date_of_birth);
                    $('#editGender').val(student.gender);
                    $('#editFormationId').val(student.formation_id);
                    $('#editEmail').val(student.email);
                    $('#editPhone').val(student.phone);
                    $('#editStatus').val(student.status);
                } else {
                    alert(response.error || 'Erreur lors du chargement');
                }
            },
            error: function() {
                alert('Erreur serveur');
            }
        });
    });

    // Mise à jour d'un étudiant
    $('#editStudentForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.ajax({
            url: 'api/students.php',
            method: 'PUT',
            data: formData,
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

    // Suppression d'un étudiant
    $('.delete-student').click(function() {
        if (confirm('Voulez-vous vraiment supprimer cet étudiant ?')) {
            const studentId = $(this).data('id');
            
            $.ajax({
                url: 'api/students.php',
                method: 'DELETE',
                data: { id: studentId },
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
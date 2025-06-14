<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérifier les permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Seuls les admins et super admins peuvent accéder
if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = "Inscription Principale - CFP-CMD";
include 'includes/header2.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // 1. Créer l'utilisateur
        $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, password, phone, address, photo) 
                              VALUES ((SELECT id FROM roles WHERE name = 'etudiant'), ?, ?, ?, ?, ?, ?, ?)");
        
        $password = password_hash('default123', PASSWORD_DEFAULT);
        $photo = $_FILES['photo']['name'] ?: 'default.png';
        
        $stmt->execute([
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $password,
            $_POST['phone'],
            $_POST['address'],
            $photo
        ]);
        
        $userId = $pdo->lastInsertId();

        // 2. Gérer l'upload de la photo
        if ($_FILES['photo']['name']) {
            $targetDir = "assets/images/users/";
            $targetFile = $targetDir . basename($_FILES['photo']['name']);
            move_uploaded_file($_FILES['photo']['tmp_name'], $targetFile);
        }

        // 3. Générer le matricule (année + id)
        $matricule = date('Y') . str_pad($userId, 4, '0', STR_PAD_LEFT);

        // 4. Créer l'étudiant
        $stmt = $pdo->prepare("INSERT INTO students (user_id, matricule, date_of_birth, gender, cin, photo, niveau_scolaire, formation_id, status) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'preinscrit')");
        
        $stmt->execute([
            $userId,
            $matricule,
            $_POST['date_of_birth'],
            $_POST['gender'],
            $_POST['cin'],
            $photo,
            $_POST['niveau_scolaire'],
            $_POST['formation_id']
        ]);

        $studentId = $pdo->lastInsertId();

        // 5. Créer l'enregistrement de pension
        $formation = $pdo->query("SELECT price FROM formations WHERE id = {$_POST['formation_id']}")->fetch();
        
        $stmt = $pdo->prepare("INSERT INTO pensions (student_id, formation_id, total_amount) VALUES (?, ?, ?)");
        $stmt->execute([$studentId, $_POST['formation_id'], $formation['price']]);

        $pdo->commit();
        
        $success = "Étudiant inscrit avec succès! Matricule: $matricule";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'inscription: " . $e->getMessage();
        logEvent("Erreur inscription: " . $e->getMessage());
    }
}

// Récupérer les formations pour le select
$formations = $pdo->query("SELECT f.id, f.name, fi.name AS filiere, ft.name AS type 
                          FROM formations f 
                          JOIN filieres fi ON f.filiere_id = fi.id 
                          JOIN formation_types ft ON f.type_id = ft.id
                          WHERE f.is_active = TRUE")->fetchAll();
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-user-graduate"></i> Inscription Principale</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Nouvel Étudiant</h2>
        </div>
        <div class="card-body">
            <form id="registrationForm" method="POST" enctype="multipart/form-data">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name"><i class="fas fa-user"></i> Prénom</label>
                        <input type="text" id="first_name" name="first_name" required placeholder="Prénom de l'étudiant">
                    </div>
                    <div class="form-group">
                        <label for="last_name"><i class="fas fa-user"></i> Nom</label>
                        <input type="text" id="last_name" name="last_name" required placeholder="Nom de l'étudiant">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email</label>
                        <input type="email" id="email" name="email" required placeholder="Email de l'étudiant">
                    </div>
                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Téléphone</label>
                        <input type="tel" id="phone" name="phone" placeholder="Numéro de téléphone">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address"><i class="fas fa-map-marker-alt"></i> Adresse</label>
                    <textarea id="address" name="address" rows="2" placeholder="Adresse complète"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="date_of_birth"><i class="fas fa-birthday-cake"></i> Date de naissance</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" required>
                    </div>
                    <div class="form-group">
                        <label for="gender"><i class="fas fa-venus-mars"></i> Genre</label>
                        <select id="gender" name="gender" required>
                            <option value="M">Masculin</option>
                            <option value="F">Féminin</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="cin"><i class="fas fa-id-card"></i> CIN/Passport</label>
                        <input type="text" id="cin" name="cin" placeholder="Numéro CIN ou passport">
                    </div>
                    <div class="form-group">
                        <label for="niveau_scolaire"><i class="fas fa-graduation-cap"></i> Niveau scolaire</label>
                        <input type="text" id="niveau_scolaire" name="niveau_scolaire" placeholder="Dernier niveau atteint">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="formation_id"><i class="fas fa-book"></i> Formation</label>
                        <select id="formation_id" name="formation_id" required>
                            <option value="">Sélectionnez une formation</option>
                            <?php foreach ($formations as $formation): ?>
                                <option value="<?= $formation['id'] ?>">
                                    <?= htmlspecialchars($formation['name']) ?> (<?= $formation['filiere'] ?> - <?= $formation['type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="photo"><i class="fas fa-camera"></i> Photo</label>
                        <input type="file" id="photo" name="photo" accept="image/*">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="reset" class="btn btn-outline">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Liste des étudiants préinscrits -->
    <div class="card mt-4">
        <div class="card-header">
            <h2>Liste des Préinscriptions</h2>
            <div class="search-box">
                <input type="text" id="searchInput" placeholder="Rechercher...">
                <button class="btn btn-sm btn-outline" onclick="printTable()"><i class="fas fa-print"></i> Imprimer</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="studentsTable" class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Matricule</th>
                            <th>Nom Complet</th>
                            <th>Formation</th>
                            <th>Téléphone</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                        $limit = 10;
                        $offset = ($page - 1) * $limit;
                        
                        $query = "SELECT s.id, s.matricule, CONCAT(u.first_name, ' ', u.last_name) AS full_name, 
                                 f.name AS formation, u.phone, s.status 
                                 FROM students s 
                                 JOIN users u ON s.user_id = u.id 
                                 JOIN formations f ON s.formation_id = f.id 
                                 WHERE s.status = 'preinscrit' 
                                 ORDER BY s.id DESC 
                                 LIMIT $limit OFFSET $offset";
                        
                        $students = $pdo->query($query)->fetchAll();
                        
                        $countQuery = "SELECT COUNT(*) FROM students WHERE status = 'preinscrit'";
                        $total = $pdo->query($countQuery)->fetchColumn();
                        $pages = ceil($total / $limit);
                        
                        foreach ($students as $index => $student):
                        ?>
                            <tr>
                                <td><?= $offset + $index + 1 ?></td>
                                <td><?= htmlspecialchars($student['matricule']) ?></td>
                                <td><?= htmlspecialchars($student['full_name']) ?></td>
                                <td><?= htmlspecialchars($student['formation']) ?></td>
                                <td><?= htmlspecialchars($student['phone']) ?></td>
                                <td><span class="badge badge-warning">Préinscrit</span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="viewStudent(<?= $student['id'] ?>)">
                                        <i class="fas fa-eye"></i> Voir
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="validateRegistration(<?= $student['id'] ?>)">
                                        <i class="fas fa-check"></i> Valider
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="page-link">&laquo; Précédent</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <a href="?page=<?= $i ?>" class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $pages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="page-link">Suivant &raquo;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal pour voir les détails -->
<div id="studentModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('studentModal')">&times;</span>
        <div id="studentDetails"></div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>

<script>
// Fonctions pour la gestion des étudiants
function viewStudent(id) {
    fetch(`api/get_student.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            let html = `
                <h2><i class="fas fa-user-graduate"></i> Détails de l'étudiant</h2>
                <div class="student-profile">
                    <div class="profile-header">
                        <img src="assets/images/users/${data.photo}" alt="Photo" class="profile-photo">
                        <div class="profile-info">
                            <h3>${data.full_name}</h3>
                            <p>Matricule: ${data.matricule}</p>
                            <p>${data.formation} (${data.filiere})</p>
                        </div>
                    </div>
                    
                    <div class="profile-details">
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-birthday-cake"></i> Date de naissance:</span>
                            <span class="detail-value">${data.date_of_birth} (${data.age} ans)</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-venus-mars"></i> Genre:</span>
                            <span class="detail-value">${data.gender === 'M' ? 'Masculin' : data.gender === 'F' ? 'Féminin' : 'Autre'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-id-card"></i> CIN/Passport:</span>
                            <span class="detail-value">${data.cin || 'Non renseigné'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-envelope"></i> Email:</span>
                            <span class="detail-value">${data.email}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-phone"></i> Téléphone:</span>
                            <span class="detail-value">${data.phone || 'Non renseigné'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-map-marker-alt"></i> Adresse:</span>
                            <span class="detail-value">${data.address || 'Non renseignée'}</span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-graduation-cap"></i> Niveau scolaire:</span>
                            <span class="detail-value">${data.niveau_scolaire || 'Non renseigné'}</span>
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('studentDetails').innerHTML = html;
            openModal('studentModal');
        });
}

function validateRegistration(id) {
    if (confirm("Voulez-vous vraiment valider cette inscription? L'étudiant passera au statut 'inscrit'.")) {
        fetch(`api/validate_registration.php?id=${id}`, { method: 'POST' })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert("Inscription validée avec succès!");
                    location.reload();
                } else {
                    alert("Erreur: " + data.message);
                }
            });
    }
}

function printTable() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>Liste des Préinscriptions - CFP-CMD</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #0c6fb5; text-align: center; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .logo { text-align: center; margin-bottom: 20px; }
                    .date { text-align: right; margin-bottom: 20px; }
                </style>
            </head>
            <body>
                <div class="logo">
                    <img src="assets/images/logo-cfp-cmd.png" alt="Logo" height="80">
                    <h1>Liste des Préinscriptions</h1>
                </div>
                <div class="date">
                    Généré le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}
                </div>
                ${document.getElementById('studentsTable').outerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Recherche dans le tableau
document.getElementById('searchInput').addEventListener('keyup', function() {
    const input = this.value.toLowerCase();
    const rows = document.querySelectorAll('#studentsTable tbody tr');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(input) ? '' : 'none';
    });
});
</script>

</body>
</html>
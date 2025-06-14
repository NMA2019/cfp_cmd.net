<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Inscription Étudiant - CFP-CMD";
include_once 'includes/header2.php';

// Initialisation des variables
$error = null;
$success = null;
$formations = [];

// Récupérer les formations disponibles
try {
    $stmt = $pdo->prepare("
        SELECT f.id, f.name, ft.name AS type, fi.name AS filiere, f.price, fi.code AS filiere_code
        FROM formations f
        JOIN formation_types ft ON f.type_id = ft.id
        JOIN filieres fi ON f.filiere_id = fi.id
        WHERE f.is_active = TRUE
        ORDER BY fi.name, f.name
    ");
    $stmt->execute();
    $formations = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des formations: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $required = ['first_name', 'last_name', 'email', 'date_of_birth', 'formation_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ " . htmlspecialchars($field) . " est obligatoire");
            }
        }

        // Vérifier l'email
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide");
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé");
        }

        // Vérifier la formation
        $formation_id = (int)$_POST['formation_id'];
        $stmt = $pdo->prepare("SELECT id, price FROM formations WHERE id = ? AND is_active = TRUE");
        $stmt->execute([$formation_id]);
        $formation = $stmt->fetch();
        
        if (!$formation) {
            throw new Exception("Formation invalide ou inactive");
        }

        // Générer un mot de passe temporaire
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Commencer la transaction
        $pdo->beginTransaction();

        // Créer l'utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO users (role_id, first_name, last_name, email, password, phone, address, photo)
            VALUES (
                (SELECT id FROM roles WHERE name = 'etudiant'),
                ?, ?, ?, ?, ?, ?, ?
            )
        ");
        
        // Gestion de la photo
        $photo = 'default.png';
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/students/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExt = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $photo = uniqid('student_') . '.' . $fileExt;
            $uploadPath = $uploadDir . $photo;
            
            if (!move_uploaded_file($_FILES['photo']['tmp_name'], $uploadPath)) {
                throw new Exception("Erreur lors du téléchargement de la photo");
            }
        }

        $stmt->execute([
            htmlspecialchars($_POST['first_name']),
            htmlspecialchars($_POST['last_name']),
            $email,
            $hashed_password,
            !empty($_POST['phone']) ? htmlspecialchars($_POST['phone']) : null,
            !empty($_POST['address']) ? htmlspecialchars($_POST['address']) : null,
            $photo
        ]);
        $user_id = $pdo->lastInsertId();

        // Trouver le code de la filière pour le matricule
        $filiere_code = null;
        foreach ($formations as $f) {
            if ($f['id'] == $formation_id) {
                $filiere_code = $f['filiere_code'];
                break;
            }
        }

        // Générer le matricule (ANNEE-FILIERE-ID)
        $matricule = date('Y') . '-' . $filiere_code . '-' . $user_id;

        // Créer l'étudiant
        $stmt = $pdo->prepare("
            INSERT INTO students (user_id, matricule, date_of_birth, gender, cin, niveau_scolaire, formation_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'preinscrit')
        ");
        $stmt->execute([
            $user_id,
            $matricule,
            htmlspecialchars($_POST['date_of_birth']),
            !empty($_POST['gender']) ? htmlspecialchars($_POST['gender']) : null,
            !empty($_POST['cin']) ? htmlspecialchars($_POST['cin']) : null,
            !empty($_POST['niveau_scolaire']) ? htmlspecialchars($_POST['niveau_scolaire']) : null,
            $formation_id
        ]);
        $student_id = $pdo->lastInsertId();

        // Créer la pension
        $stmt = $pdo->prepare("
            INSERT INTO pensions (student_id, formation_id, total_amount, paid_amount, status)
            VALUES (?, ?, ?, 0, 'non_paye')
        ");
        $stmt->execute([$student_id, $formation_id, $formation['price']]);

        // Valider la transaction
        $pdo->commit();

        // Envoyer l'email de bienvenue (simulé ici)
        $success = "Étudiant inscrit avec succès! Matricule: " . htmlspecialchars($matricule) . " - Mot de passe temporaire: " . htmlspecialchars($temp_password);
        logEvent("Nouvel étudiant inscrit: $matricule");

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<main class="registration-container">
    <h1><i class="fas fa-user-graduate"></i> Inscription d'un Nouvel Étudiant</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="registration-form" enctype="multipart/form-data">
        <div class="form-section">
            <h2><i class="fas fa-id-card"></i> Informations Personnelles</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Prénom *</label>
                    <input type="text" id="first_name" name="first_name" required 
                           value="<?= isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Nom *</label>
                    <input type="text" id="last_name" name="last_name" required 
                           value="<?= isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="date_of_birth">Date de naissance *</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" required 
                           value="<?= isset($_POST['date_of_birth']) ? htmlspecialchars($_POST['date_of_birth']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="gender">Genre</label>
                    <select id="gender" name="gender">
                        <option value="">-- Sélectionner --</option>
                        <option value="M" <?= isset($_POST['gender']) && $_POST['gender'] === 'M' ? 'selected' : '' ?>>Masculin</option>
                        <option value="F" <?= isset($_POST['gender']) && $_POST['gender'] === 'F' ? 'selected' : '' ?>>Féminin</option>
                        <option value="Autre" <?= isset($_POST['gender']) && $_POST['gender'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="cin">CIN/Passport</label>
                    <input type="text" id="cin" name="cin" 
                           value="<?= isset($_POST['cin']) ? htmlspecialchars($_POST['cin']) : '' ?>">
                </div>
                <div class="form-group">
                    <label for="niveau_scolaire">Niveau scolaire</label>
                    <input type="text" id="niveau_scolaire" name="niveau_scolaire" 
                           value="<?= isset($_POST['niveau_scolaire']) ? htmlspecialchars($_POST['niveau_scolaire']) : '' ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Adresse</label>
                <textarea id="address" name="address" rows="2"><?= isset($_POST['address']) ? htmlspecialchars($_POST['address']) : '' ?></textarea>
            </div>
        </div>

        <div class="form-section">
            <h2><i class="fas fa-book"></i> Formation</h2>
            
            <div class="form-group">
                <label for="formation_id">Formation *</label>
                <select id="formation_id" name="formation_id" required>
                    <option value="">-- Sélectionner une formation --</option>
                    <?php foreach ($formations as $formation): ?>
                        <option value="<?= $formation['id'] ?>" 
                            <?= isset($_POST['formation_id']) && $_POST['formation_id'] == $formation['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($formation['filiere'] . ' - ' . $formation['name'] . ' (' . $formation['type'] . ') - ' . number_format($formation['price'], 0, ',', ' ') . ' MGA') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="photo">Photo (optionnel)</label>
                <input type="file" id="photo" name="photo" accept="image/jpeg,image/png">
                <small class="form-text">Formats acceptés: JPEG, PNG (max 2MB)</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Enregistrer l'inscription
            </button>
            <button type="reset" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Réinitialiser
            </button>
            <a href="students_list.php" class="btn btn-outline">
                <i class="fas fa-list"></i> Voir la liste
            </a>
        </div>
    </form>
</main>

<style>
.registration-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.registration-form {
    background-color: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 2px 15px rgba(0,0,0,0.1);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #eee;
}

.form-section:last-child {
    border-bottom: none;
}

.form-section h2 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-group {
    flex: 1;
    margin-bottom: 10px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="tel"],
.form-group input[type="date"],
.form-group input[type="file"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    transition: border-color 0.3s;
}

.form-group input[type="file"] {
    padding: 8px 0;
}

.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 2px rgba(12, 111, 181, 0.2);
}

.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

.form-actions {
    margin-top: 30px;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.form-text {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: #666;
}

@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .form-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .form-actions .btn {
        width: 100%;
        margin-bottom: 10px;
    }
}
</style>

<?php include_once 'includes/footer.php'; ?>
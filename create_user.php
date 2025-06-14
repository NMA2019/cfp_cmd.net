<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Création d'Utilisateur - CFP-CMD";
include_once 'includes/header2.php';

// Récupérer les rôles disponibles
try {
    $roles = $pdo->query("SELECT id, name, description FROM roles ORDER BY name")->fetchAll();
    
    // Pour l'auto-complétion des formations (si étudiant)
    $formations = $pdo->query("SELECT id, name FROM formations WHERE is_active = TRUE ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des données
        $required = ['first_name', 'last_name', 'email', 'role_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        // Vérifier l'email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide");
        }

        // Vérifier si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        if ($stmt->fetch()) {
            throw new Exception("Cet email est déjà utilisé");
        }

        // Vérifier le rôle
        $valid_role = false;
        foreach ($roles as $role) {
            if ($role['id'] == $_POST['role_id']) {
                $valid_role = true;
                $role_name = $role['name'];
                break;
            }
        }
        if (!$valid_role) {
            throw new Exception("Rôle invalide");
        }

        // Gestion du mot de passe
        $password = $_POST['password'] ?? bin2hex(random_bytes(4)); // Génère un mot de passe aléatoire si non fourni
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Commencer la transaction
        $pdo->beginTransaction();

        // Créer l'utilisateur
        $stmt = $pdo->prepare("
            INSERT INTO users (role_id, first_name, last_name, email, password, phone, address, photo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $_POST['role_id'],
            $_POST['first_name'],
            $_POST['last_name'],
            $_POST['email'],
            $hashed_password,
            $_POST['phone'] ?? null,
            $_POST['address'] ?? null,
            'default.png'
        ]);
        $user_id = $pdo->lastInsertId();

        // Si c'est un étudiant, créer aussi le profil étudiant
if ($role_name === 'etudiant' && !empty($_POST['formation_id'])) {
    // Vérifier la formation
    $stmt = $pdo->prepare("SELECT id FROM formations WHERE id = ? AND is_active = TRUE");
    $stmt->execute([$_POST['formation_id']]);
    if (!$stmt->fetch()) {
        throw new Exception("Formation invalide");
    }

    // Récupérer le code filière
    $stmt = $pdo->prepare("
        SELECT fi.code 
        FROM formations f
        JOIN filieres fi ON f.filiere_id = fi.id
        WHERE f.id = ?
    ");
    $stmt->execute([$_POST['formation_id']]);
    $filiere_code = $stmt->fetchColumn();
    
    if (!$filiere_code) {
        throw new Exception("Impossible de déterminer le code filière");
    }

    $matricule = date('Y') . '-' . $filiere_code . '-' . $user_id;

    // Créer l'étudiant
    $stmt = $pdo->prepare("
        INSERT INTO students (user_id, matricule, date_of_birth, gender, cin, niveau_scolaire, formation_id, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'preinscrit')
    ");
    $stmt->execute([
        $user_id,
        $matricule,
        $_POST['date_of_birth'] ?? null,
        $_POST['gender'] ?? null,
        $_POST['cin'] ?? null,
        $_POST['niveau_scolaire'] ?? null,
        $_POST['formation_id']
    ]);
    $student_id = $pdo->lastInsertId(); // Get the actual student ID

    // Récupérer le prix de la formation
    $stmt = $pdo->prepare("SELECT price FROM formations WHERE id = ?");
    $stmt->execute([$_POST['formation_id']]);
    $formation_price = $stmt->fetchColumn();

    if ($formation_price === false) {
        throw new Exception("Impossible de déterminer le prix de la formation");
    }

    // Créer la pension avec le bon student_id
    $stmt = $pdo->prepare("
        INSERT INTO pensions (student_id, formation_id, total_amount, paid_amount, status)
        VALUES (?, ?, ?, 0, 'non_paye')
    ");
    $stmt->execute([$student_id, $_POST['formation_id'], $formation_price]); // Use $student_id instead of $user_id
}

        // Si c'est un membre du personnel
        if ($role_name === 'professeur' || $role_name === 'admin') {
            $stmt = $pdo->prepare("
                INSERT INTO staff (user_id, type, qualification, hire_date, salary, status)
                VALUES (?, ?, ?, CURDATE(), ?, 'actif')
            ");
            $staff_type = $role_name === 'professeur' ? 'enseignant' : 'administratif';
            $stmt->execute([
                $user_id,
                $staff_type,
                $_POST['qualification'] ?? null,
                $_POST['salary'] ?? null
            ]);
        }

        // Valider la transaction
        $pdo->commit();

        // Préparer le message de succès
        $success_message = "Utilisateur créé avec succès!";
        if (empty($_POST['password'])) {
            $success_message .= " Mot de passe généré: $password";
        }

        $success = $success_message;
        logEvent("Nouvel utilisateur créé: " . $_POST['email'] . " (Rôle: $role_name)");

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>

<main class="user-create-container">
    <h1><i class="fas fa-user-plus"></i> Créer un Nouvel Utilisateur</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="user-form" id="userForm">
        <div class="form-section">
            <h2><i class="fas fa-id-card"></i> Informations de Base</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">Prénom *</label>
                    <input type="text" id="first_name" name="first_name" required 
                           value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Nom *</label>
                    <input type="text" id="last_name" name="last_name" required 
                           value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="email">Email *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="phone">Téléphone</label>
                    <input type="tel" id="phone" name="phone" 
                           value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="password-input">
                        <input type="password" id="password" name="password" 
                               placeholder="Laisser vide pour générer automatiquement">
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button type="button" class="generate-password" onclick="generatePassword()">
                            <i class="fas fa-random"></i> Générer
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="role_id">Rôle *</label>
                    <select id="role_id" name="role_id" required onchange="updateForm()">
                        <option value="">-- Sélectionner un rôle --</option>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?= htmlspecialchars($role['id']) ?>" 
                                <?= isset($_POST['role_id']) && $_POST['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(ucfirst($role['name'])) ?> - <?= htmlspecialchars($role['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="address">Adresse</label>
                <textarea id="address" name="address" rows="2"><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Section Étudiant (visible seulement si rôle=étudiant) -->
        <div class="form-section" id="studentSection" style="display: none;">
            <h2><i class="fas fa-graduation-cap"></i> Informations Étudiant</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="formation_id">Formation *</label>
                    <select id="formation_id" name="formation_id">
                        <option value="">-- Sélectionner une formation --</option>
                        <?php foreach ($formations as $formation): ?>
                            <option value="<?= htmlspecialchars($formation['id']) ?>" 
                                <?= isset($_POST['formation_id']) && $_POST['formation_id'] == $formation['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($formation['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="date_of_birth">Date de naissance</label>
                    <input type="date" id="date_of_birth" name="date_of_birth" 
                           value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="gender">Genre</label>
                    <select id="gender" name="gender">
                        <option value="">-- Sélectionner --</option>
                        <option value="M" <?= isset($_POST['gender']) && $_POST['gender'] === 'M' ? 'selected' : '' ?>>Masculin</option>
                        <option value="F" <?= isset($_POST['gender']) && $_POST['gender'] === 'F' ? 'selected' : '' ?>>Féminin</option>
                        <option value="Autre" <?= isset($_POST['gender']) && $_POST['gender'] === 'Autre' ? 'selected' : '' ?>>Autre</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="niveau_scolaire">Niveau scolaire</label>
                    <input type="text" id="niveau_scolaire" name="niveau_scolaire" 
                           value="<?= htmlspecialchars($_POST['niveau_scolaire'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-group">
                <label for="cin">CIN/Passport</label>
                <input type="text" id="cin" name="cin" value="<?= htmlspecialchars($_POST['cin'] ?? '') ?>">
            </div>
        </div>

        <!-- Section Personnel (visible pour admin/professeur) -->
        <div class="form-section" id="staffSection" style="display: none;">
            <h2><i class="fas fa-briefcase"></i> Informations Professionnelles</h2>
            <div class="form-row">
                <div class="form-group">
                    <label for="qualification">Qualification</label>
                    <input type="text" id="qualification" name="qualification" 
                           value="<?= htmlspecialchars($_POST['qualification'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="salary">Salaire (CFA)</label>
                    <input type="number" id="salary" name="salary" step="0.01" 
                           value="<?= htmlspecialchars($_POST['salary'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Créer l'utilisateur
            </button>
            <button type="reset" class="btn btn-secondary">
                <i class="fas fa-undo"></i> Réinitialiser
            </button>
        </div>
    </form>
</main>

<script>
// Affiche/masque les sections en fonction du rôle sélectionné
function updateForm() {
    const roleSelect = document.getElementById('role_id');
    const selectedRole = roleSelect.options[roleSelect.selectedIndex].text.toLowerCase();
    
    // Masquer toutes les sections d'abord
    document.getElementById('studentSection').style.display = 'none';
    document.getElementById('staffSection').style.display = 'none';
    
    // Afficher la section appropriée
    if (selectedRole.includes('étudiant') || selectedRole.includes('etudiant')) {
        document.getElementById('studentSection').style.display = 'block';
        document.getElementById('formation_id').required = true;
    } else if (selectedRole.includes('professeur') || selectedRole.includes('admin')) {
        document.getElementById('staffSection').style.display = 'block';
    }
}

// Génère un mot de passe aléatoire
function generatePassword() {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*';
    let password = '';
    for (let i = 0; i < 12; i++) {
        password += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    document.getElementById('password').value = password;
}

// Affiche/masque le mot de passe
function togglePassword() {
    const passwordInput = document.getElementById('password');
    const toggleBtn = document.querySelector('.toggle-password i');
    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleBtn.classList.replace('fa-eye-slash', 'fa-eye');
    }
}

// Initialiser le formulaire au chargement
document.addEventListener('DOMContentLoaded', function() {
    updateForm();
});
</script>

<style>
/* Main container */
.user-create-container {
    max-width: 1000px;
    margin: 20px auto;
    padding: 20px;
    background-color: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
}

/* Page title */
.user-create-container h1 {
    color: #2c3e50;
    margin-bottom: 25px;
    padding-bottom: 10px;
    border-bottom: 2px solid #eaeaea;
    display: flex;
    align-items: center;
    gap: 10px;
}

/* Alert messages */
.alert {
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 0.95rem;
}
.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}
.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

/* Form styling */
.user-form {
    background-color: white;
    padding: 25px;
    border-radius: 6px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid #f0f0f0;
}
.form-section:last-child {
    border-bottom: none;
}
.form-section h2 {
    color: #3498db;
    margin-bottom: 20px;
    font-size: 1.3rem;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Form rows and groups */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}
.form-group {
    flex: 1;
    min-width: 250px;
}
.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #555;
}
.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.95rem;
    transition: border-color 0.3s;
}
.form-group input:focus,
.form-group select:focus,
.form-group textarea:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}
.form-group textarea {
    min-height: 80px;
    resize: vertical;
}

/* Password input special styling */
.password-input {
    position: relative;
    display: flex;
    align-items: center;
}
.password-input input {
    padding-right: 80px;
}
.toggle-password {
    position: absolute;
    right: 90px;
    background: none;
    border: none;
    cursor: pointer;
    padding: 10px;
    color: #7f8c8d;
}
.toggle-password:hover {
    color: #3498db;
}
.generate-password {
    position: absolute;
    right: 0;
    background: #f0f0f0;
    border: 1px solid #ddd;
    border-radius: 0 4px 4px 0;
    cursor: pointer;
    padding: 10px 12px;
    font-size: 0.85rem;
    color: #34495e;
    transition: all 0.2s;
}
.generate-password:hover {
    background: #e0e0e0;
}

/* Form actions */
.form-actions {
    margin-top: 30px;
    text-align: right;
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}
.btn {
    padding: 10px 20px;
    border-radius: 4px;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}
.btn-primary {
    background-color: #3498db;
    color: white;
    border: 1px solid #2980b9;
}
.btn-primary:hover {
    background-color: #2980b9;
}
.btn-secondary {
    background-color: #f8f9fa;
    color: #333;
    border: 1px solid #ddd;
}
.btn-secondary:hover {
    background-color: #e9ecef;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 15px;
    }
    .form-group {
        min-width: 100%;
    }
    .user-form {
        padding: 15px;
    }
}

/* Animation for section transitions */
#studentSection,
#staffSection {
    transition: all 0.3s ease;
    overflow: hidden;
}

/* Required field indicators */
label[required]:after {
    content: " *";
    color: #e74c3c;
}
</style>

<?php include_once 'includes/footer.php'; ?>
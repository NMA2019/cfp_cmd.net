<?php
require_once 'config/db_connection.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Récupérer les rôles depuis la base de données
try {
    $roles = $pdo->query("SELECT id, name FROM roles WHERE name != 'super_admin'")->fetchAll();
} catch (PDOException $e) {
    die("Erreur de connexion : " . $e->getMessage());
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstName = htmlspecialchars(trim($_POST['first_name']));
    $lastName = htmlspecialchars(trim($_POST['last_name']));
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone']));
    $address = htmlspecialchars(trim($_POST['address']));
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    $roleId = (int)$_POST['role_id'];
    $termsAccepted = isset($_POST['terms']);

    // Traitement de la photo
    $photo = 'default.png';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/profiles/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array(strtolower($extension), $allowedExtensions)) {
            $filename = uniqid() . '.' . $extension;
            $destination = $uploadDir . $filename;
            
            if (move_uploaded_file($_FILES['photo']['tmp_name'], $destination)) {
                $photo = 'profiles/' . $filename;
            } else {
                $error = "Erreur lors du téléchargement de la photo";
            }
        } else {
            $error = "Format de fichier non supporté. Utilisez JPG, PNG ou GIF";
        }
    }

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        $error = "Tous les champs sont obligatoires";
    } elseif (!$termsAccepted) {
        $error = "Vous devez accepter les termes d'utilisation";
    } elseif ($password !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas";
    } elseif (strlen($password) < 8) {
        $error = "Le mot de passe doit contenir au moins 8 caractères";
    } else {
        try {
            // Vérifier si l'email existe déjà
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetch()) {
                $error = "Cet email est déjà utilisé";
            } else {
                // Hasher le mot de passe
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (role_id, first_name, last_name, email, phone, address, password, photo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$roleId, $firstName, $lastName, $email,$phone, $address, $hashedPassword, $photo]);
                
                $success = "Inscription réussie ! Vous pouvez maintenant vous connecter.";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de l'inscription : " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscription - CFP-CMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0c6fb5, #0cb5a9);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .register-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .register-header {
            background-color: #0c6fb5;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .register-body {
            padding: 2rem;
            background-color: white;
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
        }
        .btn-register {
            background-color: #0c6fb5;
            border: none;
            border-radius: 50px;
            padding: 12px;
            font-weight: 600;
        }
        .input-group-text {
            border-radius: 50px 0 0 50px !important;
        }
        .logo {
            width: 100px;
            margin-bottom: 20px;
        }
        .toggle-password {
            border-radius: 0 50px 50px 0 !important;
            cursor: pointer;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
        }
        .weak { background-color: #dc3545; width: 25%; }
        .medium { background-color: #ffc107; width: 50%; }
        .strong { background-color: #28a745; width: 75%; }
        .very-strong { background-color: #0c6fb5; width: 100%; }
        .photo-preview {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            display: none;
            margin: 0 auto 15px;
        }
        .terms-link {
            cursor: pointer;
            color: #0c6fb5;
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-10 col-lg-8">
                <div class="register-card">
                    <div class="register-header">
                        <img src="assets/images/logo-cfp-cmd.png" alt="Logo CFP-CMD" class="logo">
                        <h2>Créer un compte</h2>
                        <p class="mb-0">Rejoignez notre communauté</p>
                    </div>
                    <div class="register-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
                        <div class="text-center mt-3">
                            <a href="login.php" class="btn btn-primary">Se connecter</a>
                        </div>
                        <?php else: ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="last_name" placeholder="Nom" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user"></i></span>
                                        <input type="text" class="form-control" name="first_name" placeholder="Prénom" required>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" class="form-control" name="email" placeholder="Adresse email" required>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="phone" class="form-control" name="phone" placeholder="N° téléphone" required>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="mb-3">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-location"></i></span>
                                        <input type="text" class="form-control" name="address" placeholder="Adresse" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Type de compte</label>
                                    <select class="form-select" name="role_id" required>
                                        <?php foreach ($roles as $role): ?>
                                            <option value="<?= $role['id'] ?>">
                                                <?= ucfirst(str_replace('_', ' ', $role['name'])) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                            </div>

                            </div>
                            <div class="row">
                                    <div class="mb-3">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <div class="password-strength" id="passwordStrength"></div>
                                        <small class="text-muted">Minimum 8 caractères</small>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="Confirmer le mot de passe" required>
                                            <button class="btn btn-outline-secondary toggle-password" type="button">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Photo de profil</label>
                                <input type="file" class="form-control" name="photo" accept="image/*" id="photoInput">
                                <img src="#" alt="Preview" class="photo-preview" id="photoPreview">
                                <small class="text-muted">Formats acceptés: JPG, PNG, GIF (max 2MB)</small>
                            </div>
                            
                            <div class="mb-4 form-check">
                                <input type="checkbox" class="form-check-input" id="terms" name="terms" required>
                                <label class="form-check-label" for="terms">
                                    J'accepte les <a href="includes/termes.php" class="terms-link">conditions d'utilisation</a>
                                </label>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>S'inscrire
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-0">Vous avez déjà un compte ? <a href="login.php" class="text-decoration-none">Se connecter</a> | <a href="index.php" class="text-decoration-none">HOME</a></p>
                            </div>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Afficher/masquer le mot de passe
            document.querySelectorAll('.toggle-password').forEach(function(button) {
                button.addEventListener('click', function() {
                    const input = this.parentNode.querySelector('input');
                    const icon = this.querySelector('i');
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.classList.remove('fa-eye');
                        icon.classList.add('fa-eye-slash');
                    } else {
                        input.type = 'password';
                        icon.classList.remove('fa-eye-slash');
                        icon.classList.add('fa-eye');
                    }
                });
            });

            // Indicateur de force du mot de passe
            const passwordInput = document.getElementById('password');
            const passwordStrength = document.getElementById('passwordStrength');
            
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.match(/\d/)) strength++;
                if (password.match(/[a-z]/) && password.match(/[A-Z]/)) strength++;
                if (password.match(/[^a-zA-Z0-9]/)) strength++;
                
                passwordStrength.className = 'password-strength';
                if (password.length === 0) {
                    passwordStrength.style.width = '0';
                } else if (strength <= 1) {
                    passwordStrength.classList.add('weak');
                } else if (strength === 2) {
                    passwordStrength.classList.add('medium');
                } else if (strength === 3) {
                    passwordStrength.classList.add('strong');
                } else {
                    passwordStrength.classList.add('very-strong');
                }
            });

            // Prévisualisation de la photo
            const photoInput = document.getElementById('photoInput');
            const photoPreview = document.getElementById('photoPreview');
            
            photoInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        photoPreview.src = e.target.result;
                        photoPreview.style.display = 'block';
                    }
                    
                    reader.readAsDataURL(this.files[0]);
                }
            });
        });
    </script>
</body>
</html>
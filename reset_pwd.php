<?php
require_once 'config/db_connection.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;

// Étape 1: Demande de réinitialisation
if ($step === 1 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            // Générer un token (en production, utiliser une librairie sécurisée)
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Stocker le token en base
            $pdo->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE email = ?")
                ->execute([$token, $expires, $email]);
            
            // Envoyer l'email (simulé ici)
            $resetLink = "http://{$_SERVER['HTTP_HOST']}/reset_pwd.php?step=2&token=$token";
            
            $success = "Un email de réinitialisation a été envoyé. <small>(Lien simulé : $resetLink)</small>";
        } else {
            $error = "Aucun compte trouvé avec cet email";
        }
    } catch (PDOException $e) {
        $error = "Erreur lors de la demande de réinitialisation";
    }
}

// Étape 2: Réinitialisation du mot de passe
if ($step === 2 && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    if ($password !== $confirmPassword) {
        $error = "Les mots de passe ne correspondent pas";
    } else {
        try {
            // Vérifier le token
            $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->execute([$token]);
            $user = $stmt->fetch();
            
            if ($user) {
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?")
                    ->execute([$hashedPassword, $user['id']]);
                
                $success = "Votre mot de passe a été réinitialisé avec succès. <a href='login.php'>Se connecter</a>";
                $step = 3; // Afficher le succès
            } else {
                $error = "Lien invalide ou expiré";
            }
        } catch (PDOException $e) {
            $error = "Erreur lors de la réinitialisation";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation - CFP-CMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0c6fb5, #0cb5a9);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .reset-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .reset-header {
            background-color: #0c6fb5;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .reset-body {
            padding: 2rem;
            background-color: white;
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
        }
        .btn-reset {
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
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="reset-card">
                    <div class="reset-header">
                        <img src="assets/images/logo-cfp-cmd.png" alt="Logo CFP-CMD" class="logo">
                        <h2>Réinitialisation du mot de passe</h2>
                    </div>
                    <div class="reset-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                        <div class="alert alert-success"><?= $success ?></div>
                        <?php endif; ?>
                        
                        <?php if ($step === 1): ?>
                        <form method="POST" action="reset_pwd.php?step=1">
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" placeholder="Adresse email" required>
                                </div>
                                <small class="text-muted">Entrez l'email associé à votre compte</small>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-reset">
                                    <i class="fas fa-paper-plane me-2"></i>Envoyer le lien
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-0"><a href="login.php" class="text-decoration-none">Retour à la connexion</a></p>
                            </div>
                        </form>
                        
                        <?php elseif ($step === 2 && isset($_GET['token'])): ?>
                        <form method="POST">
                            <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token']) ?>">
                            
                            <div class="mb-3">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Nouveau mot de passe" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
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
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-reset">
                                    <i class="fas fa-sync-alt me-2"></i>Réinitialiser
                                </button>
                            </div>
                        </form>
                        
                        <?php elseif ($step === 3): ?>
                        <div class="text-center">
                            <p>Votre mot de passe a été réinitialisé avec succès.</p>
                            <a href="login.php" class="btn btn-primary">Se connecter</a>
                        </div>
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
        });
    </script>
</body>
</html>
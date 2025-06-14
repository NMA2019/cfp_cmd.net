<?php
require_once 'config/db_connection.php';

// Rediriger si déjà connecté
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

// Vérifier le cookie "remember me"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    
    try {
        $stmt = $pdo->prepare("SELECT user_id FROM remember_tokens WHERE token = ? AND expires > NOW()");
        $stmt->execute([$token]);
        $tokenData = $stmt->fetch();
        
        if ($tokenData) {
            $stmt = $pdo->prepare("SELECT id, role_id, first_name, last_name, photo FROM users WHERE id = ?");
            $stmt->execute([$tokenData['user_id']]);
            $user = $stmt->fetch();
            
            if ($user) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['role'] = $user['role_id'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['photo'] = $user['photo'];
                
                header('Location: dashboard.php');
                exit();
            }
        }
    } catch (PDOException $e) {
        // Loguer l'erreur mais ne pas bloquer l'utilisateur
        error_log("Remember me error: " . $e->getMessage());
    }
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    $rememberMe = isset($_POST['remember_me']);

    try {
        $stmt = $pdo->prepare("SELECT id, password, role_id, first_name, last_name, photo FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role_id'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['photo'] = $user['photo'];

            // Mettre à jour la dernière connexion
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            
            // Gestion du "Se souvenir de moi"
            if ($rememberMe) {
                $token = bin2hex(random_bytes(32));
                $expires = time() + 30 * 24 * 60 * 60; // 30 jours
                
                setcookie('remember_token', $token, $expires, '/', '', true, true);
                
                $pdo->prepare("INSERT INTO remember_tokens (user_id, token, expires) VALUES (?, ?, FROM_UNIXTIME(?))")
                    ->execute([$user['id'], $token, $expires]);
            }
            
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Email ou mot de passe incorrect";
        }
    } catch (PDOException $e) {
        $error = "Erreur de connexion : " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - CFP-CMD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0c6fb5, #0cb5a9);
            height: 100vh;
            display: flex;
            align-items: center;
        }
        .login-card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .login-header {
            background-color: #0c6fb5;
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .login-body {
            padding: 2rem;
            background-color: white;
        }
        .form-control {
            border-radius: 50px;
            padding: 12px 20px;
        }
        .btn-login {
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
        .form-check-input {
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="login-card">
                    <div class="login-header">
                        <img src="assets/images/logo-cfp-cmd.png" alt="Logo CFP-CMD" class="logo">
                        <h2>Connexion à votre compte</h2>
                        <p class="mb-0">Accédez à votre espace personnel</p>
                    </div>
                    <div class="login-body">
                        <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                    <input type="email" class="form-control" name="email" placeholder="Adresse email" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="password" name="password" placeholder="Mot de passe" required>
                                    <button class="btn btn-outline-secondary toggle-password" type="button">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="remember_me" name="remember_me">
                                        <label class="form-check-label" for="remember_me">Se souvenir de moi</label>
                                    </div>
                                    <a href="reset_pwd.php" class="text-decoration-none">Mot de passe oublié ?</a>
                                </div>
                            </div>
                            
                            <div class="d-grid mb-3">
                                <button type="submit" class="btn btn-primary btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                                </button>
                            </div>
                            
                            <div class="text-center">
                                <p class="mb-0">Vous n'avez pas de compte ? <a href="register.php" class="text-decoration-none">S'inscrire</a> | <a href="index.php" class="text-decoration-none">HOME</a></p>
                            </div>
                        </form>
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
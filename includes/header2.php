<?php
// Démarrer la session si ce n'est pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialiser les variables de session si elles n'existent pas
$_SESSION['role'] = $_SESSION['role'] ?? null;
$_SESSION['user_id'] = $_SESSION['user_id'] ?? null;
$_SESSION['username'] = $_SESSION['username'] ?? null;
$_SESSION['photo'] = $_SESSION['photo'] ?? 'default.png';

// Vérifier si l'utilisateur est connecté
$loggedIn = isset($_SESSION['user_id']);
$userRole = $_SESSION['role'];

// Chemin correct vers db_connection.php (adaptez-le à votre structure)
$dbPath = __DIR__ . '/../config/db_connection.php';
if (file_exists($dbPath)) {
    require_once $dbPath;

    // Récupérer les messages non lus si connecté
    if ($loggedIn && isset($pdo)) {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) AS unread FROM chat WHERE to_user = ? AND read_status = 0");
            $stmt->execute([$_SESSION['user_id']]);
            $unread = $stmt->fetch()['unread'];
        } catch (PDOException $e) {
            $unread = 0;
            error_log("Erreur récupération messages: " . $e->getMessage());
        }
    }
} else {
    die("Erreur: Fichier de configuration de la base de données introuvable");
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'CFP-CMD'; ?></title>

    <!-- Favicon -->
    <link rel="icon" href="assets/images/favicon.ico" type="image/x-icon">

    <!-- CSS Principal -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/header2.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/chat.css">
    <link rel="stylesheet" href="assets/css/inspens.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/footer.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>

    <!-- Slick Slider -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css" />
</head>

<body>
    <!-- Header Principal -->
    <header class="main-header">
        <div class="header-container">
            <!-- Logo et Titre -->
            <div class="logo-container">
                <a href="index.php">
                    <img src="assets/images/logo-cfp-cmd.png" alt="Logo CFP-CMD" class="logo">
                </a>
                <div class="titles">
                    <h1>Centre de Formation Professionnelle du Commerce et du Marketing Digital</h1>
                    <p class="slogan">« Une formation de qualité pour un emploi sûr! »</p>
                </div>
            </div>

            <!-- Navigation et Authentification -->
            <div class="nav-container">
                <!-- Menu Principal -->
                <nav class="main-nav">
                    <ul>
                        <li><a href="index.php"><i class="fas fa-home"></i> Accueil</a></li>
                        <li><a href="students.php"><i class="fas fa-info-circle"></i> Students</a></li>
                        <li><a href="Staff.php"><i class="fas fa-graduation-cap"></i> Staff</a></li>
                        <li><a href="Inscription.php"><i class="fas fa-envelope"></i> Inscription</a></li>
                        <li><a href="pension.php"><i class="fas fa-envelope"></i> Pension</a></li>
                        <?php if ($loggedIn): ?>
                            <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </nav>

                <!-- Boutons d'authentification -->
                <div class="auth-container">
                    <?php if ($loggedIn): ?>
                        <div class="user-dropdown">
                            <button class="user-btn">
                                <img src="assets/images/users/<?= $_SESSION['photo'] ?? 'default.png' ?>" alt="Photo profil" class="user-avatar">
                                <span><?= $_SESSION['username'] ?></span>
                                <i class="fas fa-caret-down"></i>
                            </button>
                            <div class="dropdown-content">
                                <a href="profile.php"><i class="fas fa-user"></i> Mon profil</a>
                                <a href="chat.php">
                                    <i class="fas fa-comments"></i> Live Chat
                                    <?php if ($unread > 0): ?>
                                        <span class="notification-badge"><?= $unread ?></span>
                                    <?php endif; ?>
                                </a>
                                <a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a>
                                <div class="dropdown-divider"></div>
                                <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="login.php" class="btn btn-outline"><i class="fas fa-sign-in-alt"></i> Connexion</a>
                        <a href="register.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Inscription</a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Menu Mobile -->
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>

        <!-- Menu Mobile -->
        <div class="mobile-menu">
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Accueil</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> À propos</a></li>
                <li><a href="formations.php"><i class="fas fa-graduation-cap"></i> Formations</a></li>
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact</a></li>
                <?php if ($loggedIn): ?>
                    <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li><a href="profile.php"><i class="fas fa-user"></i> Mon profil</a></li>
                    <li><a href="messages.php">
                            <i class="fas fa-envelope"></i> Messages
                            <?php if ($unread > 0): ?>
                                <span class="notification-badge"><?= $unread ?></span>
                            <?php endif; ?>
                        </a></li>
                    <li><a href="settings.php"><i class="fas fa-cog"></i> Paramètres</a></li>
                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
                <?php else: ?>
                    <li><a href="login.php"><i class="fas fa-sign-in-alt"></i> Connexion</a></li>
                    <li><a href="register.php"><i class="fas fa-user-plus"></i> Inscription</a></li>
                <?php endif; ?>
            </ul>
        </div>
    </header>

    <!-- Contenu Principal -->
    <main class="main-content"></main>
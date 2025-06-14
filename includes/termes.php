<bo?php
$pageTitle = "Termes d'utilisation";
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
    <link rel="stylesheet" href="assets/css/footer.css">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- Slick Slider -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css" />
</head>

<body>

</body>
<!-- Header Principal -->
<header class="main-header">
    <div class="header-container">
        <!-- Logo et Titre -->
        <div class="logo-container">
            <a href="../index.php">
                <img src="assets/images/logo-cfp-cmd.png" alt="Logo CFP-CMD" class="logo">
            </a>
        <div class="titles">
            <h1>Centre de Formation Professionnelle du Commerce et du Marketing Digital</h1>
            <p class="slogan">« Une formation de qualité pour un emploi sûr! »</p>
        </div>
    </div>
</header>            

<!-- Contenu Principal -->
<main class="main-content">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h2 class="mb-0"><i class="fas fa-file-contract me-2"></i> Termes d'utilisation</h2>
                    </div>
                    <div class="card-body">
                        <h3>1. Acceptation des termes</h3>
                        <p>En utilisant ce service, vous acceptez pleinement et sans réserve les présentes conditions d'utilisation.</p>
                        
                        <h3>2. Compte utilisateur</h3>
                        <p>Vous êtes responsable de la confidentialité de votre compte et de votre mot de passe. Toute activité sur votre compte est de votre responsabilité.</p>
                        
                        <h3>3. Données personnelles</h3>
                        <p>Nous collectons et utilisons vos données personnelles conformément à notre politique de confidentialité. En vous inscrivant, vous consentez à ce traitement.</p>
                        
                        <h3>4. Propriété intellectuelle</h3>
                        <p>Tout le contenu du site est la propriété du CFP-CMD et est protégé par les lois sur la propriété intellectuelle.</p>
                        
                        <h3>5. Responsabilités</h3>
                        <p>Le CFP-CMD ne peut être tenu responsable des dommages indirects résultant de l'utilisation du service.</p>
                        
                        <h3>6. Modifications</h3>
                        <p>Nous nous réservons le droit de modifier ces termes à tout moment. Les modifications prendront effet immédiatement après leur publication.</p>
                        
                        <div class="text-center mt-4">
                            <a href="../register.php" class="btn btn-primary">
                                <i class="fas fa-arrow-left me-2"></i>Retour à l'inscription
                            </a>
                        </div>
                    </div>
                    <div class="card-footer text-muted">
                        Dernière mise à jour : <?= date('d/m/Y') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>   

 <!-- Copyright -->
 <div class="footer-bottom">
    <div class="copyright">
        <p>&copy; 2024 - <?php echo date("Y"); ?> | CFP-CMD - Tous droits réservés | Powered by MASTER-NMA</p>
    </div>
</div>
</body>
</html>
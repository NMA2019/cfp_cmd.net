<?php
$pageTitle = "Accès Non Autorisé";
include 'includes/header2.php';
?>

<main class="container">
    <div class="alert alert-danger text-center">
        <h1><i class="fas fa-ban"></i> Accès Non Autorisé</h1>
        <p>Vous n'avez pas les permissions nécessaires pour accéder à cette page.</p>
        <a href="dashboard.php" class="btn btn-primary">Retour au tableau de bord</a>
    </div>
</main>

<?php include 'includes/footer.php'; ?>
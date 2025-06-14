<?php
require_once __DIR__.'/config/db_connection.php';
require_once __DIR__.'/config/auth.php';

$user_id = $_GET['id'] ?? null;

if (!$user_id) {
    header('Location: formations.php');
    exit();
}

try {
    // Récupérer les informations de l'utilisateur et de la formation
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email,
               f.name AS formation_name, fi.name AS filiere_name,
               p.total_amount, p.id AS pension_id
        FROM users u
        JOIN students s ON u.id = s.user_id
        JOIN formations f ON s.formation_id = f.id
        JOIN filieres fi ON f.filiere_id = fi.id
        JOIN pensions p ON s.id = p.student_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $data = $stmt->fetch();

    if (!$data) {
        throw new Exception("Informations de pré-inscription introuvables");
    }

} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: formations.php');
    exit();
}

$pageTitle = "Confirmation de Pré-inscription - CFP-CMD";
include __DIR__.'/includes/header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h2 class="mb-0">
                        <i class="fas fa-check-circle"></i> Pré-inscription confirmée
                    </h2>
                </div>
                <div class="card-body text-center">
                    <div class="mb-4">
                        <i class="fas fa-envelope fa-5x text-success mb-3"></i>
                        <h3>Merci, <?= htmlspecialchars($data['first_name']) ?> !</h3>
                        <p class="lead">Votre pré-inscription a été enregistrée avec succès.</p>
                    </div>

                    <div class="confirmation-details text-start mb-4 p-3 bg-light rounded">
                        <h4 class="text-center mb-3">Récapitulatif</h4>
                        <ul class="list-unstyled">
                            <li><strong>Formation :</strong> <?= htmlspecialchars($data['formation_name']) ?></li>
                            <li><strong>Filière :</strong> <?= htmlspecialchars($data['filiere_name']) ?></li>
                            <li><strong>Montant total :</strong> <?= number_format($data['total_amount'], 0, ',', ' ') ?> FCFA</li>
                            <li><strong>Numéro de dossier :</strong> PREF-<?= $data['pension_id'] ?></li>
                        </ul>
                    </div>

                    <div class="alert alert-info">
                        <h5><i class="fas fa-info-circle"></i> Prochaines étapes</h5>
                        <ol class="text-start">
                            <li>Un email de confirmation vous a été envoyé à <?= htmlspecialchars($data['email']) ?></li>
                            <li>Notre équipe vous contactera pour finaliser votre inscription</li>
                            <li>Préparez les documents nécessaires (CNI, diplômes, etc.)</li>
                        </ol>
                    </div>

                    <div class="d-grid gap-2">
                        <a href="formations.php" class="btn btn-primary">
                            <i class="fas fa-book"></i> Voir nos autres formations
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
<?php
require_once __DIR__.'/config/db_connection.php';
require_once __DIR__.'/config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: unauthorized.php');
    exit();
}

// Récupérer les pré-inscriptions
try {
    $query = "
        SELECT s.id, s.matricule, s.created_at, s.status,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name,
               u.email, u.phone,
               f.name AS formation_name, fi.name AS filiere_name,
               p.total_amount
        FROM students s
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON s.formation_id = f.id
        JOIN filieres fi ON f.filiere_id = fi.id
        JOIN pensions p ON s.id = p.student_id
        WHERE s.status = 'preinscript'
        ORDER BY s.created_at DESC
    ";
    
    $preinscriptions = $pdo->query($query)->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des pré-inscriptions: " . $e->getMessage();
}

$pageTitle = "Gestion des Pré-inscriptions - CFP-CMD";
include __DIR__.'/includes/header.php';
?>

<main class="container py-5">
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h1 class="mb-0">
                <i class="fas fa-user-graduate"></i> Gestion des Pré-inscriptions
            </h1>
        </div>
        
        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Matricule</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Montant</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($preinscriptions as $preins): ?>
                            <tr>
                                <td><?= htmlspecialchars($preins['matricule']) ?></td>
                                <td>
                                    <?= htmlspecialchars($preins['student_name']) ?><br>
                                    <small><?= htmlspecialchars($preins['email']) ?></small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($preins['formation_name']) ?><br>
                                    <small><?= htmlspecialchars($preins['filiere_name']) ?></small>
                                </td>
                                <td><?= number_format($preins['total_amount'], 0, ',', ' ') ?> FCFA</td>
                                <td><?= date('d/m/Y H:i', strtotime($preins['created_at'])) ?></td>
                                <td>
                                    <a href="student_details.php?id=<?= $preins['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                    <a href="validate_preinscription.php?id=<?= $preins['id'] ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i> Valider
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
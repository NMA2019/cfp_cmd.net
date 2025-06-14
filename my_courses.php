<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

if (!hasRole('etudiant')) {
    header('Location: unauthorized.php');
    exit();
}

$pageTitle = "Mes Formations - CFP-CMD";
include 'includes/header2.php';

try {
    // Récupérer l'ID de l'étudiant
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $studentId = $stmt->fetchColumn();
    
    if (!$studentId) {
        throw new Exception("Profil étudiant non trouvé");
    }

    // Récupérer les formations de l'étudiant
    $stmt = $pdo->prepare("
        SELECT f.id, f.name, fi.name AS filiere, ft.name AS type_formation,
               f.start_date, f.end_date, f.price,
               s.inscription_date, s.status,
               (SELECT COUNT(*) FROM student_modules sm 
                WHERE sm.student_id = s.id AND sm.formation_id = f.id) AS modules_count,
               (SELECT COUNT(*) FROM student_modules sm 
                WHERE sm.student_id = s.id AND sm.formation_id = f.id AND sm.status = 'valide') AS modules_completed
        FROM students s
        JOIN formations f ON s.formation_id = f.id
        JOIN filieres fi ON f.filiere_id = fi.id
        JOIN formation_types ft ON f.type_id = ft.id
        WHERE s.user_id = ?
        ORDER BY f.start_date DESC
    ");
    $stmt->execute([$studentId]);
    $formations = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-book"></i> Mes Formations</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h2>Formations Inscrites</h2>
        </div>
        <div class="card-body">
            <?php if (!empty($formations)): ?>
                <div class="formations-list">
                    <?php foreach ($formations as $formation): 
                        $progress = $formation['modules_count'] > 0 
                            ? round(($formation['modules_completed'] / $formation['modules_count']) * 100) 
                            : 0;
                        $statusClass = [
                            'preinscrit' => 'warning',
                            'inscrit' => 'info',
                            'formation' => 'primary',
                            'soutenance' => 'secondary',
                            'diplome' => 'success'
                        ][$formation['status']] ?? 'secondary';
                    ?>
                        <div class="formation-item">
                            <div class="formation-header">
                                <h3><?= htmlspecialchars($formation['name']) ?></h3>
                                <span class="badge badge-<?= $statusClass ?>"><?= ucfirst(htmlspecialchars($formation['status'])) ?></span>
                            </div>
                            <div class="formation-details">
                                <p><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars($formation['filiere']) ?> (<?= htmlspecialchars($formation['type_formation']) ?>)</p>
                                <p><i class="fas fa-calendar-alt"></i> 
                                    <?= date('d/m/Y', strtotime($formation['start_date'])) ?> - 
                                    <?= date('d/m/Y', strtotime($formation['end_date'])) ?>
                                </p>
                                <p><i class="fas fa-money-bill-wave"></i> <?= number_format($formation['price'], 0, ',', ' ') ?> MGA</p>
                            </div>
                            <div class="formation-progress">
                                <div class="progress-info">
                                    <span>Progression: <?= $progress ?>%</span>
                                    <span><?= $formation['modules_completed'] ?> / <?= $formation['modules_count'] ?> modules validés</span>
                                </div>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?= $progress ?>%"></div>
                                </div>
                            </div>
                            <div class="formation-actions">
                                <a href="formation_details.php?id=<?= $formation['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-info-circle"></i> Détails
                                </a>
                                <a href="formation_modules.php?id=<?= $formation['id'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-list-ul"></i> Modules
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Vous n'êtes actuellement inscrit à aucune formation.
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.formations-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.formation-item {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    padding: 20px;
    border-left: 4px solid var(--primary-color);
    transition: transform 0.2s, box-shadow 0.2s;
}

.formation-item:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
}

.formation-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.formation-header h3 {
    margin: 0;
    color: #333;
    font-size: 1.2rem;
}

.formation-details p {
    margin: 8px 0;
    color: #555;
    font-size: 14px;
}

.formation-details i {
    width: 20px;
    color: var(--primary-color);
}

.formation-progress {
    margin: 15px 0;
}

.progress-info {
    display: flex;
    justify-content: space-between;
    margin-bottom: 5px;
    font-size: 14px;
    color: #666;
}

.progress-bar {
    height: 10px;
    background: #f0f0f0;
    border-radius: 5px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: var(--primary-color);
    transition: width 0.3s;
}

.formation-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .formation-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .formation-actions {
        flex-direction: column;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
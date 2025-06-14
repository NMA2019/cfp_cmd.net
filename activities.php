<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupération des activités
$activities = [];
$filter = $_GET['filter'] ?? 'all';
$page = max(1, $_GET['page'] ?? 1);
$perPage = 20;
$offset = ($page - 1) * $perPage;

try {
    $query = "SELECT * FROM (
        SELECT 'payment' AS type, p.payment_date AS date, 
               CONCAT('Paiement de ', p.amount, ' € par ', u.first_name, ' ', u.last_name) AS description,
               CONCAT('formation.php?id=', f.id) AS link
        FROM payments p
        JOIN pensions pe ON p.pension_id = pe.id
        JOIN students s ON pe.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON pe.formation_id = f.id
        
        UNION ALL
        
        SELECT 'soutenance' AS type, so.presentation_date AS date,
               CONCAT('Soutenance de ', u.first_name, ' ', u.last_name, ' - ', so.title) AS description,
               CONCAT('soutenance.php?id=', so.id) AS link
        FROM soutenances so
        JOIN students s ON so.student_id = s.id
        JOIN users u ON s.user_id = u.id
        
        UNION ALL
        
        SELECT 'user' AS type, u.created_at AS date,
               CONCAT('Nouvel utilisateur: ', u.first_name, ' ', u.last_name) AS description,
               CONCAT('user_details.php?id=', u.id) AS link
        FROM users u
    ) AS activities";

    // Filtres
    if ($filter === 'payments') {
        $query .= " WHERE type = 'payment'";
    } elseif ($filter === 'soutenances') {
        $query .= " WHERE type = 'soutenance'";
    } elseif ($filter === 'users') {
        $query .= " WHERE type = 'user'";
    }

    $query .= " ORDER BY date DESC LIMIT $perPage OFFSET $offset";

    $activities = $pdo->query($query)->fetchAll();

    // Comptage total pour la pagination
    $countQuery = "SELECT COUNT(*) FROM (
        SELECT 'payment' AS type FROM payments
        UNION ALL SELECT 'soutenance' AS type FROM soutenances
        UNION ALL SELECT 'user' AS type FROM users
    ) AS activities";
    
    if ($filter !== 'all') {
        $countQuery .= " WHERE type = '$filter'";
    }
    
    $totalActivities = $pdo->query($countQuery)->fetchColumn();
    $totalPages = ceil($totalActivities / $perPage);

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des activités: " . $e->getMessage();
    logEvent("Erreur activities: " . $e->getMessage());
}

$pageTitle = "Activités - CFP-CMD";
include 'includes/header2.php';
?>

<main class="container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-history"></i> Historique des activités</h1>
            <div class="filters">
                <form method="GET" class="filter-form">
                    <select name="filter" onchange="this.form.submit()">
                        <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Toutes les activités</option>
                        <option value="payments" <?= $filter === 'payments' ? 'selected' : '' ?>>Paiements</option>
                        <option value="soutenances" <?= $filter === 'soutenances' ? 'selected' : '' ?>>Soutenances</option>
                        <option value="users" <?= $filter === 'users' ? 'selected' : '' ?>>Utilisateurs</option>
                    </select>
                </form>
            </div>
        </div>

        <div class="card-body">
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php elseif (count($activities) > 0): ?>
                <table class="activities-table">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Description</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activities as $activity): ?>
                            <tr>
                                <td>
                                    <span class="badge badge-<?= 
                                        $activity['type'] === 'payment' ? 'success' : 
                                        ($activity['type'] === 'soutenance' ? 'primary' : 'info') 
                                    ?>">
                                        <?= ucfirst($activity['type']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($activity['description']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($activity['date'])) ?></td>
                                <td>
                                    <a href="<?= htmlspecialchars($activity['link']) ?>" class="btn btn-sm btn-outline">
                                        <i class="fas fa-eye"></i> Voir
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- Pagination -->
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $page - 1 ?>" class="btn btn-outline">
                            <i class="fas fa-chevron-left"></i> Précédent
                        </a>
                    <?php endif; ?>

                    <span>Page <?= $page ?> sur <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?filter=<?= $filter ?>&page=<?= $page + 1 ?>" class="btn btn-outline">
                            Suivant <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-muted">Aucune activité trouvée</p>
            <?php endif; ?>
        </div>
    </div>
</main>

<style>
.activities-table {
    width: 100%;
    border-collapse: collapse;
}

.activities-table th, 
.activities-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.activities-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.filters {
    display: flex;
    justify-content: flex-end;
    margin-top: 10px;
}

.filter-form select {
    padding: 8px 12px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>

<?php include 'includes/footer.php'; ?>
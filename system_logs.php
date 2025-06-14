<?php
require_once '../config/db_connection.php';
require_once '../config/auth.php';

// Vérification des droits super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = "Journal Système - CFP-CMD";
include_once 'includes/header2.php';

// Pagination
$logs_per_page = 50;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $logs_per_page;

try {
    // Nombre total de logs
    $total_logs = $pdo->query("SELECT COUNT(*) FROM system_logs")->fetchColumn();
    $total_pages = ceil($total_logs / $logs_per_page);

    // Récupération des logs
    $logs = $pdo->query("
        SELECT id, timestamp, level, message, user_id
        FROM system_logs
        ORDER BY timestamp DESC
        LIMIT $offset, $logs_per_page
    ")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des logs: " . $e->getMessage();
}
?>

<main class="logs-container">
    <h1><i class="fas fa-clipboard-list"></i> Journal Système</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="logs-actions">
        <form method="GET" class="search-form">
            <input type="text" name="search" placeholder="Rechercher dans les logs..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            <button type="submit"><i class="fas fa-search"></i></button>
        </form>
        <a href="export_logs.php" class="btn btn-secondary">
            <i class="fas fa-download"></i> Exporter
        </a>
    </div>

    <div class="logs-table-container">
        <table class="logs-table">
            <thead>
                <tr>
                    <th>Date/Heure</th>
                    <th>Niveau</th>
                    <th>Message</th>
                    <th>Utilisateur</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): 
                    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                    $stmt->execute([$log['user_id']]);
                    $user = $stmt->fetch();
                ?>
                <tr class="log-<?= strtolower($log['level']) ?>">
                    <td><?= date('d/m/Y H:i:s', strtotime($log['timestamp'])) ?></td>
                    <td><span class="log-level"><?= $log['level'] ?></span></td>
                    <td><?= htmlspecialchars($log['message']) ?></td>
                    <td><?= $user ? htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) : 'Système' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($current_page > 1): ?>
            <a href="?page=<?= $current_page - 1 ?>" class="page-link">&laquo; Précédent</a>
        <?php endif; ?>

        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
            <a href="?page=<?= $i ?>" class="page-link <?= $i == $current_page ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="?page=<?= $current_page + 1 ?>" class="page-link">Suivant &raquo;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</main>

<style>
.logs-table {
    width: 100%;
    border-collapse: collapse;
}
.logs-table th {
    background-color: #0c6fb5;
    color: white;
    padding: 10px;
    text-align: left;
}
.logs-table td {
    padding: 8px;
    border-bottom: 1px solid #ddd;
}
.log-error {
    background-color: #ffebee;
}
.log-warning {
    background-color: #fff8e1;
}
.log-info {
    background-color: #e3f2fd;
}
.log-level {
    padding: 3px 6px;
    border-radius: 3px;
    font-weight: bold;
}
.log-level.ERROR {
    color: #d32f2f;
}
.log-level.WARNING {
    color: #ffa000;
}
.log-level.INFO {
    color: #1976d2;
}
.pagination {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}
.page-link {
    padding: 5px 10px;
    margin: 0 5px;
    border: 1px solid #ddd;
}
.page-link.active {
    background-color: #0c6fb5;
    color: white;
}
</style>

<?php include_once 'includes/footer.php'; ?>
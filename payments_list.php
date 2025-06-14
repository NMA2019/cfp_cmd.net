<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Liste des Paiements - CFP-CMD";
include_once 'includes/header2.php';

// Filtres et recherche
$where = [];
$params = [];

// Filtre par date
if (!empty($_GET['date_from']) && !empty($_GET['date_to'])) {
    $where[] = "p.payment_date BETWEEN ? AND ?";
    $params[] = $_GET['date_from'];
    $params[] = $_GET['date_to'];
} elseif (!empty($_GET['date_from'])) {
    $where[] = "p.payment_date >= ?";
    $params[] = $_GET['date_from'];
} elseif (!empty($_GET['date_to'])) {
    $where[] = "p.payment_date <= ?";
    $params[] = $_GET['date_to'];
}

// Filtre par méthode de paiement
if (!empty($_GET['method'])) {
    $where[] = "p.payment_method = ?";
    $params[] = $_GET['method'];
}

// Filtre par formation
if (!empty($_GET['formation'])) {
    $where[] = "f.id = ?";
    $params[] = $_GET['formation'];
}

// Recherche par nom étudiant
if (!empty($_GET['search'])) {
    $where[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = '%' . $_GET['search'] . '%';
    $params[] = '%' . $_GET['search'] . '%';
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Pagination
$per_page = 20;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $per_page;

try {
    // Nombre total de paiements
    $total_query = $pdo->prepare("
        SELECT COUNT(*)
        FROM payments p
        JOIN pensions pe ON p.pension_id = pe.id
        JOIN students s ON pe.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON pe.formation_id = f.id
        $where_clause
    ");
    $total_query->execute($params);
    $total_payments = $total_query->fetchColumn();
    $total_pages = ceil($total_payments / $per_page);

    // Requête principale
    $query = $pdo->prepare("
        SELECT p.id, p.amount, p.payment_date, p.payment_method, p.reference,
               p.tranche_number, p.received_by,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name,
               f.name AS formation,
               CONCAT(st.first_name, ' ', st.last_name) AS received_by_name
        FROM payments p
        JOIN pensions pe ON p.pension_id = pe.id
        JOIN students s ON pe.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON pe.formation_id = f.id
        LEFT JOIN staff stf ON p.received_by = stf.id
        LEFT JOIN users st ON stf.user_id = st.id
        $where_clause
        ORDER BY p.payment_date DESC
        LIMIT $per_page OFFSET $offset
    ");
    $query->execute($params);
    $payments = $query->fetchAll();

    // Options pour les filtres
    $payment_methods = $pdo->query("SELECT DISTINCT payment_method FROM payments")->fetchAll(PDO::FETCH_COLUMN);
    $formations = $pdo->query("SELECT id, name FROM formations")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des paiements: " . $e->getMessage();
}
?>

<main class="payments-list-container">
    <h1><i class="fas fa-money-bill-wave"></i> Liste des Paiements</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <section class="filters-section">
        <h2><i class="fas fa-filter"></i> Filtres</h2>
        <form method="GET" class="filter-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="date_from">Date de début</label>
                    <input type="date" id="date_from" name="date_from" value="<?= htmlspecialchars($_GET['date_from'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="date_to">Date de fin</label>
                    <input type="date" id="date_to" name="date_to" value="<?= htmlspecialchars($_GET['date_to'] ?? '') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="method">Méthode de paiement</label>
                    <select id="method" name="method">
                        <option value="">Toutes</option>
                        <?php foreach ($payment_methods as $method): ?>
                            <option value="<?= $method ?>" <?= isset($_GET['method']) && $_GET['method'] === $method ? 'selected' : '' ?>>
                                <?= ucfirst($method) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="formation">Formation</label>
                    <select id="formation" name="formation">
                        <option value="">Toutes</option>
                        <?php foreach ($formations as $formation): ?>
                            <option value="<?= $formation['id'] ?>" <?= isset($_GET['formation']) && $_GET['formation'] == $formation['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($formation['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group search-group">
                    <label for="search">Recherche étudiant</label>
                    <input type="text" id="search" name="search" placeholder="Nom de l'étudiant..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Appliquer
                    </button>
                    <a href="payments_list.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Réinitialiser
                    </a>
                </div>
            </div>
        </form>
    </section>

    <section class="payments-section">
        <div class="payments-header">
            <h2><i class="fas fa-list"></i> Résultats (<?= $total_payments ?>)</h2>
            <div class="actions">
                <a href="export_payments.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">
                    <i class="fas fa-file-export"></i> Exporter
                </a>
                <a href="payment_receipt.php" class="btn btn-primary" target="_blank">
                    <i class="fas fa-receipt"></i> Générer reçu
                </a>
            </div>
        </div>

        <?php if ($payments): ?>
            <table class="payments-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Étudiant</th>
                        <th>Formation</th>
                        <th>Tranche</th>
                        <th>Montant</th>
                        <th>Méthode</th>
                        <th>Reçu par</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                        <td><?= htmlspecialchars($payment['student_name']) ?></td>
                        <td><?= htmlspecialchars($payment['formation']) ?></td>
                        <td><?= $payment['tranche_number'] ?></td>
                        <td><?= number_format($payment['amount'], 2, ',', ' ') ?> €</td>
                        <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                        <td><?= $payment['received_by_name'] ?? 'Système' ?></td>
                        <td class="actions">
                            <a href="payment_details.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-info" title="Détails">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="edit_payment.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-warning" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link">
                        &laquo; Précédent
                    </a>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link">
                        Suivant &raquo;
                    </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-info-circle"></i>
                <p>Aucun paiement trouvé avec ces critères</p>
            </div>
        <?php endif; ?>
    </section>
</main>

<style>
.payments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
.payments-table th {
    background-color: #0c6fb5;
    color: white;
    padding: 12px;
    text-align: left;
}
.payments-table td {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
.payments-table tr:hover {
    background-color: #f5f5f5;
}
.actions {
    white-space: nowrap;
}
.actions a {
    margin-right: 5px;
}
.pagination {
    margin-top: 20px;
    display: flex;
    justify-content: center;
}
.page-link {
    padding: 8px 12px;
    margin: 0 5px;
    border: 1px solid #ddd;
    text-decoration: none;
}
.page-link.active {
    background-color: #0c6fb5;
    color: white;
    border-color: #0c6fb5;
}
.filter-form {
    background-color: #f8f9fa;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}
.form-group {
    flex: 1;
}
.search-group {
    display: flex;
    align-items: flex-end;
    gap: 10px;
}
.search-group input {
    flex: 1;
}
.no-results {
    text-align: center;
    padding: 40px;
    color: #6c757d;
}
</style>

<?php include_once 'includes/footer.php'; ?>
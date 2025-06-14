<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès - Seuls les admins et comptables
checkRole([1, 2]); // Super Admin et Admin

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        if (isset($_POST['add_transaction'])) {
            // Ajout d'une nouvelle transaction
            $stmt = $pdo->prepare("INSERT INTO cash 
                                (transaction_type, amount, payment_id, description, reference, handled_by, transaction_date) 
                                VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_POST['transaction_type'],
                $_POST['amount'],
                $_POST['payment_id'] ?? null,
                $_POST['description'],
                generateReference(),
                $_SESSION['user_id'],
                $_POST['transaction_date']
            ]);
            
            $success = "Transaction enregistrée avec succès";
        } elseif (isset($_POST['update_transaction'])) {
            // Mise à jour d'une transaction
            $stmt = $pdo->prepare("UPDATE cash SET 
                                transaction_type = ?,
                                amount = ?,
                                payment_id = ?,
                                description = ?,
                                handled_by = ?,
                                transaction_date = ?
                                WHERE id = ?");
            $stmt->execute([
                $_POST['transaction_type'],
                $_POST['amount'],
                $_POST['payment_id'] ?? null,
                $_POST['description'],
                $_SESSION['user_id'],
                $_POST['transaction_date'],
                $_POST['transaction_id']
            ]);
            
            $success = "Transaction mise à jour avec succès";
        }
        
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'opération: " . $e->getMessage();
        logEvent("Erreur caisse: " . $e->getMessage());
    }
}

// Suppression d'une transaction
if (isset($_GET['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM cash WHERE id = ?");
        $stmt->execute([$_GET['delete']]);
        $success = "Transaction supprimée avec succès";
    } catch (PDOException $e) {
        $error = "Impossible de supprimer cette transaction";
        logEvent("Erreur suppression transaction: " . $e->getMessage());
    }
}

// Récupération des données pour les selects
$payments = $pdo->query("SELECT p.id, p.reference, p.amount, s.matricule 
                       FROM payments p
                       JOIN pensions ps ON p.pension_id = ps.id
                       JOIN students s ON ps.student_id = s.id
                       ORDER BY p.payment_date DESC")->fetchAll();

$staffMembers = $pdo->query("SELECT s.id, u.first_name, u.last_name 
                           FROM staff s
                           JOIN users u ON s.user_id = u.id
                           ORDER BY u.last_name")->fetchAll();

// Récupération des transactions avec pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

// Requête principale avec jointures
$sql = "SELECT c.*, 
       u.first_name as handler_first_name, u.last_name as handler_last_name,
       p.reference as payment_reference, s.matricule as student_matricule
       FROM cash c
       LEFT JOIN payments p ON c.payment_id = p.id
       LEFT JOIN pensions ps ON p.pension_id = ps.id
       LEFT JOIN students s ON ps.student_id = s.id
       JOIN staff st ON c.handled_by = st.id
       JOIN users u ON st.user_id = u.id
       ORDER BY c.transaction_date DESC
       LIMIT $offset, $perPage";

$transactions = $pdo->query($sql)->fetchAll();

// Calcul du solde
$solde = $pdo->query("SELECT 
                     SUM(CASE WHEN transaction_type = 'entree' THEN amount ELSE 0 END) as total_entrees,
                     SUM(CASE WHEN transaction_type = 'sortie' THEN amount ELSE 0 END) as total_sorties
                     FROM cash")->fetch();

$totalTransactions = $pdo->query("SELECT COUNT(*) FROM cash")->fetchColumn();
$totalPages = ceil($totalTransactions / $perPage);

// Fonction pour générer une référence unique
function generateReference() {
    return 'TR-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
}

$pageTitle = "Gestion de Caisse - CFP-CMD";
include_once 'includes/header.php';
?>

<div class="container py-5">
    <h1 class="mb-4"><i class="fas fa-cash-register"></i> Gestion de Caisse</h1>
    
        <!-- Affichage des messages -->
        <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php endif; ?>

    <!-- Carte de synthèse -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Entrées</h5>
                    <p class="card-text h4"><?= number_format($solde['total_entrees'] ?? 0, 2, ',', ' ') ?> FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card bg-danger text-white">
                <div class="card-body">
                    <h5 class="card-title">Total Sorties</h5>
                    <p class="card-text h4"><?= number_format($solde['total_sorties'] ?? 0, 2, ',', ' ') ?> FCFA</p>
                </div>
            </div>
        </div>
        <div class="col-md-12 mt-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Solde Actuel</h5>
                    <p class="card-text h4">
                        <?= number_format(($solde['total_entrees'] ?? 0) - ($solde['total_sorties'] ?? 0), 2, ',', ' ') ?> FCFA
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Bouton d'ajout -->
    <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#transactionModal">
        <i class="fas fa-plus"></i> Nouvelle Transaction
    </button>

    <!-- Tableau des transactions -->
    <div class="card shadow-sm">
        <div class="card-header bg-primary text-white">
            <h2 class="h5 mb-0"><i class="fas fa-list"></i> Historique des Transactions</h2>
        </div>
        <div class="card-body">
            <div class="table-responsive">

                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Référence</th>
                            <th>Type</th>
                            <th>Montant</th>
                            <th>Description</th>
                            <th>Paiement lié</th>
                            <th>Géré par</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($transactions)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Aucune transaction enregistrée</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($transactions as $index => $tx): ?>
                                <tr>
                                    <td><?= $offset + $index + 1 ?></td>
                                    <td><?= date('Y-m-d H:i', strtotime($tx['transaction_date'])) ?></td>
                                    <td><?= htmlspecialchars($tx['reference']) ?></td>
                                    <td>
                                        <span class="badge bg-<?= $tx['transaction_type'] === 'entree' ? 'success' : 'danger' ?>">
                                            <?= $tx['transaction_type'] === 'entree' ? 'Entrée' : 'Sortie' ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($tx['amount'], 0, ',', ' ') ?> FCFA</td>
                                    <td><?= htmlspecialchars($tx['description']) ?></td>
                                    <td>
                                        <?php if ($tx['payment_reference']): ?>
                                            <a href="payment_details.php?id=<?= $tx['payment_id'] ?>" 
                                               title="Voir le paiement"
                                               class="text-decoration-none">
                                                <?= $tx['payment_reference'] ?>
                                                <?= $tx['student_matricule'] ? "({$tx['student_matricule']})" : '' ?>
                                            </a>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($tx['handler_first_name'] . ' ' . $tx['handler_last_name']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#transactionModal"
                                                onclick="editTransaction(<?= $tx['id'] ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <a href="cash.php?delete=<?= $tx['id'] ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Confirmer la suppression?')">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

<!-- Modal adapté -->
<div class="modal fade" id="transactionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Nouvelle Transaction</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" id="transactionForm">
                <input type="hidden" name="transaction_id" id="transactionId">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="transaction_date" class="form-label">Date</label>
                                <input type="datetime-local" class="form-control" id="transaction_date" name="transaction_date" required>
                            </div>
                            <div class="mb-3">
                                <label for="transaction_type" class="form-label">Type</label>
                                <select class="form-select" id="transaction_type" name="transaction_type" required>
                                    <option value="entree">Entrée d'argent</option>
                                    <option value="sortie">Sortie d'argent</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="amount" class="form-label">Montant (FCFA)</label>
                                <input type="number" class="form-control" id="amount" name="amount" step="0.01" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="payment_id" class="form-label">Lier à un paiement (optionnel)</label>
                                <select class="form-select" id="payment_id" name="payment_id">
                                    <option value="">-- Aucun paiement lié --</option>
                                    <?php foreach ($payments as $payment): ?>
                                        <option value="<?= $payment['id'] ?>">
                                            <?= $payment['reference'] ?> - 
                                            <?= number_format($payment['amount'], 0, ',', ' ') ?> FCFA - 
                                            <?= $payment['matricule'] ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times"></i> Annuler
                    </button>
                    <button type="submit" class="btn btn-primary" name="add_transaction" id="submitButton">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts JavaScript -->
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
// Pré-remplir le modal pour l'édition
function editTransaction(id) {
    fetch(`api/get_transaction.php?id=${id}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Modifier Transaction';
            document.getElementById('transactionId').value = data.id;
            document.getElementById('transaction_type').value = data.transaction_type;
            document.getElementById('amount').value = data.amount;
            document.getElementById('description').value = data.description;
            document.getElementById('transaction_date').value = data.transaction_date.replace(' ', 'T');
            document.getElementById('submitButton').name = 'update_transaction';
        });
}

// Réinitialiser le modal pour une nouvelle transaction
document.getElementById('transactionModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('transactionForm').reset();
    document.getElementById('modalTitle').textContent = 'Nouvelle Transaction';
    document.getElementById('submitButton').name = 'add_transaction';
});

// Définir la date par défaut à maintenant
document.getElementById('transaction_date').value = new Date().toISOString().slice(0, 16);
</script>

<?php
include_once 'includes/footer.php';
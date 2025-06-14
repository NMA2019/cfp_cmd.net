<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérifier les permissions
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Seuls les admins, super admins et professeurs peuvent accéder
if ($_SESSION['role'] === 'etudiant') {
    header('Location: dashboard.php');
    exit();
}

$pageTitle = "Gestion des Pensions - CFP-CMD";
include 'includes/header2.php';

// Traitement du paiement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['paiement'])) {
    try {
        $pdo->beginTransaction();

        // 1. Enregistrer le paiement
        $stmt = $pdo->prepare("INSERT INTO payments (pension_id, tranche_number, amount, payment_date, payment_method, reference, received_by, notes) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $reference = 'PAY-' . date('Ymd') . '-' . strtoupper(uniqid());
        
        $stmt->execute([
            $_POST['pension_id'],
            $_POST['tranche_number'],
            $_POST['amount'],
            $_POST['payment_date'],
            $_POST['payment_method'],
            $reference,
            $_SESSION['user_id'],
            $_POST['notes']
        ]);

        // 2. Mettre à jour le montant payé dans la pension
        $pdo->query("UPDATE pensions SET paid_amount = paid_amount + {$_POST['amount']} WHERE id = {$_POST['pension_id']}");

        // 3. Enregistrer dans la caisse
        $stmt = $pdo->prepare("INSERT INTO cash (transaction_type, amount, payment_id, description, reference, handled_by, transaction_date) 
                              VALUES ('entree', ?, ?, ?, ?, ?, NOW())");
        
        $description = "Paiement pension tranche {$_POST['tranche_number']}";
        
        $stmt->execute([
            $_POST['amount'],
            $pdo->lastInsertId(),
            $description,
            'CA-' . $reference,
            $_SESSION['user_id']
        ]);

        $pdo->commit();
        
        $success = "Paiement enregistré avec succès! Référence: $reference";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Erreur lors de l'enregistrement du paiement: " . $e->getMessage();
        logEvent("Erreur paiement pension: " . $e->getMessage());
    }
}

// Récupérer les pensions avec filtres
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "SELECT p.id, p.student_id, p.formation_id, p.total_amount, p.paid_amount, p.remaining_amount, p.status,
                 s.matricule, CONCAT(u.first_name, ' ', u.last_name) AS student_name,
                 f.name AS formation, ft.tranches AS tranches_info
          FROM pensions p
          JOIN students s ON p.student_id = s.id
          JOIN users u ON s.user_id = u.id
          JOIN formations f ON p.formation_id = f.id
          JOIN formation_types ft ON f.type_id = ft.id";

$where = [];
$params = [];

if ($search) {
    $where[] = "(s.matricule LIKE ? OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($filter === 'unpaid') {
    $where[] = "p.status != 'complet'";
} elseif ($filter === 'partial') {
    $where[] = "p.status = 'partiel'";
} elseif ($filter === 'paid') {
    $where[] = "p.status = 'complet'";
}

if ($where) {
    $query .= " WHERE " . implode(" AND ", $where);
}

$query .= " ORDER BY p.status, s.matricule";

$pensions = $pdo->prepare($query);
$pensions->execute($params);
$pensions = $pensions->fetchAll();

// Récupérer les tranches de paiement pour le modal
if (isset($_GET['pension_id'])) {
    $pensionDetails = $pdo->query("
        SELECT p.*, s.matricule, CONCAT(u.first_name, ' ', u.last_name) AS student_name,
               f.name AS formation, ft.name AS type_formation, ft.tranches
        FROM pensions p
        JOIN students s ON p.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON p.formation_id = f.id
        JOIN formation_types ft ON f.type_id = ft.id
        WHERE p.id = {$_GET['pension_id']}
    ")->fetch();

    $payments = $pdo->query("
        SELECT * FROM payments 
        WHERE pension_id = {$_GET['pension_id']}
        ORDER BY tranche_number
    ")->fetchAll();

    // Calcul des tranches attendues
    $tranches = explode(',', $pensionDetails['tranches']);
    $trancheCount = (int)$tranches[0];
    $expectedPayments = [];

    for ($i = 1; $i <= $trancheCount; $i++) {
        $percentage = $tranches[$i] / 100;
        $amount = $pensionDetails['total_amount'] * $percentage;
        $expectedPayments[$i] = $amount;
    }
}
?>

<main class="container">
    <h1 class="page-title"><i class="fas fa-money-bill-wave"></i> Gestion des Pensions</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <div class="card">
        <div class="card-header">
            <h2>Liste des Pensions</h2>
            <div class="filters">
                <form method="GET" class="filter-form">
                    <div class="form-group">
                        <select name="filter" onchange="this.form.submit()">
                            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>Toutes</option>
                            <option value="unpaid" <?= $filter === 'unpaid' ? 'selected' : '' ?>>Non payées</option>
                            <option value="partial" <?= $filter === 'partial' ? 'selected' : '' ?>>Partielles</option>
                            <option value="paid" <?= $filter === 'paid' ? 'selected' : '' ?>>Payées</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="text" name="search" placeholder="Rechercher..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <button class="btn btn-primary" onclick="printPensions()"><i class="fas fa-print"></i> Imprimer</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table id="pensionsTable" class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Matricule</th>
                            <th>Étudiant</th>
                            <th>Formation</th>
                            <th>Pension</th>
                            <th>Payé</th>
                            <th>Reste</th>
                            <th>Statut</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pensions as $index => $pension): 
                            $statusClass = $pension['status'] === 'complet' ? 'success' : 
                                         ($pension['status'] === 'partiel' ? 'warning' : 'danger');
                        ?>
                            <tr>
                                <td><?= $index + 1 ?></td>
                                <td><?= htmlspecialchars($pension['matricule']) ?></td>
                                <td><?= htmlspecialchars($pension['student_name']) ?></td>
                                <td><?= htmlspecialchars($pension['formation']) ?></td>
                                <td><?= number_format($pension['total_amount'], 0, ',', ' ') ?> FCFA</td>
                                <td><?= number_format($pension['paid_amount'], 0, ',', ' ') ?> FCFA</td>
                                <td><?= number_format($pension['remaining_amount'], 0, ',', ' ') ?> FCFA</td>
                                <td><span class="badge badge-<?= $statusClass ?>"><?= $pension['status'] === 'complet' ? 'Complet' : ($pension['status'] === 'partiel' ? 'Partiel' : 'Non payé') ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-outline" onclick="viewPension(<?= $pension['id'] ?>)">
                                        <i class="fas fa-eye"></i> Détails
                                    </button>
                                    <button class="btn btn-sm btn-primary" onclick="addPayment(<?= $pension['id'] ?>)">
                                        <i class="fas fa-plus"></i> Paiement
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<!-- Modal pour les détails de pension -->
<div id="pensionModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <span class="close-modal" onclick="closeModal('pensionModal')">&times;</span>
        <div id="pensionDetails">
            <?php if (isset($pensionDetails)): ?>
                <h2><i class="fas fa-money-bill-wave"></i> Détails de la Pension</h2>
                
                <div class="pension-header">
                    <div class="pension-info">
                        <h3><?= htmlspecialchars($pensionDetails['student_name']) ?></h3>
                        <p>Matricule: <?= htmlspecialchars($pensionDetails['matricule']) ?></p>
                        <p>Formation: <?= htmlspecialchars($pensionDetails['formation']) ?> (<?= $pensionDetails['type_formation'] ?>)</p>
                    </div>
                    <div class="pension-summary">
                        <div class="summary-card">
                            <span class="summary-label">Total à payer</span>
                            <span class="summary-value"><?= number_format($pensionDetails['total_amount'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="summary-card">
                            <span class="summary-label">Déjà payé</span>
                            <span class="summary-value"><?= number_format($pensionDetails['paid_amount'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                        <div class="summary-card">
                            <span class="summary-label">Reste à payer</span>
                            <span class="summary-value"><?= number_format($pensionDetails['remaining_amount'], 0, ',', ' ') ?> FCFA</span>
                        </div>
                    </div>
                </div>
                
                <h3><i class="fas fa-list-ol"></i> Tranches de paiement</h3>
                <div class="tranches-container">
                    <?php
                    $tranches = explode(',', $pensionDetails['tranches']);
                    $trancheCount = (int)$tranches[0];
                    
                    for ($i = 1; $i <= $trancheCount; $i++):
                        $percentage = $tranches[$i];
                        $amount = $pensionDetails['total_amount'] * ($percentage / 100);
                        $paid = 0;
                        
                        foreach ($payments as $payment) {
                            if ($payment['tranche_number'] == $i) {
                                $paid = $payment['amount'];
                                break;
                            }
                        }
                        
                        $remaining = $amount - $paid;
                        $isComplete = $remaining <= 0;
                    ?>
                        <div class="tranche-card <?= $isComplete ? 'complete' : '' ?>">
                            <div class="tranche-header">
                                <h4>Tranche <?= $i ?> (<?= $percentage ?>%)</h4>
                                <span class="tranche-amount"><?= number_format($amount, 0, ',', ' ') ?> FCFA</span>
                            </div>
                            <div class="tranche-progress">
                                <div class="progress-bar" style="width: <?= ($paid / $amount) * 100 ?>%"></div>
                                <span class="progress-text">
                                    <?= number_format($paid, 0, ',', ' ') ?> FCFA payés sur <?= number_format($amount, 0, ',', ' ') ?> FCFA
                                </span>
                            </div>
                            <?php if (!$isComplete): ?>
                                <button class="btn btn-sm btn-primary" onclick="payTranche(<?= $pensionDetails['id'] ?>, <?= $i ?>, <?= $remaining ?>)">
                                    <i class="fas fa-money-bill-wave"></i> Payer (reste: <?= number_format($remaining, 0, ',', ' ') ?> FCFA)
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>
                
                <h3><i class="fas fa-history"></i> Historique des paiements</h3>
                <?php if ($payments): ?>
                    <table class="payments-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Tranche</th>
                                <th>Montant</th>
                                <th>Méthode</th>
                                <th>Référence</th>
                                <th>Reçu par</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                                    <td><?= $payment['tranche_number'] ?></td>
                                    <td><?= number_format($payment['amount'], 0, ',', ' ') ?> FCFA</td>
                                    <td><?= ucfirst($payment['payment_method']) ?></td>
                                    <td><?= $payment['reference'] ?></td>
                                    <td><?= $payment['received_by'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Aucun paiement enregistré.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour ajouter un paiement -->
<div id="paymentModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('paymentModal')">&times;</span>
        <h2><i class="fas fa-money-bill-wave"></i> Nouveau Paiement</h2>
        
        <form id="paymentForm" method="POST">
            <input type="hidden" name="paiement" value="1">
            <input type="hidden" id="pension_id" name="pension_id" value="<?= $_GET['pension_id'] ?? '' ?>">
            
            <div class="form-group">
                <label for="tranche_number"><i class="fas fa-list-ol"></i> Tranche</label>
                <input type="number" id="tranche_number" name="tranche_number" min="1" required>
            </div>
            
            <div class="form-group">
                <label for="amount"><i class="fas fa-money-bill-alt"></i> Montant</label>
                <input type="number" id="amount" name="amount" min="1" required>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="payment_date"><i class="fas fa-calendar-day"></i> Date</label>
                    <input type="date" id="payment_date" name="payment_date" required value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label for="payment_method"><i class="fas fa-credit-card"></i> Méthode</label>
                    <select id="payment_method" name="payment_method" required>
                        <option value="especes">Espèces</option>
                        <option value="cheque">Chèque</option>
                        <option value="virement">Virement</option>
                        <option value="mobile_money">Mobile Money</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label for="notes"><i class="fas fa-sticky-note"></i> Notes</label>
                <textarea id="notes" name="notes" rows="2" placeholder="Informations supplémentaires"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('paymentModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Enregistrer</button>
            </div>
        </form>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
// Fonctions pour la gestion des pensions
function viewPension(id) {
    window.location.href = `pension.php?pension_id=${id}`;
}

function addPayment(id) {
    window.location.href = `pension.php?pension_id=${id}&add_payment=1`;
}

function payTranche(pensionId, trancheNumber, amount) {
    document.getElementById('pension_id').value = pensionId;
    document.getElementById('tranche_number').value = trancheNumber;
    document.getElementById('amount').value = amount;
    document.getElementById('amount').max = amount;
    openModal('paymentModal');
}

function printPensions() {
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
            <head>
                <title>État des Pensions - CFP-CMD</title>
                <style>
                    body { font-family: Arial; margin: 20px; }
                    h1 { color: #0c6fb5; text-align: center; }
                    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                    th { background-color: #f2f2f2; }
                    .logo { text-align: center; margin-bottom: 20px; }
                    .date { text-align: right; margin-bottom: 20px; }
                    .badge { padding: 3px 6px; border-radius: 3px; font-size: 12px; }
                    .badge-success { background-color: #d4edda; color: #155724; }
                    .badge-warning { background-color: #fff3cd; color: #856404; }
                    .badge-danger { background-color: #f8d7da; color: #721c24; }
                </style>
            </head>
            <body>
                <div class="logo">
                    <img src="assets/images/logo-cfp-cmd.png" alt="Logo" height="80">
                    <h1>État des Pensions</h1>
                </div>
                <div class="date">
                    Généré le ${new Date().toLocaleDateString()} à ${new Date().toLocaleTimeString()}
                    <br>Filtre: ${document.querySelector('select[name="filter"] option:checked').textContent}
                </div>
                ${document.getElementById('pensionsTable').outerHTML}
            </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// Si on doit afficher un modal au chargement
document.addEventListener('DOMContentLoaded', function() {
    <?php if (isset($_GET['pension_id'])): ?>
        openModal('pensionModal');
    <?php endif; ?>
    
    <?php if (isset($_GET['add_payment'])): ?>
        document.getElementById('pension_id').value = <?= $_GET['pension_id'] ?>;
        openModal('paymentModal');
    <?php endif; ?>
});
</script>

</body>
</html>
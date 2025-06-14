<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits étudiant
if (!isset($_SESSION['user_id']) || !hasRole('etudiant')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Mes Paiements - CFP-CMD";
include_once 'includes/header2.php';

try {
    // Récupérer l'ID de l'étudiant
    $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student_id = $stmt->fetchColumn();
    
    if (!$student_id) {
        throw new Exception("Profil étudiant non trouvé");
    }

    // Récupérer les informations de pension
    $stmt = $pdo->prepare("
        SELECT p.id, f.name AS formation, p.total_amount, p.paid_amount, 
               p.remaining_amount, p.status, f.id AS formation_id
        FROM pensions p
        JOIN formations f ON p.formation_id = f.id
        WHERE p.student_id = ?
    ");
    $stmt->execute([$student_id]);
    $pensions = $stmt->fetchAll();

    // Récupérer l'historique des paiements
    $stmt = $pdo->prepare("
        SELECT py.id, py.amount, py.payment_date, py.payment_method, 
               f.name AS formation, py.tranche_number, py.receipt_number
        FROM payments py
        JOIN pensions pe ON py.pension_id = pe.id
        JOIN formations f ON pe.formation_id = f.id
        WHERE pe.student_id = ?
        ORDER BY py.payment_date DESC
    ");
    $stmt->execute([$student_id]);
    $payments = $stmt->fetchAll();

    // Prochains paiements attendus
    $stmt = $pdo->prepare("
        SELECT p.id, f.name AS formation, ft.tranches,
               (SELECT COUNT(*) FROM payments WHERE pension_id = p.id) AS paid_tranches,
               p.total_amount, p.paid_amount
        FROM pensions p
        JOIN formations f ON p.formation_id = f.id
        JOIN formation_types ft ON f.type_id = ft.id
        WHERE p.student_id = ? AND p.status != 'complet'
    ");
    $stmt->execute([$student_id]);
    $next_payments = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur de base de données: " . $e->getMessage();
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>

<main class="payments-container">
    <h1><i class="fas fa-money-bill-wave"></i> Mes Paiements</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <section class="payment-summary">
        <h2><i class="fas fa-file-invoice-dollar"></i> Résumé des Pensions</h2>
        <?php if (!empty($pensions)): ?>
            <div class="pension-cards">
                <?php foreach ($pensions as $pension): 
                    $paid_percent = $pension['total_amount'] > 0 
                        ? round(($pension['paid_amount'] / $pension['total_amount']) * 100) 
                        : 0;
                ?>
                <div class="pension-card <?= $pension['status'] === 'complet' ? 'paid' : '' ?>">
                    <h3><?= htmlspecialchars($pension['formation']) ?></h3>
                    <div class="progress-container">
                        <div class="progress-bar" style="width: <?= $paid_percent ?>%"></div>
                        <span class="progress-text"><?= $paid_percent ?>%</span>
                    </div>
                    <div class="amounts">
                        <span>Total: <?= number_format($pension['total_amount'], 2, ',', ' ') ?> €</span>
                        <span>Payé: <?= number_format($pension['paid_amount'], 2, ',', ' ') ?> €</span>
                        <span>Restant: <?= number_format($pension['remaining_amount'], 2, ',', ' ') ?> €</span>
                    </div>
                    <div class="status">
                        Statut: <span class="status-badge <?= $pension['status'] ?>"><?= ucfirst(htmlspecialchars($pension['status'])) ?></span>
                    </div>
                    <a href="payment_details.php?formation_id=<?= $pension['formation_id'] ?>" class="btn btn-sm btn-outline">
                        Voir détails
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucune pension enregistrée.
            </div>
        <?php endif; ?>
    </section>

    <section class="payment-history">
        <h2><i class="fas fa-history"></i> Historique des Paiements</h2>
        <?php if (!empty($payments)): ?>
            <div class="table-responsive">
                <table class="payments-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Formation</th>
                            <th>Tranche</th>
                            <th>Montant</th>
                            <th>Méthode</th>
                            <th>Reçu</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                            <td><?= htmlspecialchars($payment['formation']) ?></td>
                            <td><?= $payment['tranche_number'] ?></td>
                            <td><?= number_format($payment['amount'], 2, ',', ' ') ?> €</td>
                            <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                            <td><?= htmlspecialchars($payment['receipt_number']) ?></td>
                            <td>
                                <a href="payment_receipt.php?id=<?= $payment['id'] ?>" class="btn btn-sm btn-outline" title="Voir reçu">
                                    <i class="fas fa-receipt"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucun paiement enregistré.
            </div>
        <?php endif; ?>
    </section>

    <section class="next-payments">
        <h2><i class="fas fa-calendar-check"></i> Prochains Paiements</h2>
        <?php if (!empty($next_payments)): ?>
            <div class="next-payments-list">
                <?php foreach ($next_payments as $next): 
                    $tranches = explode(',', $next['tranches']);
                    $next_tranche = (int)$next['paid_tranches'] + 1;
                    
                    if ($next_tranche <= count($tranches)): 
                        $amount = $next['total_amount'] * ($tranches[$next_tranche - 1] / 100);
                ?>
                <div class="next-payment">
                    <h3><?= htmlspecialchars($next['formation']) ?> - Tranche <?= $next_tranche ?></h3>
                    <p>Montant attendu: <?= number_format($amount, 2, ',', ' ') ?> €</p>
                    <p>Date limite: <?= date('d/m/Y', strtotime('+15 days')) ?></p>
                    <div class="actions">
                        <a href="make_payment.php?pension_id=<?= $next['id'] ?>&tranche=<?= $next_tranche ?>" class="btn btn-primary">
                            <i class="fas fa-credit-card"></i> Payer maintenant
                        </a>
                        <a href="payment_plan.php?pension_id=<?= $next['id'] ?>" class="btn btn-outline">
                            <i class="fas fa-calendar-alt"></i> Plan de paiement
                        </a>
                    </div>
                </div>
                <?php endif; endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Aucun paiement à venir.
            </div>
        <?php endif; ?>
    </section>
</main>

<style>
.payments-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.pension-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.pension-card {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--primary-color);
}

.pension-card.paid {
    border-left-color: var(--success);
}

.pension-card h3 {
    margin-top: 0;
    color: var(--dark);
}

.progress-container {
    height: 20px;
    background: #f0f0f0;
    border-radius: 10px;
    margin: 15px 0;
    position: relative;
}

.progress-bar {
    height: 100%;
    border-radius: 10px;
    background: var(--primary);
    transition: width 0.3s;
}

.progress-text {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 12px;
    font-weight: bold;
}

.amounts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin: 15px 0;
    font-size: 14px;
}

.status {
    margin-bottom: 15px;
}

.status-badge {
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
}

.status-badge.complet {
    background: var(--success-light);
    color: var(--success-dark);
}

.status-badge.partiel {
    background: var(--warning-light);
    color: var(--warning-dark);
}

.status-badge.en_retard {
    background: var(--danger-light);
    color: var(--danger-dark);
}

.payments-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}

.payments-table th, .payments-table td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #eee;
}

.payments-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.next-payments-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.next-payment {
    background: white;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    border-left: 4px solid var(--warning);
}

.next-payment h3 {
    margin-top: 0;
    color: var(--dark);
}

.next-payment .actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
}

@media (max-width: 768px) {
    .pension-cards, .next-payments-list {
        grid-template-columns: 1fr;
    }
    
    .payments-table {
        display: block;
        overflow-x: auto;
    }
    
    .amounts {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include_once 'includes/footer.php'; ?>
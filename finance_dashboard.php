<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Tableau de Bord Financier - CFP-CMD";
include_once 'includes/header2.php';

try {
    // Statistiques financières
    $stats = [
        'annual_revenue' => $pdo->query("SELECT SUM(amount) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn(),
        'monthly_revenue' => $pdo->query("SELECT SUM(amount) FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE())")->fetchColumn(),
        'pending_payments' => $pdo->query("SELECT COUNT(*) FROM pensions WHERE status != 'complet'")->fetchColumn(),
        'avg_payment' => $pdo->query("SELECT AVG(amount) FROM payments")->fetchColumn()
    ];

    // Derniers paiements
    $recent_payments = $pdo->query("
        SELECT p.id, p.amount, p.payment_date, p.payment_method,
               CONCAT(u.first_name, ' ', u.last_name) AS student_name,
               f.name AS formation
        FROM payments p
        JOIN pensions pe ON p.pension_id = pe.id
        JOIN students s ON pe.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON pe.formation_id = f.id
        ORDER BY p.payment_date DESC
        LIMIT 5
    ")->fetchAll();

    // Paiements par mois pour le graphique
    $monthly_payments = $pdo->query("
        SELECT MONTH(payment_date) AS month, SUM(amount) AS total
        FROM payments
        WHERE YEAR(payment_date) = YEAR(CURDATE())
        GROUP BY MONTH(payment_date)
        ORDER BY month
    ")->fetchAll(PDO::FETCH_KEY_PAIR);

    // Remplir les mois manquants avec 0
    $payments_data = array_fill(1, 12, 0);
    foreach ($monthly_payments as $month => $total) {
        $payments_data[$month] = $total;
    }

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>

<main class="finance-container">
    <h1><i class="fas fa-chart-line"></i> Tableau de Bord Financier</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <section class="finance-stats">
        <div class="stat-card bg-success">
            <h3><?= number_format($stats['annual_revenue'] ?? 0, 2, ',', ' ') ?> FCFA</h3>
            <p>Revenu annuel</p>
        </div>
        <div class="stat-card bg-primary">
            <h3><?= number_format($stats['monthly_revenue'] ?? 0, 2, ',', ' ') ?> FCFA</h3>
            <p>Revenu mensuel</p>
        </div>
        <div class="stat-card bg-warning">
            <h3><?= $stats['pending_payments'] ?? 0 ?> </h3>
            <p>  Paiements en attente</p>
        </div>
        <div class="stat-card bg-info">
            <h3><?= number_format($stats['avg_payment'] ?? 0, 2, ',', ' ') ?> FCFA</h3>
            <p>Moyenne par paiement</p>
        </div>
    </section>

    <section class="finance-charts">
        <div class="chart-container">
            <h2><i class="fas fa-dollar-sign"></i> Revenus par mois</h2>
            <canvas id="paymentsChart" height="300"></canvas>
        </div>
    </section>

    <section class="recent-payments">
        <h2><i class="fas fa-history"></i> Derniers Paiements</h2>
        <table class="finance-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Étudiant</th>
                    <th>Formation</th>
                    <th>Montant</th>
                    <th>Méthode</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_payments as $payment): ?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($payment['payment_date'])) ?></td>
                    <td><?= htmlspecialchars($payment['student_name']) ?></td>
                    <td><?= htmlspecialchars($payment['formation']) ?></td>
                    <td><?= number_format($payment['amount'], 2, ',', ' ') ?> FCFA</td>
                    <td><?= htmlspecialchars($payment['payment_method']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table><br><br>
        <a href="payments_list.php" class="btn btn-primary">Voir tous les paiements</a>
    </section>
</main>

<script src="assets/js/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des paiements
    const ctx = document.getElementById('paymentsChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Revenus (FCFA)',
                data: <?= json_encode(array_values($payments_data)) ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return value + 'FCFA';
                        }
                    }
                }
            }
        }
    });
});
</script>

<?php include_once 'includes/footer.php'; ?>
<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits admin
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: login.php');
    exit();
}

$pageTitle = "Panneau d'Administration - CFP-CMD";
include_once 'includes/header2.php';

// Statistiques pour le panel admin
try {
    $stats = [
        'total_users' => $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn(),
        'active_users' => $pdo->query("SELECT COUNT(*) FROM users WHERE is_active = 1")->fetchColumn(),
        'recent_logins' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn(),
        'pending_messages' => $pdo->query("SELECT COUNT(*) FROM contacts WHERE status = 'new'")->fetchColumn()
    ];

    // Derniers utilisateurs inscrits
    $recent_users = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email, r.name as role, u.last_login
        FROM users u
        JOIN roles r ON u.role_id = r.id
        ORDER BY u.created_at DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    $error = "Erreur lors du chargement des données: " . $e->getMessage();
}
?>

<main class="admin-container">
    <h1><i class="fas fa-cogs"></i> Panneau d'Administration</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <section class="admin-stats">
        <div class="stat-card">
            <h3><?= $stats['total_users'] ?? 0 ?></h3>
            <p>Utilisateurs</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['active_users'] ?? 0 ?></h3>
            <p>Actifs</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['recent_logins'] ?? 0 ?></h3>
            <p>Connexions (7j)</p>
        </div>
        <div class="stat-card">
            <h3><?= $stats['pending_messages'] ?? 0 ?></h3>
            <p>Messages non lus</p>
        </div>
    </section>

    <section class="admin-sections">
        <div class="admin-section">
            <h2><i class="fas fa-users"></i> Gestion des Utilisateurs</h2>
            <div class="section-content">
                <a href="user_management.php" class="btn btn-primary">Liste des utilisateurs</a>
                <a href="create_user.php" class="btn btn-secondary">Créer un utilisateur</a>
            </div>
        </div>

        <div class="admin-section">
            <h2><i class="fas fa-graduation-cap"></i> Gestion des Étudiants</h2>
            <div class="section-content">
                <a href="student_management.php" class="btn btn-primary">Liste des étudiants</a>
                <a href="student_registration.php" class="btn btn-secondary">Nouvelle inscription</a>
            </div>
        </div>

        <?php if ($_SESSION['role'] === 'super_admin'): ?>
        <div class="admin-section">
            <h2><i class="fas fa-server"></i> Paramètres Système</h2>
            <div class="section-content">
                <a href="system_settings.php" class="btn btn-primary">Configuration</a>
                <a href="database_backup.php" class="btn btn-secondary">Sauvegarde BD</a>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="recent-users">
        <h2><i class="fas fa-user-clock"></i> Utilisateurs Récents</h2>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Rôle</th>
                    <th>Dernière connexion</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></td>
                    <td><?= htmlspecialchars($user['email']) ?></td>
                    <td><?= htmlspecialchars($user['role']) ?></td>
                    <td><?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Jamais' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</main>

<?php include_once 'includes/footer.php'; ?>
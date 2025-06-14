<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Accès réservé au super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = "Paramètres Système - CFP-CMD";
include_once 'includes/header2.php';

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        foreach ($_POST['settings'] as $key => $value) {
            $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
            $stmt->execute([$value, $key]);
        }
        $success = "Paramètres mis à jour avec succès";
        logEvent("Paramètres système modifiés par " . $_SESSION['username']);
    } catch (PDOException $e) {
        $error = "Erreur lors de la mise à jour: " . $e->getMessage();
    }
}

// Récupération des paramètres
try {
    $settings = $pdo->query("SELECT * FROM settings WHERE is_public = FALSE")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $error = "Erreur lors du chargement des paramètres: " . $e->getMessage();
}
?>

<main class="settings-container">
    <h1><i class="fas fa-server"></i> Paramètres Système</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="settings-group">
            <h2><i class="fas fa-lock"></i> Sécurité</h2>
            <div class="form-group">
                <label for="password_expiry_days">Expiration des mots de passe (jours)</label>
                <input type="number" id="password_expiry_days" name="settings[password_expiry_days]" 
                       value="<?= htmlspecialchars($settings['password_expiry_days'] ?? '90') ?>" min="0">
            </div>
        </div>

        <div class="settings-group">
            <h2><i style="color: blue;" class="fas fa-database"></i> Base de Données</h2>
            <div class="form-group">
                <label for="backup_frequency">Fréquence des sauvegardes (jours)</label>
                <input type="number" id="backup_frequency" name="settings[backup_frequency]" 
                       value="<?= htmlspecialchars($settings['backup_frequency'] ?? '7') ?>" min="1">
            </div>
        </div>

        <div class="settings-group">
            <h2><i style="color: blue;" class="fas fa-bell"></i> Notifications</h2>
            <div class="form-group">
                <label for="notification_email">Email pour les notifications</label>
                <input type="email" id="notification_email" name="settings[notification_email]" 
                       value="<?= htmlspecialchars($settings['notification_email'] ?? '') ?>">
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Enregistrer les modifications</button>
    </form>

    <div class="danger-zone">
        <h2><i style="color: red;" class="fas fa-exclamation-triangle"></i> Zone de Danger</h2>
        <div class="danger-actions">
            <a href="database_backup.php" class="btn btn-secondary">Sauvegarder la base de données</a>
            <a href="system_logs.php" class="btn btn-secondary">Voir les logs système</a>
            <button class="btn btn-danger" onclick="if (confirm('Êtes-vous sûr?')) { window.location='clear_cache.php'; }">
                Vider le cache
            </button>
        </div>
    </div>
</main>

<?php include_once 'includes/footer.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = "Sauvegarde de la Base de Données - CFP-CMD";
include_once 'includes/header2.php';

// Dossier de sauvegarde
$backup_dir = '/backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0755, true);
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $backup_file = $backup_dir . 'cfpcmd_backup_' . date('Y-m-d_H-i-s') . '.sql';
        
        // Commande mysqldump
        $command = "mysqldump --user=" . DB_USER . " --password=" . DB_PASS . " --host=" . DB_HOST . " " . DB_NAME . " > " . $backup_file;
        system($command, $output);
        
        if ($output === 0) {
            $success = "Sauvegarde réussie : " . basename($backup_file);
            logEvent("Sauvegarde BD effectuée: " . basename($backup_file));
        } else {
            throw new Exception("Erreur lors de la sauvegarde (code $output)");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        logEvent("Échec sauvegarde BD: " . $error);
    }
}

// Liste des sauvegardes existantes
$backups = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir, SCANDIR_SORT_DESCENDING);
    foreach ($files as $file) {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            $backups[] = [
                'name' => $file,
                'path' => $backup_dir . $file,
                'size' => filesize($backup_dir . $file),
                'date' => date('d/m/Y H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
}
?>

<main class="backup-container">
    <h1><i style="color: red;" class="fas fa-database"></i> Sauvegarde de la Base de Données</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <section class="backup-actions">
        <h2><i style="color: green;" class="fas fa-plus-circle"></i> Nouvelle Sauvegarde</h2>
        <form method="POST">
            <p>Créez une nouvelle sauvegarde complète de la base de données.</p>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Exécuter la sauvegarde
            </button>
        </form>
    </section>

    <section class="existing-backups">
        <h2><i style="color: blue;" class="fas fa-history"></i> Sauvegardes Existantes</h2>
        
        <?php if (count($backups) > 0): ?>
            <table class="backups-table">
                <thead>
                    <tr>
                        <th>Nom du fichier</th>
                        <th>Date</th>
                        <th>Taille</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backups as $backup): ?>
                    <tr>
                        <td><?= htmlspecialchars($backup['name']) ?></td>
                        <td><?= $backup['date'] ?></td>
                        <td><?= formatSizeUnits($backup['size']) ?></td>
                        <td>
                            <a href="download_backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-secondary">
                                <i class="fas fa-download"></i> Télécharger
                            </a>
                            <a href="restore_backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-warning" onclick="return confirm('Restaurer cette sauvegarde?')">
                                <i class="fas fa-undo"></i> Restaurer
                            </a>
                            <a href="delete_backup.php?file=<?= urlencode($backup['name']) ?>" class="btn btn-sm btn-danger" onclick="return confirm('Supprimer définitivement?')">
                                <i class="fas fa-trash"></i> Supprimer
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="no-backups">Aucune sauvegarde disponible</p>
        <?php endif; ?>
    </section>
</main>

<?php 
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}

include_once 'includes/footer.php'; 
?>
<?php

require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits super admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    header('Location: login.php');
    exit();
}

$pageTitle = "Vider le Cache - CFP-CMD";
include_once 'includes/header2.php';

// Dossier de cache
$cache_dir = 'cache/';
$cleared_files = 0;
$total_size = 0;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!is_dir($cache_dir)) {
            throw new Exception("Le dossier cache n'existe pas");
        }

        $files = glob($cache_dir . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                $total_size += filesize($file);
                if (unlink($file)) {
                    $cleared_files++;
                }
            }
        }

        logEvent("Cache vidé: $cleared_files fichiers supprimés (" . formatSizeUnits($total_size) . ")");
        $success = "Cache vidé avec succès : $cleared_files fichiers supprimés (" . formatSizeUnits($total_size) . ")";

    } catch (Exception $e) {
        $error = $e->getMessage();
        logEvent("Échec vidage cache: " . $error);
    }
}

// Analyse du cache
$cache_info = [
    'files_count' => 0,
    'total_size' => 0
];

if (is_dir($cache_dir)) {
    $files = glob($cache_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $cache_info['files_count']++;
            $cache_info['total_size'] += filesize($file);
        }
    }
}
?>

<main class="cache-container">
    <h1><i class="fas fa-broom"></i> Vider le Cache</h1>
    
    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?= $success ?></div>
    <?php elseif (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <section class="cache-info">
        <h2><i class="fas fa-info-circle"></i> État Actuel du Cache</h2>
        <div class="info-cards">
            <div class="info-card">
                <h3><?= $cache_info['files_count'] ?></h3>
                <p>Fichiers en cache</p>
            </div>
            <div class="info-card">
                <h3><?= formatSizeUnits($cache_info['total_size']) ?></h3>
                <p>Espace utilisé</p>
            </div>
        </div>
    </section>

    <section class="cache-actions">
        <h2><i class="fas fa-trash-alt"></i> Actions</h2>
        <form method="POST" onsubmit="return confirm('Vider tout le cache système? Cette action est irréversible.')">
            <p>Supprime tous les fichiers temporaires du système pour libérer de l'espace et résoudre d'éventuels problèmes de cache.</p>
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-broom"></i> Vider le Cache
            </button>
        </form>
    </section>

    <section class="cache-details">
        <h2><i class="fas fa-cogs"></i> Détails Techniques</h2>
        <div class="technical-info">
            <p><strong>Emplacement du cache :</strong> <?= realpath($cache_dir) ?: $cache_dir ?></p>
            <p><strong>Dernier nettoyage :</strong> <?= date('d/m/Y H:i:s') ?></p>
            <p><strong>Type de fichiers :</strong> Données temporaires, miniatures, résultats de requêtes</p>
        </div>
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
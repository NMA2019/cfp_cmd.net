<?php
require_once 'config/db_connection.php';
require_once 'config/auth.php';

// Vérification des droits d'accès
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header('Location: unauthorized.php');
    exit();
}

// Récupération du contact
$contact = null;
if (isset($_GET['id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM contacts WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $contact = $stmt->fetch();

        if (!$contact) {
            throw new Exception("Contact non trouvé");
        }

        // Marquer comme lu si c'est un nouvel message
        if ($contact['status'] === 'new') {
            $pdo->prepare("UPDATE contacts SET status = 'read' WHERE id = ?")->execute([$_GET['id']]);
        }
    } catch (PDOException $e) {
        $error = "Erreur base de données : " . $e->getMessage();
        logEvent("Erreur contact_details: " . $e->getMessage());
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
} else {
    $error = "Aucun contact spécifié";
}

$pageTitle = "Détails du Contact - CFP-CMD";
include 'includes/header2.php';
?>

<main class="container">
    <div class="card">
        <div class="card-header">
            <h1><i class="fas fa-envelope-open-text"></i> Détails du message</h1>
            <a href="dashboard.php" class="btn btn-outline">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php elseif ($contact): ?>
            <div class="card-body">
                <div class="contact-details">
                    <div class="detail-row">
                        <label>Date :</label>
                        <span><?= date('d/m/Y H:i', strtotime($contact['created_at'])) ?></span>
                    </div>
                    <div class="detail-row">
                        <label>Expéditeur :</label>
                        <span><?= htmlspecialchars($contact['email']) ?></span>
                    </div>
                    <div class="detail-row">
                        <label>Sujet :</label>
                        <span><?= htmlspecialchars($contact['sujet']) ?></span>
                    </div>
                    <div class="detail-row full-width">
                        <label>Message :</label>
                        <div class="message-content">
                            <?= nl2br(htmlspecialchars($contact['message'])) ?>
                        </div>
                    </div>
                </div>

                <div class="contact-actions">
                    <a href="mailto:<?= htmlspecialchars($contact['email']) ?>" class="btn btn-primary">
                        <i class="fas fa-reply"></i> Répondre
                    </a>
                    <form method="POST" action="api/update_contact.php" style="display: inline;">
                        <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                        <input type="hidden" name="status" value="archived">
                        <button type="submit" class="btn btn-secondary">
                            <i class="fas fa-archive"></i> Archiver
                        </button>
                    </form>
                    <?php if (isSuperAdmin()): ?>
                        <form method="POST" action="api/delete_contact.php" style="display: inline;">
                            <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                            <button type="submit" class="btn btn-danger" onclick="return confirm('Supprimer définitivement ce message ?')">
                                <i class="fas fa-trash"></i> Supprimer
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card-body">
                <p class="text-muted">Aucun message à afficher</p>
            </div>
        <?php endif; ?>
    </div>
</main>

<style>
.contact-details {
    background: #fff;
    padding: 20px;
    border-radius: 5px;
    margin-bottom: 20px;
}

.detail-row {
    display: flex;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.detail-row label {
    font-weight: 600;
    width: 120px;
    color: #555;
}

.detail-row.full-width {
    flex-direction: column;
}

.message-content {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 5px;
    margin-top: 10px;
    white-space: pre-wrap;
}

.contact-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid #eee;
}
</style>

<?php include 'includes/footer.php'; ?>
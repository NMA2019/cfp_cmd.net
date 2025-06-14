<?php
require_once __DIR__.'/config/db_connection.php';
require_once __DIR__.'/config/auth.php';

// Vérification CSRF
session_start();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token'])) {
    die("Erreur de sécurité CSRF");
}

// Limite de taux (5 pré-inscriptions/heure par IP)
$ip = $_SERVER['REMOTE_ADDR'];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM preinscription_log WHERE ip = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$stmt->execute([$ip]);
if ($stmt->fetchColumn() >= 5) {
    die("Trop de tentatives de pré-inscription. Veuillez réessayer plus tard.");
}

// Vérifier si une formation est spécifiée
$formation_id = $_GET['formation'] ?? null;

if (!$formation_id) {
    header('Location: formations.php');
    exit();
}

// Récupérer les détails de la formation
try {
    $stmt = $pdo->prepare("
        SELECT f.id, f.name, f.price, f.start_date, f.end_date, f.capacity,
               fi.name AS filiere_name, fi.code AS filiere_code,
               ft.name AS type_name, ft.code AS type_code,
               (SELECT COUNT(*) FROM students WHERE formation_id = f.id) AS current_students
        FROM formations f
        JOIN filieres fi ON f.filiere_id = fi.id
        JOIN formation_types ft ON f.type_id = ft.id
        WHERE f.id = ? AND f.is_active = TRUE
    ");

    if (!$stmt->execute([$formation_id])) {
        throw new Exception("Erreur lors de l'exécution de la requête");
    }

    $formation = $stmt->fetch();

    if (!$formation) {
        throw new Exception("Formation non disponible ou introuvable");
    }

    // Vérifier la capacité
    if ($formation['capacity'] && $formation['current_students'] >= $formation['capacity']) {
        throw new Exception("Cette formation a atteint sa capacité maximale");
    }

} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header('Location: formations.php');
    exit();
}

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validation des champs obligatoires
        $required = ['first_name', 'last_name', 'email', 'phone', 'date_of_birth', 'niveau_scolaire', 'csrf_token'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Le champ $field est obligatoire");
            }
        }

        // Validation email
        if (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email invalide");
        }

        // Validation téléphone (format international simplifié)
        if (!preg_match('/^\+?[0-9]{10,15}$/', $_POST['phone'])) {
            throw new Exception("Numéro de téléphone invalide");
        }

        // Validation date de naissance (au moins 16 ans)
        $today = new DateTime();
        $birthdate = new DateTime($_POST['date_of_birth']);
        $age = $today->diff($birthdate)->y;
        
        if ($age < 16) {
            throw new Exception("Vous devez avoir au moins 16 ans pour vous inscrire");
        }

        // Vérification si l'email existe déjà
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$_POST['email']]);
        $user_id = $stmt->fetchColumn();

        $pdo->beginTransaction();

        // Journalisation de la tentative
        $stmt = $pdo->prepare("INSERT INTO preinscription_log (ip, email, formation_id) VALUES (?, ?, ?)");
        $stmt->execute([$ip, $_POST['email'], $formation_id]);

        if ($user_id) {
            // Vérifier si déjà inscrit à cette formation
            $stmt = $pdo->prepare("SELECT id FROM students WHERE user_id = ? AND formation_id = ?");
            $stmt->execute([$user_id, $formation_id]);
            if ($stmt->fetch()) {
                throw new Exception("Vous êtes déjà pré-inscrit à cette formation");
            }
        } else {
            // Créer un nouvel utilisateur
            $stmt = $pdo->prepare("
                INSERT INTO users (role_id, first_name, last_name, email, phone, password, is_active)
                VALUES (4, ?, ?, ?, ?, '', FALSE)
            ");
            $stmt->execute([
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['email'],
                $_POST['phone']
            ]);
            $user_id = $pdo->lastInsertId();
        }

        // Créer la pré-inscription
        $matricule = date('Y') . '-' . $formation['filiere_code'] . '-' . $user_id;
        
        $stmt = $pdo->prepare("
            INSERT INTO students (user_id, matricule, date_of_birth, gender, cin, niveau_scolaire, formation_id, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'preinscript')
        ");
        $stmt->execute([
            $user_id,
            $matricule,
            $_POST['date_of_birth'],
            $_POST['gender'] ?? null,
            $_POST['cin'] ?? null,
            $_POST['niveau_scolaire'],
            $formation_id
        ]);
        $student_id = $pdo->lastInsertId();

        // Créer la pension
        $stmt = $pdo->prepare("
            INSERT INTO pensions (student_id, formation_id, total_amount, paid_amount, status)
            VALUES (?, ?, ?, 0, 'non_paye')
        ");
        $stmt->execute([$student_id, $formation_id, $formation['price']]);
        $pension_id = $pdo->lastInsertId();

        // Générer PDF (fonction simplifiée)
        $pdf_path = generateConfirmationPDF($user_id, $student_id, $formation);

        // Envoyer email de confirmation
        sendConfirmationEmail(
            $_POST['email'],
            $_POST['first_name'],
            $formation['name'],
            $pension_id,
            $pdf_path
        );

        $pdo->commit();

        $_SESSION['success'] = "Votre pré-inscription a été enregistrée avec succès!";
        header('Location: preinscription_confirmation.php?id=' . $user_id);
        exit();

    } catch (Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}

// Générer un token CSRF
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;

$pageTitle = "Pré-inscription - " . htmlspecialchars($formation['name']);
include __DIR__.'/includes/header.php';
?>

<!-- [Le reste du formulaire HTML reste inchangé mais ajoutez ceci dans le formulaire] -->
<input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

<?php
// Fonctions supplémentaires
function generateConfirmationPDF($user_id, $student_id, $formation) {
    $pdf_path = "assets/preinscriptions/preinscription_$student_id.pdf";
    // Ici, utiliser une librairie comme TCPDF ou Dompdf pour générer le PDF
    // Ceci est une implémentation simplifiée
    file_put_contents($pdf_path, "Confirmation de pré-inscription #$student_id");
    return $pdf_path;
}

function sendConfirmationEmail($to, $firstName, $formationName, $pensionId, $pdf_path) {
    $subject = "Confirmation de votre pré-inscription à $formationName";
    
    $message = "
    <html>
    <head>
        <title>$subject</title>
    </head>
    <body>
        <h2>Bonjour $firstName,</h2>
        <p>Votre pré-inscription à la formation <strong>$formationName</strong> a bien été enregistrée.</p>
        <p>Votre numéro de dossier est : <strong>PREF-$pensionId</strong></p>
        
        <h3>Prochaines étapes :</h3>
        <ol>
            <li>Notre équipe va examiner votre dossier</li>
            <li>Vous recevrez un email pour finaliser votre inscription</li>
            <li>Préparez les documents nécessaires (CNI, diplômes)</li>
        </ol>
        
        <p>Vous trouverez ci-joint votre confirmation de pré-inscription.</p>
        
        <p>Cordialement,<br>L'équipe du CFP-CMD</p>
    </body>
    </html>
    ";
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: noreply@cfp-cmd.mg',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Envoi avec pièce jointe (simplifié)
    $boundary = md5(time());
    $headers[] = "Content-Type: multipart/mixed; boundary=\"$boundary\"";
    
    $body = "--$boundary\r\n" .
            "Content-Type: text/html; charset=\"utf-8\"\r\n" .
            "Content-Transfer-Encoding: 8bit\r\n\r\n" .
            $message . "\r\n";
    
    $body .= "--$boundary\r\n" .
             "Content-Type: application/pdf; name=\"Confirmation_$pensionId.pdf\"\r\n" .
             "Content-Transfer-Encoding: base64\r\n" .
             "Content-Disposition: attachment\r\n\r\n" .
             chunk_split(base64_encode(file_get_contents($pdf_path))) . "\r\n";
    
    $body .= "--$boundary--";
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

$pageTitle = "Pré-inscription - " . htmlspecialchars($formation['name']);
include __DIR__.'/includes/header.php';
?>

<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h2 class="mb-0">
                        <i class="fas fa-user-graduate"></i> Pré-inscription : <?= htmlspecialchars($formation['name']) ?>
                    </h2>
                </div>
                <div class="card-body">
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <div class="formation-info mb-4 p-3 bg-light rounded">
                        <h4>Détails de la formation</h4>
                        <ul class="list-unstyled">
                            <li><strong>Filière :</strong> <?= htmlspecialchars($formation['filiere_name']) ?></li>
                            <li><strong>Type :</strong> <?= htmlspecialchars($formation['type_name']) ?></li>
                            <li><strong>Période :</strong> du <?= date('d/m/Y', strtotime($formation['start_date'])) ?> au <?= date('d/m/Y', strtotime($formation['end_date'])) ?></li>
                            <li><strong>Coût :</strong> <?= number_format($formation['price'], 0, ',', ' ') ?> FCFA</li>
                        </ul>
                    </div>

                    <form method="POST" id="preinscriptionForm">
                        <h4 class="mb-3">Informations personnelles</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="first_name" class="form-label">Prénom *</label>
                                <input type="text" class="form-control" id="first_name" name="first_name" 
                                       value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="last_name" class="form-label">Nom *</label>
                                <input type="text" class="form-control" id="last_name" name="last_name" 
                                       value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="phone" class="form-label">Téléphone *</label>
                                <input type="tel" class="form-control" id="phone" name="phone" 
                                       value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="date_of_birth" class="form-label">Date de naissance *</label>
                                <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" 
                                       value="<?= htmlspecialchars($_POST['date_of_birth'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Genre</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="M" <?= isset($_POST['gender']) && $_POST['gender'] === 'M' ? 'selected' : '' ?>>Masculin</option>
                                    <option value="F" <?= isset($_POST['gender']) && $_POST['gender'] === 'F' ? 'selected' : '' ?>>Féminin</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cin" class="form-label">CIN/Passport</label>
                                <input type="text" class="form-control" id="cin" name="cin" 
                                       value="<?= htmlspecialchars($_POST['cin'] ?? '') ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="niveau_scolaire" class="form-label">Niveau scolaire *</label>
                                <input type="text" class="form-control" id="niveau_scolaire" name="niveau_scolaire" 
                                       value="<?= htmlspecialchars($_POST['niveau_scolaire'] ?? '') ?>" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="conditions" required>
                                <label class="form-check-label" for="conditions">
                                    J'accepte les <a href="#" data-bs-toggle="modal" data-bs-target="#conditionsModal">conditions générales</a> *
                                </label>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end mt-4">
                            <a href="formations.php" class="btn btn-outline-secondary me-md-2">Annuler</a>
                            <button type="submit" class="btn btn-primary">Soumettre la pré-inscription</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Conditions -->
<div class="modal fade" id="conditionsModal" tabindex="-1" aria-labelledby="conditionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="conditionsModalLabel">Conditions générales de pré-inscription</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <?php include __DIR__.'/includes/conditions_inscription.html'; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">J'ai compris</button>
            </div>
        </div>
    </div>
</div>

<script>
// Validation du formulaire
document.getElementById('preinscriptionForm').addEventListener('submit', function(e) {
    const today = new Date();
    const birthDate = new Date(document.getElementById('date_of_birth').value);
    const age = today.getFullYear() - birthDate.getFullYear();
    
    if (age < 16) {
        e.preventDefault();
        alert('Vous devez avoir au moins 16 ans pour vous inscrire');
        return false;
    }
    
    if (!document.getElementById('conditions').checked) {
        e.preventDefault();
        alert('Vous devez accepter les conditions générales');
        return false;
    }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
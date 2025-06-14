<?php
require_once __DIR__.'/config/db_connection.php';
$pageTitle = "Contact - CFP-CMD";
include __DIR__.'/includes/header.php';
 
// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = htmlspecialchars($_POST['nom']);
    $email = htmlspecialchars($_POST['email']);
    $sujet = htmlspecialchars($_POST['sujet']);
    $message = htmlspecialchars($_POST['message']);

    try {
        $stmt = $pdo->prepare("INSERT INTO contacts (nom, email, sujet, message) VALUES (?, ?, ?, ?)");
        $stmt->execute([$nom, $email, $sujet, $message]);
        $success = "Votre message a été envoyé avec succès !";
    } catch (PDOException $e) {
        $error = "Erreur lors de l'envoi du message : " . $e->getMessage();
    }
}
?>

<main class="main-content">
    <!-- Banner Section -->
    <section class="page-banner" style="background-image: url('assets/images/contact-banner.jpg')">
        <div class="container"> 
            <h1>Contactez-nous</h1>
            <p>Nous sommes à votre écoute pour toute question ou demande d'information</p>
        </div>
    </section>

    <!-- Contact Grid -->
    <section class="contact-section section-padding">
        <div class="container">
            <div class="row">
                <div class="col-lg-6">
                    <h2 class="section-title">Envoyez-nous un message</h2>
                    <?php if (isset($success)) : ?>
                        <div class="alert alert-success"><?= $success ?></div>
                    <?php elseif (isset($error)) : ?>
                        <div class="alert alert-danger"><?= $error ?></div>
                    <?php endif; ?>
                    <form method="POST" class="contact-form">
                        <div class="form-group">
                            <label for="nom">Nom complet *</label>
                            <input type="text" id="nom" name="nom" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="sujet">Sujet *</label>
                            <select id="sujet" name="sujet" class="form-control" required>
                                <option value="">Sélectionnez un sujet</option>
                                <option value="Information">Demande d'information</option>
                                <option value="Inscription">Inscription à une formation</option>
                                <option value="Partenariat">Proposition de partenariat</option>
                                <option value="Autre">Autre demande</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="message">Message *</label>
                            <textarea id="message" name="message" rows="5" class="form-control" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">Envoyer le message</button>
                    </form>
                </div>
                <div class="col-lg-6">
                    <div class="contact-info">
                        <h2 class="section-title">Nos coordonnées</h2>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h3>Adresse</h3>
                                <p>Total Logpom</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone-alt"></i>
                            <div>
                                <h3>Téléphone</h3>
                                <p>+237 696 493 531</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h3>Email</h3>
                                <p>ecolemondedigital@gmail.com<</p>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h3>Horaires</h3>
                                <p>Lundi - Vendredi : 8h - 17h</p>
                                <p>Samedi : 9h - 13h</p>
                            </div>
                        </div>
                    </div>
                   <!-- <div class="social-links">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                    </div> -->
                </div>
            </div>
        </div>
    </section>

    <!-- Google Map -->
    <section class="map-section">
        <div class="container-fluid p-0">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d254706.33817586195!2d9.715711357488436!3d4.0638834713808105!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x10610da68f8640f3%3A0x8618dc8446ea0114!2sCentre%20de%20Formation%20Professionnelle%20du%20Commerce%20et%20du%20Monde%20Digital!5e0!3m2!1sfr!2scm!4v1749582473899!5m2!1sfr!2scm" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>
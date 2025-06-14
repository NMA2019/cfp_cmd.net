<?php
require_once __DIR__.'/config/db_connection.php';
$pageTitle = "À Propos - CFP-CMD";
include __DIR__.'/includes/header.php';
?>

<main class="main-content">
    <!-- Banner Section -->
    <section class="page-banner" style="background-image: url('assets/images/about-banner.jpg')">
        <div class="container">
            <h1>À Propos du CFP-CMD</h1>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
                    <li class="breadcrumb-item active" aria-current="page">À Propos</li>
                </ol>
            </nav>
        </div>
    </section>

    <!-- Histoire du Centre -->
    <section class="section-padding">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h2 class="section-title">Notre Histoire</h2>
                    <p>Fondé en 2010, le Centre de Formation Professionnelle du Commerce et du Marketing Digital (CFP-CMD) s'est imposé comme un leader dans la formation professionnelle en Afrique de l'Ouest. Notre mission est de fournir des compétences pratiques adaptées aux besoins du marché.</p>
                    <div class="timeline">
                        <div class="timeline-item">
                            <h4>2010</h4>
                            <p>Création avec 2 filières et 50 étudiants</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2015</h4>
                            <p>Accréditation par le Ministère de l'Éducation</p>
                        </div>
                        <div class="timeline-item">
                            <h4>2020</h4>
                            <p>Lancement des formations en Marketing Digital</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <img src="assets/images/history.jpg" alt="Histoire du CFP-CMD" class="img-fluid rounded shadow">
                </div>
            </div>
        </div>
    </section>

    <!-- Valeurs -->
    <section class="values-section bg-light">
        <div class="container">
            <h2 class="section-title text-center">Nos Valeurs</h2>
            <div class="values-grid">
                <div class="value-card">
                    <i class="fas fa-graduation-cap"></i>
                    <h3>Excellence</h3>
                    <p>Nous visons l'excellence académique et professionnelle dans tous nos programmes.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-hands-helping"></i>
                    <h3>Intégrité</h3>
                    <p>Transparence et éthique guident toutes nos actions et décisions.</p>
                </div>
                <div class="value-card">
                    <i class="fas fa-lightbulb"></i>
                    <h3>Innovation</h3>
                    <p>Nous adoptons les dernières technologies et méthodes pédagogiques.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Partenaires -->
    <section class="partners-section">
        <div class="container">
            <h2 class="section-title text-center">Nos Partenaires</h2>
            <div class="partners-slider">
                <?php
                $partenaires = $pdo->query("SELECT * FROM partenaires WHERE statut = 'Actif'")->fetchAll();
                foreach ($partenaires as $partenaire) :
                ?>
                    <div class="partner-item">
                        <img src="assets/images/partenaires/<?= $partenaire['logo'] ?>" alt="<?= $partenaire['nom'] ?>">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Équipe -->
    <section class="team-section">
        <div class="container">
            <h2 class="section-title text-center">Notre Équipe</h2>
            <div class="team-grid">
                <?php
                $equipe = $pdo->query("SELECT * FROM staff WHERE categorie = 'Formateur' LIMIT 4")->fetchAll();
                foreach ($equipe as $membre) :
                ?>
                    <div class="team-card">
                        <div class="team-img">
                            <img src="assets/images/staff/<?= $membre['photo'] ?>" alt="<?= $membre['noms'] ?>">
                        </div>
                        <div class="team-info">
                            <h3><?= $membre['noms'] ?></h3>
                            <p><?= $membre['fonction'] ?></p>
                            <div class="social-links">
                                <a href="#"><i class="fab fa-linkedin"></i></a>
                                <a href="#"><i class="fab fa-twitter"></i></a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>

<?php include 'includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Slider partenaires
        $('.partners-slider').slick({
            dots: false,
            infinite: true,
            speed: 300,
            slidesToShow: 4,
            slidesToScroll: 1,
            autoplay: true,
            autoplaySpeed: 2000,
            responsive: [{
                breakpoint: 768,
                settings: {
                    slidesToShow: 2
                }
            }]
        });
    });
</script>
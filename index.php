<?php
require_once __DIR__.'/config/db_connection.php';
$pageTitle = "Accueil - CFP-CMD";
include __DIR__.'/includes/header.php';

// Récupérer les dernières actualités
try {
    $stmt = $pdo->query("SELECT * FROM actualites ORDER BY date_publication DESC LIMIT 2");
    $actualites = $stmt->fetchAll();
} catch (PDOException $e) {
    $actualites = [];
    logEvent("Erreur récupération actualités: " . $e->getMessage());
}
?>

<main class="main-content">
    <!-- Hero Banner -->
    <section class="hero-banner">
        <div class="hero-slider">
            <div class="slide" style="background-image: url('assets/images/slider1.jpg')">
                <div class="slide-content">
                    <h2>Formation Professionnelle d'Excellence</h2>
                    <p>Découvrez nos programmes adaptés aux besoins du marché</p>
                    <a href="formations.php" class="btn btn-primary btn-lg">Voir nos formations</a>
                </div>
            </div>
            <div class="slide" style="background-image: url('assets/images/slider2.jpg')">
                <div class="slide-content">
                    <h2>Insertion Professionnelle Garantie</h2>
                    <p>85% de nos étudiants trouvent un emploi dans les 6 mois</p>
                    <a href="temoignages.php" class="btn btn-primary btn-lg">Lire les témoignages</a>
                </div>
            </div>
            <div class="slide" style="background-image: url('assets/images/slider3.jpg')">
                <div class="slide-content">
                    <h2>Une Equipe d'Experts Chevronés</h2>
                    <p>80% de cours orientés pratiques avec des réalisation concretes</p>
                    <a href="experts.php" class="btn btn-primary btn-lg">Consulter Nos Experts</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Statistiques -->
    <section class="stats-section">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item">
                    <i class="fas fa-user-graduate"></i>
                    <h3><span class="counter" data-target="1250">0</span>+</h3>
                    <p>Étudiants formés</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-chalkboard-teacher"></i>
                    <h3><span class="counter" data-target="45">0</span>+</h3>
                    <p>Formateurs experts</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-briefcase"></i>
                    <h3><span class="counter" data-target="65">0</span>%</h3>
                    <p>Taux d'insertion</p>
                </div>
                <div class="stat-item">
                    <i class="fas fa-building"></i>
                    <h3><span class="counter" data-target="20">0</span>+</h3>
                    <p>Partenaires entreprises</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Formations phares -->
    <section class="featured-courses">
        <div class="container">
            <h2 class="section-title">Nos Formations Phares</h2>
            <div class="courses-grid">
                <?php
                $formations_phares = $pdo->query("SELECT * FROM formations WHERE filiere_id = 1 LIMIT 4")->fetchAll();
                foreach ($formations_phares as $formation) :
                ?>
                    <div class="course-card">
                        <div class="course-img">
                            <img src="assets/images/formations/<?= $formation['image'] ?>" alt="<?= $formation['name'] ?>">
                            <div class="course-badge"><?= $formation['start_date'] ?> </div>
                        </div>
                        <div class="course-body">
                            <h3><?= $formation['name'] ?></h3>
                            <div class="course-meta">
                                <span><i class="fas fa-money-bill-wave"></i> <?= number_format($formation['price'], 0, ',', ' ') ?> FCFA</span>
                                <span><i class="fas fa-user-friends"></i> <?= $formation['capacity'] ?> places</span>
                            </div>
                            <a href="formation.php?id=<?= $formation['id'] ?>" class="btn btn-outline-primary">Détails</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="formations.php" class="btn btn-primary">Voir toutes les formations</a>
            </div>
        </div>
    </section>

    <!-- Pourquoi nous choisir -->
    <section class="why-us">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h2 class="section-title">Pourquoi choisir le CFP-CMD ?</h2>
                    <div class="features-list">
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4>Formateurs experts</h4>
                                <p>Nos formateurs sont des professionnels en activité</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4>Pédagogie pratique</h4>
                                <p>80% de temps dédié à la pratique et aux cas concrets</p>
                            </div>
                        </div>
                        <div class="feature-item">
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <h4>Infrastructures modernes</h4>
                                <p>Salles équipées, matériel récent et espaces de coworking</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <img src="assets/images/why-us.jpg" alt="Pourquoi nous choisir" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </section>

    <!-- Témoignages -->
    <section class="testimonials">
        <div class="container">
            <h2 class="section-title">Ce que disent nos étudiants</h2>
            <div class="testimonial-slider">
                <?php
                $temoignages = $pdo->query("SELECT * FROM temoignages WHERE approuve = 1 LIMIT 3")->fetchAll();
                foreach ($temoignages as $temoignage) :
                ?>
                    <div class="testimonial-item">
                        <div class="testimonial-content">
                            <p>"<?= $temoignage['contenu'] ?>"</p>
                        </div>
                        <div class="testimonial-author">
                            <img src="assets/images/temoignages/<?= $temoignage['photo'] ?>" alt="<?= $temoignage['auteur'] ?>">
                            <div>
                                <h4><?= $temoignage['auteur'] ?></h4>
                                <p><?= $temoignage['promotion'] ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Actualités -->
    <section class="news-section">
        <div class="container">
            <h2 class="section-title">Actualités & Événements</h2>
            <div class="news-grid">
                <?php foreach ($actualites as $actu) : ?>
                    <div class="news-card">
                        <div class="news-date">
                            <span class="day"><?= date('d', strtotime($actu['date_publication'])) ?></span>
                            <span class="month"><?= strtoupper(date('M', strtotime($actu['date_publication']))) ?></span>
                        </div>
                        <div class="news-content">
                            <h3><?= $actu['titre'] ?></h3>
                            <p><?= substr($actu['resume'], 0, 100) ?>...</p>
                            <a href="actualite.php?id=<?= $actu['id'] ?>" class="read-more">Lire la suite <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="text-center mt-4">
                <a href="actualites.php" class="btn btn-outline-primary">Voir toutes les actualités</a>
            </div>
        </div>
    </section>

    <!-- CTA Inscription -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Prêt à transformer votre carrière ?</h2>
                <p>Inscrivez-vous dès maintenant à l'une de nos formations</p>
                <div class="cta-buttons">
                    <a href="formations.php" class="btn btn-light">Découvrir les formations</a>
                    <a href="contact.php" class="btn btn-outline-light">Nous contacter</a>
                </div>
            </div>
        </div>
    </section>

<?php include 'includes/footer.php'; ?>

<script>
    $(document).ready(function() {
        // Slider Hero
        $('.hero-slider').slick({
            dots: true,
            infinite: true,
            speed: 500,
            fade: true,
            cssEase: 'linear',
            autoplay: true,
            autoplaySpeed: 5000
        });

        // Slider Témoignages
        $('.testimonial-slider').slick({
            dots: true,
            infinite: true,
            speed: 300,
            slidesToShow: 1,
            centerMode: true,
            variableWidth: true,
            autoplay: true,
            autoplaySpeed: 4000
        });

        // Animation des compteurs
        $('.counter').each(function() {
            $(this).prop('Counter', 0).animate({
                Counter: $(this).data('target')
            }, {
                duration: 2000,
                easing: 'swing',
                step: function(now) {
                    $(this).text(Math.ceil(now));
                }
            });
        });
    });
</script>
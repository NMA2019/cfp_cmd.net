</main> <!-- Fermeture du main-content ouvert dans le header -->

<!-- Footer Principal -->
<footer class="main-footer">
    <div class="footer-container">
        <!-- Colonnes du footer -->
        <div class="footer-grid">
            <!-- Colonne 1 : Navigation -->
            <div class="footer-col">
                <h3 class="footer-title">Navigation</h3>
                <ul class="footer-links">
                    <li><a href="index.php"><i class="fas fa-chevron-right"></i> Accueil</a></li>
                    <li><a href="about.php"><i class="fas fa-chevron-right"></i> À propos</a></li>
                    <li><a href="formations.php"><i class="fas fa-chevron-right"></i> Formations</a></li>
                    <li><a href="contact.php"><i class="fas fa-chevron-right"></i> Contact</a></li>
                    <li><a href="blog.php"><i class="fas fa-chevron-right"></i> Blog</a></li>
                </ul>
            </div>
            
            <!-- Colonne 2 : Formations -->
            <div class="footer-col">
                <h3 class="footer-title">Formations</h3>
                <ul class="footer-links">
                    <?php
                    // Récupérer les 5 formations principales pour le footer
                    require_once 'config/db_connection.php';
                    try {
                        $formations = $pdo->query("SELECT id, name FROM formations WHERE status = 'Actif' LIMIT 5")->fetchAll();
                        foreach ($formations as $formation) {
                            echo '<li><a href="../formation.php?id='.$formation['id'].'"><i class="fas fa-chevron-right"></i> '.htmlspecialchars($formation['name']).'</a></li>';
                        }
                    } catch (PDOException $e) {
                        logEvent("Erreur récupération formations footer: " . $e->getMessage());
                    }
                    ?>
                </ul>
            </div>
            
            <!-- Colonne 3 : Contact -->
            <div class="footer-col">
                <h3 class="footer-title">Contact</h3>
                <div class="contact-info">
                    <p><i class="fas fa-map-marker-alt"></i> Total Logpom</p>
                    <p><i class="fas fa-phone"></i> +237 696 493 531</p>
                    <p><i class="fas fa-envelope"></i> ecolemondedigital@gmail.com</p>
                </div>
              <!--  <div class="social-links">
                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-linkedin-in"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                    <a href="#" class="social-icon"><i class="fab fa-youtube"></i></a>
                </div> -->
            </div>
            
            <!-- Colonne 4 : Newsletter -->
            <div class="footer-col">
                <h3 class="footer-title">Newsletter</h3>
                <p>Abonnez-vous à notre newsletter pour recevoir nos actualités.</p>
                <form class="newsletter-form">
                    <input type="email" placeholder="Votre email" required>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> S'abonner
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Copyright -->
        <div class="footer-bottom">
            <div class="copyright">
                <p>&copy; 2024 - <?php echo date("Y"); ?> | CFP-CMD - Tous droits réservés | Powered by MASTER-NMA</p>
            </div>
            <div class="legal-links">
                <a href="#">Mentions légales</a>
                <a href="#">Politique de confidentialité</a>
                <a href="#">CGU</a>
            </div>
        </div>
    </div>
</footer>

<!-- Retour en haut -->
<a href="#" class="back-to-top" id="backToTop">
    <i class="fas fa-arrow-up"></i>
</a>

<!-- Scripts JS -->
<script src="assets/js/carousel-slick.min.js"></script>
<script src="assets/js/main.js"></script>

<?php if (isset($pageJs)): ?>
    <script src="assets/js/<?php echo $pageJs; ?>"></script>
<?php endif; ?>

<script>
// Menu mobile
$(document).ready(function(){
    $('.mobile-menu-btn').click(function(){
        $('.mobile-menu').slideToggle();
    });
    
    // Back to top
    $(window).scroll(function(){
        if ($(this).scrollTop() > 300) {
            $('#backToTop').fadeIn();
        } else {
            $('#backToTop').fadeOut();
        }
    });
    
    $('#backToTop').click(function(e){
        e.preventDefault();
        $('html, body').animate({scrollTop: 0}, '300');
    });
    
    // Dropdown utilisateur
    $('.user-btn').click(function(){
        $('.dropdown-content').toggle();
    });
    
    // Fermer le dropdown si clic ailleurs
    $(document).click(function(e) {
        if (!$(e.target).closest('.user-dropdown').length) {
            $('.dropdown-content').hide();
        }
    });
});
</script>
</body>
</html>
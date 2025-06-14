<?php
require_once __DIR__.'/config/db_connection.php';
$pageTitle = "Formations - CFP-CMD";
include __DIR__.'/includes/header.php';

// Récupérer les filières
try {
    $filieres = $pdo->query("SELECT id, name, code FROM filieres WHERE name IS NOT NULL")->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

// Récupérer les types de formations
try {
    $types = $pdo->query("SELECT id, name, code FROM formation_types ORDER BY name")->fetchAll();
} catch (PDOException $e) {
    error_log($e->getMessage());
    die("Une erreur est survenue. Veuillez réessayer plus tard.");
}

// Filtrage
$filiere_id = $_GET['filiere'] ?? null;
$type_code = $_GET['type'] ?? null;

// Conditions de base (active = true)
$conditions = ["f.is_active = TRUE"];
$params = [];

if ($filiere_id) {
    $conditions[] = "f.filiere_id = :filiere_id";
    $params[':filiere_id'] = $filiere_id;
}
if ($type_code) {
    $conditions[] = "ft.code = :type_code";
    $params[':type_code'] = $type_code;
}

$where = '';
if (count($conditions) > 0) {
    $where = "WHERE " . implode(" AND ", $conditions);
}
?>

<main class="main-content">
    <!-- Banner Section -->
    <section class="page-banner" style="background-image: url('assets/images/formations-banner.jpg')">
        <div class="container">
            <h1>Nos Formations</h1>
            <p>Découvrez notre catalogue complet de formations professionnelles</p>
        </div>
    </section>

    <!-- Filtres -->
    <section class="filters-section bg-light">
        <div class="container">
            <form method="GET" class="filter-form">
                <div class="row">
                    <div class="col-md-4">
                        <label for="filiere">Filière</label>
                        <select id="filiere" name="filiere" class="form-control">
                            <option value="">Toutes les filières</option>
                            <?php foreach ($filieres as $filiere) : ?>
                                <option value="<?= htmlspecialchars($filiere['id']) ?>" <?= ($filiere_id == $filiere['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($filiere['name']) ?> (<?= htmlspecialchars($filiere['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="type">Type de formation</label>
                        <select id="type" name="type" class="form-control">
                            <option value="">Tous types</option>
                            <?php foreach ($types as $type) : ?>
                                <option value="<?= htmlspecialchars($type['code']) ?>" <?= ($type_code == $type['code']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($type['name']) ?> (<?= htmlspecialchars($type['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">Filtrer</button>
                        <a href="formations.php" class="btn btn-link ml-2">Réinitialiser</a>
                    </div>
                </div>
            </form>
        </div>
    </section>

    <!-- Liste des formations -->
    <section class="formations-list">
        <div class="container">
            <div class="row">
                <?php
                try {
                    $query = "
                        SELECT f.id, f.name, f.price, f.start_date, f.end_date,
                               fi.name AS filiere_name, fi.code AS filiere_code,
                               ft.name AS type_name, ft.code AS type_code
                        FROM formations f
                        JOIN filieres fi ON f.filiere_id = fi.id
                        JOIN formation_types ft ON f.type_id = ft.id
                        $where
                        ORDER BY f.name
                    ";

                    $stmt = $pdo->prepare($query);

                    // Bind des paramètres
                    foreach ($params as $key => $value) {
                        $stmt->bindValue($key, $value);
                    }

                    $stmt->execute();
                    $formations = $stmt->fetchAll();

                    if (empty($formations)) {
                        echo '<div class="col-12"><div class="alert alert-info">Aucune formation disponible pour le moment.</div></div>';
                    }

                    foreach ($formations as $formation) :
                        $imagePath = file_exists("assets/images/formations/{$formation['id']}.jpg") 
                            ? "assets/images/formations/{$formation['id']}.jpg" 
                            : "assets/images/formations/default.jpg";
                ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="formation-card">
                            <div class="formation-img">
                                <img src="<?= htmlspecialchars($imagePath) ?>" alt="<?= htmlspecialchars($formation['name']) ?>">
                                <span class="badge"><?= htmlspecialchars($formation['type_code']) ?></span>
                            </div>
                            <div class="formation-body">
                                <h3><?= htmlspecialchars($formation['name']) ?></h3>
                                <p class="filiere">
                                    <?= htmlspecialchars($formation['filiere_name']) ?> (<?= htmlspecialchars($formation['filiere_code']) ?>)
                                </p>
                                <div class="formation-meta">
                                    <span><i class="fas fa-calendar-alt"></i> 
                                        <?= date('d/m/Y', strtotime($formation['start_date'])) ?> - 
                                        <?= date('d/m/Y', strtotime($formation['end_date'])) ?>
                                    </span>
                                    <span><i class="fas fa-money-bill-wave"></i> 
                                        <?= number_format($formation['price'], 0, ',', ' ') ?> FCFA
                                    </span>
                                </div>
                                <div class="formation-actions mt-3">
                                    <a href="formation.php?id=<?= $formation['id'] ?>" class="btn btn-primary btn-sm">Détails</a>
                                    <a href="preinscription.php?formation=<?= $formation['id'] ?>" class="btn btn-outline-primary btn-sm">Pré-inscription</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php
                    endforeach; // Fin boucle formations
                } catch (PDOException $e) {
                    error_log($e->getMessage());
                    echo '<div class="col-12"><div class="alert alert-danger">Erreur lors du chargement des formations.</div></div>';
                }
                ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section bg-primary text-white">
        <div class="container text-center">
            <h2>Vous ne trouvez pas votre formation idéale ?</h2>
            <p class="lead">Contactez-nous pour une orientation personnalisée</p>
            <a href="contact.php" class="btn btn-light btn-lg">Nous contacter</a>
        </div>
    </section>
</main>

<?php include __DIR__.'/includes/footer.php'; ?>
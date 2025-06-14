<?php
// Initialisation des variables
$activities = $contacts = $birthdays = $soutenances = [];
$registrationsData = [];
$monthlyRegistrations = array_fill(0, 12, 0);
$stats = [
    'students' => 0, 'active_students' => 0, 'staff' => 0,
    'formations' => 0, 'revenue' => 0, 'online' => 0
];
$error = null;

// Vérification de session
require_once 'config/db_connection.php';
require_once 'config/auth.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Récupérer les informations complètes de l'utilisateur depuis la BD
try {
    $stmt = $pdo->prepare("SELECT u.*, r.name AS role_name 
                          FROM users u
                          JOIN roles r ON u.role_id = r.id
                          WHERE u.id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user) {
        throw new Exception("Utilisateur non trouvé dans la base de données");
    }

    // Mettre à jour les informations de session
    $_SESSION['username'] = $user['last_name'] . ' ' . $user['first_name'];
    $_SESSION['role'] = $user['role_name'];
    $_SESSION['photo'] = $user['photo'];

    // Statistiques générales - données réelles depuis la BD
    $stats = [
        'students' => $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn(),
        'active_students' => $pdo->query("SELECT COUNT(*) FROM students WHERE status IN ('inscrit', 'formation', 'soutenance')")->fetchColumn(),
        'staff' => $pdo->query("SELECT COUNT(*) FROM staff WHERE status = 'actif'")->fetchColumn(),
        'formations' => $pdo->query("SELECT COUNT(*) FROM formations WHERE is_active = TRUE")->fetchColumn(),
        'revenue' => $pdo->query("SELECT SUM(amount) FROM payments WHERE YEAR(payment_date) = YEAR(CURDATE())")->fetchColumn(),
        'online' => $pdo->query("SELECT COUNT(*) FROM users WHERE last_login >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) AND is_active = 1")->fetchColumn()
    ];

    // Récupérer les anniversaires du mois
    $birthdays = $pdo->query("
        SELECT s.id, CONCAT(u.first_name, ' ', u.last_name) AS name, 
               s.date_of_birth, TIMESTAMPDIFF(YEAR, s.date_of_birth, CURDATE()) AS age,
               s.photo, DATEDIFF(DATE_FORMAT(s.date_of_birth, '%Y-%m-%d'), DATE_FORMAT(CURDATE(), '%Y-%m-%d')) AS days_remaining
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE MONTH(s.date_of_birth) = MONTH(CURDATE())
        ORDER BY DAY(s.date_of_birth)
        LIMIT 5
    ")->fetchAll();

    // Récupérer les prochaines soutenances
    $soutenances = $pdo->query("
        SELECT so.id, so.presentation_date, 
               CONCAT(u.first_name, ' ', u.last_name) AS student_name,
               f.name AS formation, so.title, so.room
        FROM soutenances so
        JOIN students s ON so.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON so.formation_id = f.id
        WHERE so.status = 'planifiee' AND so.presentation_date >= CURDATE()
        ORDER BY so.presentation_date ASC
        LIMIT 5
    ")->fetchAll();

    // Récupérer les messages de contact non lus
    $contacts = $pdo->query("
        SELECT c.id, c.email, c.sujet, c.message, c.created_at
        FROM contacts c
        WHERE c.status = 'new'
        ORDER BY c.created_at DESC
        LIMIT 5
    ")->fetchAll();

    // Récupérer les inscriptions par mois pour le graphique
    $registrationsData = $pdo->query("
        SELECT 
            MONTH(inscription_date) AS month, 
            COUNT(*) AS count
        FROM students
        WHERE YEAR(inscription_date) = YEAR(CURDATE())
        GROUP BY MONTH(inscription_date)
        ORDER BY month
    ")->fetchAll();

    // Préparer les données pour le graphique
    $monthlyRegistrations = array_fill(0, 12, 0);
    foreach ($registrationsData as $data) {
        $monthlyRegistrations[$data['month'] - 1] = $data['count'];
    }

    // Récupérer les dernières activités
    $activities = $pdo->query("
        SELECT 'payment' AS type, p.payment_date AS date, 
               CONCAT('Paiement de ', p.amount, ' € par ', u.first_name, ' ', u.last_name) AS description,
               CONCAT('formation.php?id=', f.id) AS link
        FROM payments p
        JOIN pensions pe ON p.pension_id = pe.id
        JOIN students s ON pe.student_id = s.id
        JOIN users u ON s.user_id = u.id
        JOIN formations f ON pe.formation_id = f.id
        ORDER BY p.payment_date DESC
        LIMIT 5
    ")->fetchAll();

} catch (PDOException $e) {
    logEvent("Erreur dashboard: " . $e->getMessage());
    $error = "Une erreur est survenue lors du chargement des données.";
} catch (Exception $e) {
    logEvent("Erreur utilisateur: " . $e->getMessage());
    $error = $e->getMessage();
}

// Titre de la page
$pageTitle = "Tableau de Bord - CFP-CMD";
include_once 'includes/header2.php';
?>

<main class="dashboard-container">
    <!-- Section de bienvenue et notifications -->
    <section class="dashboard-header">
        <div class="welcome-message">
            <h1>Bienvenue, <?= htmlspecialchars($user['last_name'] . ' ' . htmlspecialchars($user['first_name'])) ?></h1>
            <p>Vous êtes connecté en tant que: <strong><?= htmlspecialchars($user['role_name']) ?></strong></p>
            <p>Dernière connexion: <?= $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Première connexion' ?></p>
        </div>
        
        <?php if ($user['role_name'] === 'admin' || $user['role_name'] === 'super_admin'): ?>
            <div class="admin-actions">
    <?php if (isSuperAdmin()): ?>
        <button class="btn btn-primary" onclick="openModal('eventModal')">
            <i class="fas fa-calendar-plus"></i> Créer un événement
        </button>
    <?php endif; ?>
    
    <?php if ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'): ?>
        <a href="admin_panel.php" class="btn btn-secondary">
            <i class="fas fa-cogs"></i> Panneau admin
        </a>
    <?php endif; ?>
</div>
        <?php endif; ?>

        <div class="role-specific-features">
            <?php if ($user['role_name'] === 'super_admin'): ?>
                <a href="user_management.php" class="btn btn-danger">
                    <i class="fas fa-users-cog"></i> Gestion des utilisateurs
                </a><br><br>
                <a href="system_settings.php" class="btn btn-warning">
                    <i class="fas fa-server"></i> Paramètres système
                </a>
                
            <?php elseif ($user['role_name'] === 'admin'): ?>
                <a href="student_management.php" class="btn btn-info">
                    <i class="fas fa-user-graduate"></i> Gestion des étudiants
                </a>
                <a href="finance_dashboard.php" class="btn btn-success">
                    <i class="fas fa-chart-line"></i> Tableau de bord financier
                </a>
                
            <?php elseif ($user['role_name'] === 'professeur'): ?>
                <a href="courses_management.php" class="btn btn-warning">
                    <i class="fas fa-chalkboard-teacher"></i> Mes cours
                </a>
                <a href="student_grading.php" class="btn btn-primary">
                    <i class="fas fa-check-circle"></i> Évaluation des étudiants
                </a>
                
            <?php elseif ($user['role_name'] === 'etudiant'): ?>
                <a href="my_courses.php" class="btn btn-success">
                    <i class="fas fa-book"></i> Mes formations
                </a>
                <a href="my_payments.php" class="btn btn-info">
                    <i class="fas fa-money-bill-wave"></i> Mes paiements
                </a>
            <?php endif; ?>
        </div>
    </section>

    <!-- Affichage des erreurs -->
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <!-- Section des statistiques -->
    <section class="stats-section">
        <div class="stat-card">
            <div class="stat-icon bg-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['students'] ?></h3>
                <p>Étudiants</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-success">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['active_students'] ?></h3>
                <p>Actifs</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-info">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['online'] ?></h3>
                <p>En ligne</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-warning">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['staff'] ?></h3>
                <p>Personnel</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-secondary">
                <i class="fas fa-book"></i>
            </div>
            <div class="stat-info">
                <h3><?= $stats['formations'] ?></h3>
                <p>Formations</p>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon bg-danger">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($stats['revenue'] ?? 0, 0, ',', ' ') ?> FCFA</h3>
                <p>Revenus annuels</p>
            </div>
        </div>
    </section>

    <!-- Section principale avec graphiques et calendrier -->
    <section class="dashboard-main">
        <!-- Colonne de gauche - Graphiques et anniversaires -->
        <div class="dashboard-column">
            <!-- Graphique des inscriptions -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Inscriptions par mois (<?= date('Y') ?>)</h3>
                </div>
                <div class="card-body">
                    <canvas id="registrationsChart" height="250"></canvas>
                </div>
            </div>
            
            <!-- Anniversaires du mois -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-birthday-cake"></i> Anniversaires ce mois</h3>
                </div>
                <div class="card-body">
                    <?php if (count($birthdays) > 0): ?>
                        <ul class="birthday-list">
                            <?php foreach ($birthdays as $birthday): ?>
                                <li>
                                    <img src="assets/images/users/<?= htmlspecialchars($birthday['photo'] ?? 'default.png') ?>" alt="Photo" class="avatar">
                                    <div class="birthday-info">
                                        <strong><?= htmlspecialchars($birthday['name']) ?></strong>
                                        <span><?= ($birthday['age'] + 1) ?> ans le <?= date('d/m', strtotime($birthday['date_of_birth'])) ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Aucun anniversaire ce mois-ci.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Colonne de droite - Calendrier et activités -->
        <div class="dashboard-column">
            <!-- Calendrier -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Calendrier</h3>
                </div>
                <div class="card-body">
                    <div id="dashboardCalendar"></div>
                </div>
            </div>
            
            <!-- Prochaines soutenances -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-graduation-cap"></i> Prochaines soutenances</h3>
                </div>
                <div class="card-body">
                    <?php if (count($soutenances) > 0): ?>
                        <ul class="event-list">
                            <?php foreach ($soutenances as $soutenance): ?>
                                <li>
                                    <div class="event-date">
                                        <span class="day"><?= date('d', strtotime($soutenance['presentation_date'])) ?></span>
                                        <span class="month"><?= strtoupper(date('M', strtotime($soutenance['presentation_date']))) ?></span>
                                    </div>
                                    <div class="event-info">
                                        <strong><?= htmlspecialchars($soutenance['student_name']) ?></strong>
                                        <p><?= htmlspecialchars($soutenance['title']) ?></p>
                                        <small><?= htmlspecialchars($soutenance['formation']) ?> - <?= htmlspecialchars($soutenance['room']) ?></small>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Aucune soutenance prévue.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages reçus -->
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-envelope"></i> Messages récents</h3>
                </div>
                <div class="card-body">
                    <?php 

    // Avant la ligne qui cause l'erreur
    $contacts = []; // Initialisation par défaut

    // Ou mieux, récupérez les contacts depuis la base
    try {
        $contacts = $pdo->query("SELECT * FROM contacts ORDER BY created_at DESC")->fetchAll();
    } catch (PDOException $e) {
        $contacts = [];
        logEvent("Erreur récupération contacts: " . $e->getMessage());
    }
                    if (count($contacts) > 0): ?>
                        <ul class="message-list">
                            <?php foreach ($contacts as $contact): ?>
                                <li>
                                    <div class="message-header">
                                        <strong><?= htmlspecialchars($contact['email']) ?></strong>
                                        <small><?= date('d/m/Y H:i', strtotime($contact['created_at'])) ?></small>
                                    </div>
                                    <p class="message-subject"><?= htmlspecialchars($contact['sujet']) ?></p>
                                    <p class="message-preview"><?= substr(htmlspecialchars($contact['message']), 0, 100) ?>...</p>
                                    <a href="contact_details.php?id=<?= $contact['id'] ?>" class="btn btn-sm btn-outline">Voir</a><br><br>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php else: ?>
                        <p class="text-muted">Aucun message récent.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Section des activités récentes -->
    <section class="recent-activities">
        <div class="dashboard-card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Activités récentes</h3>
                <a href="activities.php" class="btn btn-sm btn-outline">Voir tout</a>
            </div>
            <div class="card-body">
                <?php if (count($activities) > 0): ?>
                    <table class="activities-table">
                        <tbody>
                            <?php foreach ($activities as $activity): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-<?= $activity['type'] === 'payment' ? 'money-bill-wave' : 'user-graduate' ?>"></i>
                                    </td>
                                    <td>
                                        <strong><?= htmlspecialchars($activity['description']) ?></strong>
                                        <small><?= date('d/m/Y H:i', strtotime($activity['date'])) ?></small>
                                    </td>
                                    <td>
                                        <a href="<?= htmlspecialchars($activity['link']) ?>" class="btn btn-sm btn-outline">
                                            <i class="fas fa-eye"></i> Voir
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="text-muted">Aucune activité récente.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- Modal pour créer un événement -->
<div id="eventModal" class="modal">
    <div class="modal-content">
        <span class="close-modal" onclick="closeModal('eventModal')">&times;</span>
        <h2><i class="fas fa-calendar-plus"></i> Créer un nouvel événement</h2>
        
        <form id="eventForm" action="api/create_event.php" method="POST">
            <div class="form-group">
                <label for="eventTitle"><i class="fas fa-heading"></i> Titre</label>
                <input type="text" id="eventTitle" name="title" required placeholder="Titre de l'événement">
            </div><br><br>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="eventStart"><i class="fas fa-calendar-day"></i> Date de début</label>
                    <input type="datetime-local" id="eventStart" name="start" required>
                </div><br><br>
                
                <div class="form-group">
                    <label for="eventEnd"><i class="fas fa-calendar-times"></i> Date de fin</label>
                    <input type="datetime-local" id="eventEnd" name="end">
                </div>
            </div><br><br>
            
            <div class="form-group">
                <label for="eventType"><i class="fas fa-tag"></i> Type</label>
                <select id="eventType" name="type" required>
                    <option value="formation">Formation</option>
                    <option value="soutenance">Soutenance</option>
                    <option value="reunion">Réunion</option>
                    <option value="autre">Autre</option>
                </select>
            </div><br><br>
            
            <div class="form-group">
                <label for="eventDescription"><i class="fas fa-align-left"></i> Description</label>
                <textarea id="eventDescription" name="description" rows="3" placeholder="Description de l'événement"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('eventModal')">Annuler</button>
                <button type="submit" class="btn btn-primary">Créer l'événement</button>
            </div>
        </form>
    </div>
</div>

<!-- Scripts spécifiques au dashboard -->
<script src="assets/js/chart.min.js"></script>
<script src="assets/js/fullcalendar.min.js"></script>
<script src="assets/js/fr.min.js"></script>

<script>
// Initialisation des graphiques
document.addEventListener('DOMContentLoaded', function() {
    // Graphique des inscriptions
    const ctx = document.getElementById('registrationsChart').getContext('2d');
    const chart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            datasets: [{
                label: 'Inscriptions',
                data: <?= json_encode($monthlyRegistrations) ?>,
                backgroundColor: 'rgba(12, 111, 181, 0.7)',
                borderColor: 'rgba(12, 111, 181, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });

    // Calendrier
    const calendarEl = document.getElementById('dashboardCalendar');
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        events: [
            <?php foreach ($soutenances as $soutenance): ?>,
            {
                title: 'Soutenance: <?= addslashes($soutenance['student_name']) ?>',
                start: '<?= date('Y-m-d H:i:s', strtotime($soutenance['presentation_date'])) ?>',
                color: '#e31607',
                extendedProps: {
                    description: '<?= addslashes($soutenance['title']) ?>',
                    location: '<?= addslashes($soutenance['room']) ?>'
                }
            },
            <?php endforeach; ?>
        ],
        eventClick: function(info) {
            alert('Détails:\n' + info.event.extendedProps.description + '\n\nSalle: ' + info.event.extendedProps.location);
        }
    });
    calendar.render();

    // Gestion des modals
    window.openModal = function(modalId) {
        document.getElementById(modalId).style.display = 'block';
    };

    window.closeModal = function(modalId) {
        document.getElementById(modalId).style.display = 'none';
    };

    // Fermer la modal si on clique en dehors
    window.onclick = function(event) {
        if (event.target.className === 'modal') {
            event.target.style.display = 'none';
        }
    };
});

// Gestion du formulaire d'événement
document.getElementById('eventForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Ici, vous devriez ajouter le code AJAX pour envoyer les données au serveur
    alert('Événement créé avec succès!');
    closeModal('eventModal');
    this.reset();
});
</script>

<?php include 'includes/footer.php';?>
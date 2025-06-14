<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Paramètres de connexion à la base de données
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cfpgesappv4');

// Fichier de logs
define('LOG_FILE', __DIR__ . '/../logs/app.log');

// Création du répertoire logs s'il n'existe pas
if (!file_exists(dirname(LOG_FILE))) {
    mkdir(dirname(LOG_FILE), 0755, true);
}

try {
    // Connexion à la base de données via PDO
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

    // Écriture d'un log si la connexion est réussie
    logEvent("Connexion réussie à la base de données.");

} catch (PDOException $e) {
    // Écriture du message d'erreur dans les logs et affichage
    logEvent("Erreur de connexion : " . $e->getMessage());
    die("Erreur de connexion à la base de données. Vérifiez les logs.");
}

// Fonction pour écrire les logs
function logEvent($message) {
    $logFile = __DIR__ . '/../logs/app.log';
    
    // Créer le dossier logs s'il n'existe pas
    if (!file_exists(dirname($logFile))) {
        mkdir(dirname($logFile), 0755, true);
    }
    
    // Vérifier si le fichier est accessible en écriture
    if (!is_writable(dirname($logFile))) {
        error_log("Impossible d'écrire dans le dossier de logs. Vérifiez les permissions.");
        return false;
    }
    
    try {
        $timestamp = date("Y-m-d H:i:s");
        $logMessage = "[{$timestamp}] {$message}\n";
        return file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
    } catch (Exception $e) {
        error_log("Erreur d'écriture dans les logs: " . $e->getMessage());
        return false;
    }
}

// Inclure les fonctions d'authentification
require_once __DIR__.'/auth.php';

// Sécurisation des sessions - Moved to auth.php
// Déconnexion automatique - Moved to auth.php
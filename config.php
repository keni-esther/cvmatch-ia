<?php
// Désactiver l'affichage des erreurs en production plus tard, mais OK pour le debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ============================================
// CVMatch IA - Configuration INTERNE Railway
// ============================================

define('DB_HOST', 'mysql.railway.internal'); 
define('DB_PORT', '3306'); 
define('DB_USER', 'root');
define('DB_PASS', 'agLVoiIdQJqDpDycdpHSWsDRqeWwrrvB');
define('DB_NAME', 'railway'); 

define('CODE_RECRUTEUR', 'RECRUT2024');
define('IA_SERVICE_URL', 'http://localhost:5000');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

/**
 * Connexion à la base de données via PDO
 */
function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            // On utilise uniquement des options standards pour éviter les alertes PHP 8.5
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);

            // On force le charset manuellement
            $pdo->exec("SET NAMES utf8mb4");

        } catch (PDOException $e) {
            die('<div style="color:red; font-family:sans-serif; padding:20px;">
                    <b>Erreur de connexion Railway :</b> ' . htmlspecialchars($e->getMessage()) . '
                 </div>');
        }
    }
    return $pdo;
}

// --- Initialisation Session ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Fonctions Utilitaires ---

function estConnecte() {
    return isset($_SESSION['user_id']);
}

function estCandidat() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'candidat';
}

function estRecruteur() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'recruteur';
}

function rediriger($url) {
    // Vérifie si un tampon existe avant de le nettoyer
    if (ob_get_length()) {
        ob_end_clean();
    }
    header("Location: $url");
    exit();
}

function s($v) {
    return htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
}

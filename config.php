<?php
// ============================================
// CVMatch IA - Configuration (Connexion Railway)
// ============================================

// Configuration des accès Railway
define('DB_HOST', 'shortline.proxy.rlwy.net:30673'); // Adresse publique + Port
define('DB_USER', 'root');
define('DB_PASS', 'agLVoiIdQJqDpDycdpHSWsDRqeWwrrvB');
define('DB_NAME', 'railway'); // Nom de la base sur Railway

define('CODE_RECRUTEUR', 'RECRUT2024');

// URL de ton service Python/IA (à modifier si tu héberges l'IA sur Railway aussi)
define('IA_SERVICE_URL', 'http://localhost:5000');

define('UPLOAD_DIR', __DIR__ . '/uploads/');

/**
 * Initialise et retourne la connexion PDO
 */
function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
                ]
            );
        } catch (PDOException $e) {
            // Affichage propre de l'erreur
            die('<div style="font-family:Arial;padding:20px;color:red;border:1px solid red;background:#fff5f5;">
                <h3 style="margin-top:0;">Erreur de connexion à la base de données Railway</h3>
                <p><b>Détail :</b> ' . htmlspecialchars($e->getMessage()) . '</p>
                <p><i>Note : Si l\'erreur est "could not find driver", activez <b>extension=pdo_mysql</b> dans votre php.ini de XAMPP.</i></p>
            </div>');
        }
    }
    return $pdo;
}

// Initialisation de la session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- Fonctions utilitaires ---

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
    if (!headers_sent()) {
        header("Location: $url");
    } else {
        echo '<script>window.location.href="' . $url . '";</script>';
    }
    exit();
}

/**
 * Sécurise les données de sortie (XSS)
 */
function s($v) {
    return htmlspecialchars(trim((string)$v), ENT_QUOTES, 'UTF-8');
}
?>

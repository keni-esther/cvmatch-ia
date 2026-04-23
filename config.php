
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ============================================
// CVMatch IA - Configuration (Connexion Railway)
// ============================================

// Configuration des accès Railway (Extraits de ta commande MySQL)
define('DB_HOST', 'mysql.railway.internal'); // L'hôte uniquement
define('DB_PORT', '30673');                   // Le port séparé
define('DB_USER', 'root');
define('DB_PASS', 'agLVoiIdQJqDpDycdpHSWsDRqeWwrrvB');
define('DB_NAME', 'railway'); 

define('CODE_RECRUTEUR', 'RECRUT2024');

// URL de ton service Python/IA
define('IA_SERVICE_URL', 'http://localhost:5000');

define('UPLOAD_DIR', __DIR__ . '/uploads/');

function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            // On retire la constante problématique du tableau d'options
            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // On force le charset manuellement après la connexion
            $pdo->exec("SET NAMES utf8mb4");

        } catch (PDOException $e) {
            die('Erreur connexion : ' . $e->getMessage());
        }
    }
    return $pdo;
}
// Le reste de tes fonctions (session_start, estConnecte, etc.) ne change pas...

if (session_status() === PHP_SESSION_NONE)
    session_start();

function estConnecte()
{
    return isset($_SESSION['user_id']);
}
function estCandidat()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'candidat';
}
function estRecruteur()
{
    return isset($_SESSION['role']) && $_SESSION['role'] === 'recruteur';
}
function rediriger($url)
{
    ob_end_clean();
    header("Location: $url");
    exit();
}
function s($v)
{
    return htmlspecialchars(trim((string) $v), ENT_QUOTES, 'UTF-8');
}
?>

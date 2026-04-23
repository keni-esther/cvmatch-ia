
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ============================================
// CVMatch IA - Configuration (Connexion Railway)
// ============================================


// ============================================
// CVMatch IA - Configuration INTERNE Railway
// ============================================

// On utilise l'hôte interne (Network interne de Railway)
define('DB_HOST', 'mysql.railway.internal'); 
define('DB_PORT', '3306'); // EN INTERNE, LE PORT EST TOUJOURS 3306
define('DB_USER', 'root');
define('DB_PASS', 'agLVoiIdQJqDpDycdpHSWsDRqeWwrrvB');
define('DB_NAME', 'railway'); 

define('CODE_RECRUTEUR', 'RECRUT2024');
define('IA_SERVICE_URL', 'http://localhost:5000');
define('UPLOAD_DIR', __DIR__ . '/uploads/');

function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            
            // On enlève MYSQL_ATTR_INIT_COMMAND d'ici pour éviter l'avertissement
            $pdo = new PDO(
                $dsn,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );

            // On exécute la commande de charset séparément
            $pdo->exec("SET NAMES utf8mb4");

        } catch (PDOException $e) {
            die("Erreur de connexion interne : " . $e->getMessage());
        }
    }
    return $pdo;
}

// ... reste de tes fonctions ...
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

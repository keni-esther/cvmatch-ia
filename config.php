<?php
// ============================================
// CVMatch IA - Configuration
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cvmatch_ia');

define('CODE_RECRUTEUR', 'RECRUT2024');

define('IA_SERVICE_URL', 'http://localhost:5000');

define('UPLOAD_DIR', __DIR__ . '/uploads/');

function getDB()
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            die('<div style="font-family:Arial;padding:20px;color:red;">
                <b>Erreur connexion DB :</b> ' . $e->getMessage() . '<br>
                Vérifiez config.php et que la base <b>cvmatch_ia</b> existe.</div>');
        }
    }
    return $pdo;
}

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
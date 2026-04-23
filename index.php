<?php

if (!file_exists('config.php')) {
    $fichiers = scandir(__DIR__);
    die("ERREUR CRITIQUE : config.php introuvable. Fichiers vus par le serveur : " . implode(', ', $fichiers));
}
require_once "config.php";

if (estConnecte()) {
    rediriger(estRecruteur() ? 'dashboard_recruteur.php' : 'dashboard_candidat.php');
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mdp = trim($_POST['mdp'] ?? '');
    $role = $_POST['role'] ?? 'candidat';
    $code = trim($_POST['code_recruteur'] ?? '');

    if (!$email || !$mdp) {
        $err = 'Veuillez remplir tous les champs.';
    } elseif ($role === 'recruteur' && $code !== CODE_RECRUTEUR) {
        $err = "Code d'accès recruteur invalide.";
    } else {
        $db = getDB();
        if ($role === 'recruteur') {
            $stmt = $db->prepare("SELECT * FROM recruteurs WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u && password_verify($mdp, $u['mot_de_passe'])) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['nom'] = $u['nom'];
                $_SESSION['email'] = $u['email'];
                $_SESSION['entreprise'] = $u['entreprise'] ?? '';
                $_SESSION['role'] = 'recruteur';
                $_SESSION['recruteur_id'] = $u['id'];
                rediriger('dashboard_recruteur.php');
            } else {
                $err = 'Email ou mot de passe incorrect.';
            }
        } else {
            $stmt = $db->prepare("SELECT * FROM candidats WHERE email = ?");
            $stmt->execute([$email]);
            $u = $stmt->fetch();
            if ($u && password_verify($mdp, $u['mot_de_passe'])) {
                $_SESSION['user_id'] = $u['id'];
                $_SESSION['nom'] = $u['nom'];
                $_SESSION['prenom'] = $u['prenom'];
                $_SESSION['email'] = $u['email'];
                $_SESSION['role'] = 'candidat';
                $_SESSION['candidat_id'] = $u['id'];
                rediriger('dashboard_candidat.php');
            } else {
                $err = 'Email ou mot de passe incorrect.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CVMatch IA — Connexion</title>
    <link rel="stylesheet" href="style.css">
</head>

<body>
    <div class="card">
        <h1>CVMatch <span class="ia">IA</span></h1>
        <div class="sub">Plateforme de recrutement intelligent</div>

        <div class="tabs">
            <button type="button" class="tab on" id="tab-candidat" onclick="setRole('candidat')">👤 Candidat</button>
            <button type="button" class="tab" id="tab-recruteur" onclick="setRole('recruteur')">🏢 Recruteur</button>
        </div>

        <?php if ($err): ?>
            <div class="err">⚠️ <?= s($err) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="role" id="role-input" value="candidat">
            <label>Email</label>
            <input type="email" name="email" placeholder="votre@email.com" value="<?= s($_POST['email'] ?? '') ?>"
                required>
            <label>Mot de passe</label>
            <input type="password" name="mdp" placeholder="••••••••" required>

            <div id="code-recruteur-box" style="display:none">
                <label>Code d'accès recruteur</label>
                <input type="password" name="code_recruteur" id="code_recruteur" placeholder="••••••••">
            </div>

            <button type="submit" class="btn">Se connecter →</button>
        </form>

        <div class="bas">Pas de compte ? <a href="inscription.php">S'inscrire</a></div>
    </div>

    <script>
        function setRole(role) {
            document.getElementById('role-input').value = role;
            document.getElementById('tab-candidat').className = 'tab' + (role === 'candidat' ? ' on' : '');
            document.getElementById('tab-recruteur').className = 'tab' + (role === 'recruteur' ? ' on' : '');
            var box = document.getElementById('code-recruteur-box');
            var inp = document.getElementById('code_recruteur');
            if (role === 'recruteur') { box.style.display = 'block'; inp.required = true; }
            else { box.style.display = 'none'; inp.required = false; inp.value = ''; }
        }
<?php if (!empty($_POST['role'])): ?>setRole('<?= s($_POST['role']) ?>'); <?php endif; ?>
    </script>
</body>

</html>

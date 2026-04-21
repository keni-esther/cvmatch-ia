<?php
require_once 'config.php';
require_once 'mailer.php';

if (estConnecte())
    rediriger('index.php');

$err = '';
$succes = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    if (empty($email)) {
        $err = 'Veuillez saisir votre adresse email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'Adresse email invalide.';
    } else {
        $db = getDB();
        $chk = $db->prepare("SELECT id FROM recruteurs WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $err = 'Un compte recruteur existe déjà avec cet email. <a href="index.php">Connectez-vous</a>.';
        } else {
            $sujet = "Votre code d'accès recruteur — CVMatch IA";
            $corps = templateCodeRecruteur(CODE_RECRUTEUR);
            $result = envoyerEmail($email, $sujet, $corps);
            if ($result['ok']) {
                $succes = true;
            } else {
                $err = 'Erreur lors de l\'envoi : ' . ($result['erreur'] ?? 'inconnue');
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
    <title>CVMatch IA — Code recruteur</title>
    <link rel="stylesheet" href="envoie.css">
</head>

<body>
    <div class="card">
        <div class="icon">🔑</div>
        <h1>Code recruteur</h1>
        <div class="sub">CVMatch IA — Accès recruteur</div>

            <?php if ($succes): ?>
            <div class="ok">
                <div class="check">✅</div>
                <h2>Code envoyé !</h2>
                <p>Votre code d'accès a été envoyé à :</p>
                <div class="email-box">📧 <?= s($email) ?></div>
                <p>Vérifiez votre boîte mail (et les spams), puis utilisez ce code pour vous inscrire.</p>
                <a href="inscription.php?role=recruteur">S'inscrire maintenant →</a>
            </div>
            <?php else: ?>
            <div class="desc">
                📋 Pour vous inscrire en tant que <strong>recruteur</strong>, vous avez besoin d'un code d'accès.<br>
                Renseignez votre email et nous vous l'envoyons immédiatement.
            </div>

            <div class="steps">
                <div class="step">
                    <div class="num">1</div>
                    <div>Saisissez votre adresse email professionnelle</div>
                </div>
                <div class="step">
                    <div class="num">2</div>
                    <div>Recevez le code dans votre boîte mail</div>
                </div>
                <div class="step">
                    <div class="num">3</div>
                    <div>Utilisez-le pour compléter votre inscription</div>
                </div>
            </div>

                    <?php if ($err): ?>
                <div class="err">⚠️ <?= $err ?></div><?php endif; ?>

            <form method="POST">
                <label>Votre adresse email</label>
                <input type="email" name="email" placeholder="recruteur@entreprise.com" value="<?= s($email) ?>" required
                    autofocus>
                <button type="submit" class="btn">📨 Recevoir le code par email</button>
            </form>
            <?php endif; ?>

        <div class="bas">
            <a href="inscription.php?role=recruteur">← Retour à l'inscription</a>
            &nbsp;·&nbsp;
            <a href="index.php">Se connecter</a>
        </div>
    </div>
</body>

</html>
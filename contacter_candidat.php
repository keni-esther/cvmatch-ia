<?php
require_once 'config.php';
require_once 'mailer.php';

if (!estRecruteur() || !isset($_GET['cid'])) {
    http_response_code(403);
    exit('Accès refusé');
}

$cid = intval($_GET['cid']);
$db = getDB();

$stmt = $db->prepare("SELECT prenom, nom, email FROM candidats WHERE id = ?");
$stmt->execute([$cid]);
$candidat = $stmt->fetch();

if (!$candidat) {
    http_response_code(404);
    exit('Candidat introuvable');
}

$stmt = $db->prepare("SELECT entreprise, email FROM recruteurs WHERE id = ?");
$stmt->execute([$_SESSION['recruteur_id']]);
$recruteur = $stmt->fetch();
    
if (!$recruteur) {
    http_response_code(403);
    exit('Recruteur introuvable');
}

$errors = [];
$success = null;
$subject = "Invitation de " . $recruteur['entreprise'] . " - Opportunité sur CVMatch IA";
$message = "Bonjour " . $candidat['prenom'] . ",\n\nJe souhaite vous proposer une opportunité intéressante.\n\nCordialement,\n" . $recruteur['entreprise'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if ($subject === '') {
        $errors[] = 'Le sujet est requis.';
    }
    if ($message === '') {
        $errors[] = 'Le message est requis.';
    }

    if (empty($errors)) {
        $htmlBody = '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"><title>' . htmlspecialchars($subject) . '</title></head><body style="font-family:Arial,sans-serif;background:#f4f4f4;margin:0;padding:0;">'
            . '<div style="max-width:720px;margin:32px auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #ddd;box-shadow:0 12px 40px rgba(0,0,0,.08);">'
            . '<div style="background:#0d47a1;color:#fff;padding:24px;text-align:center;">'
            . '<h1 style="margin:0;font-size:22px;">Message de recrutement</h1>'
            . '<p style="margin:8px 0 0;font-size:14px;opacity:.85;">' . htmlspecialchars($recruteur['entreprise']) . ' vous contacte via CVMatch IA.</p>'
            . '</div>'
            . '<div style="padding:28px 30px;color:#333;line-height:1.6;">'
            . '<p>Bonjour <strong>' . htmlspecialchars($candidat['prenom']) . '</strong>,</p>'
            . '<div style="background:#f1f8ff;border:1px solid #cce3ff;border-radius:10px;padding:18px;margin:18px 0;white-space:pre-wrap;font-family:inherit;">'
            . nl2br(htmlspecialchars($message))
            . '</div>'
            . '<p>Pour répondre à ce message, veuillez utiliser votre client email avec l’adresse de réponse ci-dessous :</p>'
            . '<p><strong>' . htmlspecialchars($recruteur['email']) . '</strong></p>'
            . '<p>Cordialement,<br>' . htmlspecialchars($recruteur['entreprise']) . '</p>'
            . '</div>'
            . '</div>'
            . '</body></html>';

        $result = envoyerEmail($candidat['email'], $subject, $htmlBody, $recruteur['email']);

        if ($result['ok']) {
            $success = 'Le message a bien été envoyé au candidat. Il pourra répondre directement à votre adresse email.';
        } else {
            $errors[] = 'Erreur lors de l’envoi : ' . $result['erreur'];
        }
    }
}

function renderValue($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contacter le candidat</title>
    <style>
        :root { --dark: #161514; --slate: #262626; --orange: #F67011; --brown: #873800; --grey: #878787; --peach: #FFE4D0; --white: #ffffff; }
        body { margin: 0; font-family: 'DM Sans', sans-serif; background: var(--dark); color: var(--peach); }
        .page { max-width: 900px; margin: 30px auto; padding: 0 16px; }
        .card { background: var(--slate); border-radius: 18px; box-shadow: 0 28px 80px rgba(0,0,0,.5); overflow: hidden; border: 1px solid rgba(246,112,17,.16); }
        .header { background: linear-gradient(135deg, var(--orange), var(--brown)); color: var(--white); padding: 28px 32px; }
        .header h1 { margin: 0 0 6px; font-size: 30px; }
        .header p { margin: 0; opacity: .9; color: rgba(255,255,255,.92); }
        .content { padding: 28px 32px; }
        .field { margin-bottom: 18px; }
        label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--peach); text-transform: uppercase; font-size: 12px; letter-spacing: .7px; }
        input[type=text], textarea { width: 100%; border: 1.5px solid rgba(255,255,255,.08); border-radius: 14px; padding: 16px 18px; font-size: 15px; background: #1b1b1b; color: var(--peach); outline: none; }
        input[type=text]:focus, textarea:focus { border-color: rgba(246,112,17,.8); box-shadow: 0 0 0 4px rgba(246,112,17,.12); }
        textarea { min-height: 220px; resize: vertical; }
        .help { font-size: 13px; color: var(--grey); margin-top: 8px; }
        .button { display: inline-flex; align-items: center; justify-content: center; background: linear-gradient(135deg, var(--orange), var(--brown)); color: var(--white); border: none; border-radius: 14px; padding: 14px 24px; font-size: 15px; cursor: pointer; transition: transform .2s ease, box-shadow .2s ease; }
        .button:hover { transform: translateY(-1px); box-shadow: 0 18px 40px rgba(246,112,17,.28); }
        .notice { margin-bottom: 18px; padding: 16px 18px; border-radius: 14px; }
        .notice.error { background: rgba(255,77,77,.12); color: #ffb3b3; border: 1px solid rgba(255,77,77,.25); }
        .notice.success { background: rgba(246,112,17,.12); color: #fff; border: 1px solid rgba(246,112,17,.35); }
        .info-row { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 22px; }
        .badge { background: #1b1b1b; border: 1px solid rgba(246,112,17,.14); border-radius: 14px; padding: 16px 18px; flex: 1; min-width: 220px; color: var(--peach); }
        .badge strong { color: var(--white); }
        .small-link { color: var(--orange); text-decoration: none; font-size: 14px; }
        .small-link:hover { color: var(--peach); }
    </style>
</head>
<body>
<div class="page">
    <div class="card">
        <div class="header">
            <h1>Contacter le candidat</h1>
            <p>Envoyez un message personnalisé à <?= renderValue($candidat['prenom'] . ' ' . $candidat['nom']) ?>.</p>
        </div>
        <div class="content">
            <?php if ($success): ?>
                <div class="notice success"><?= renderValue($success) ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="notice error">
                    <strong>Erreur</strong>
                    <ul style="margin: 12px 0 0 18px; padding: 0; list-style: disc;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= renderValue($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="info-row">
                <div class="badge"><strong>Candidat</strong><br><?= renderValue($candidat['prenom'] . ' ' . $candidat['nom']) ?><br><span style="color:#555;"><?= renderValue($candidat['email']) ?></span></div>
                <div class="badge"><strong>Recruteur</strong><br><?= renderValue($recruteur['entreprise']) ?><br><span style="color:#555;">Réponse vers : <?= renderValue($recruteur['email']) ?></span></div>
            </div>

            <form method="post" action="?cid=<?= intval($cid) ?>">
                <div class="field">
                    <label for="subject">Sujet</label>
                    <input type="text" id="subject" name="subject" value="<?= renderValue($subject) ?>" required>
                </div>
                <div class="field">
                    <label for="message">Message</label>
                    <textarea id="message" name="message" required><?= renderValue($message) ?></textarea>
                    <div class="help">Le candidat recevra ce message par email. Les réponses iront à votre adresse de recruteur.</div>
                </div>
                <button type="submit" class="button">Envoyer l’email</button>
            </form>

            <p style="margin-top:24px;font-size:14px;color:#555;">Vous pouvez revenir au tableau de bord une fois l’email envoyé.</p>
            <a class="small-link" href="dashboard_recruteur.php">← Retour au tableau de bord recruteur</a>
        </div>
    </div>
</div>
</body>
</html>

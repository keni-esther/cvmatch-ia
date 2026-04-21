<?php
require_once 'config.php';
require_once 'mailer.php';

if (estConnecte())
    rediriger('index.php');

$err = '';
$role = $_GET['role'] ?? 'candidat';
$etape = $_SESSION['otp_etape'] ?? 'formulaire';

// ============================================================
// ÉTAPE 1 — Soumission du formulaire principal
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['soumettre_formulaire'])) {
    $role = $_POST['role'] ?? 'candidat';
    $mdp = trim($_POST['mdp'] ?? '');
    $mdp2 = trim($_POST['mdp2'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $err_form = '';

    if ($role === 'candidat') {
        $nom = trim($_POST['nom'] ?? '');
        $prenom = trim($_POST['prenom'] ?? '');
        if (!$nom || !$prenom || !$email || !$mdp)
            $err_form = 'Veuillez remplir tous les champs obligatoires (*).';
    } else {
        $nom = trim($_POST['nom'] ?? '');
        $code_recruteur = trim($_POST['code_recruteur'] ?? '');
        if (!$nom || !$email || !$mdp)
            $err_form = 'Veuillez remplir tous les champs obligatoires (*).';
        elseif ($code_recruteur !== CODE_RECRUTEUR)
            $err_form = 'Code recruteur invalide.';
    }

    if (!$err_form && $mdp !== $mdp2)
        $err_form = 'Les mots de passe ne correspondent pas.';
    if (!$err_form && strlen($mdp) < 6)
        $err_form = 'Le mot de passe doit contenir au moins 6 caractères.';
    if (!$err_form && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $err_form = 'Adresse email invalide.';

    if (!$err_form) {
        $db = getDB();
        $table = ($role === 'candidat') ? 'candidats' : 'recruteurs';
        $chk = $db->prepare("SELECT id FROM $table WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch())
            $err_form = 'Cet email est déjà utilisé.';
    }

    if ($err_form) {
        $err = $err_form;
    } else {
        // Générer et envoyer l'OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $_SESSION['otp_code'] = $otp;
        $_SESSION['otp_email'] = $email;
        $_SESSION['otp_expires'] = time() + 600;
        $_SESSION['otp_etape'] = 'verification';
        $_SESSION['otp_tentatives'] = 0;
        $_SESSION['otp_form_data'] = $_POST;

        $result = envoyerEmail($email, "Votre code de vérification — CVMatch IA", templateOTP($otp, $email));

        if (!$result['ok']) {
            unset($_SESSION['otp_etape'], $_SESSION['otp_code'], $_SESSION['otp_form_data']);
            $err = 'Impossible d\'envoyer l\'email : ' . $result['erreur'];
            $etape = 'formulaire';
        } else {
            $etape = 'verification';
        }
    }
}

// ============================================================
// ÉTAPE 2 — Vérification OTP
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verifier_otp'])) {
    $code_saisi = trim(str_replace(' ', '', $_POST['otp_code'] ?? ''));
    $etape = 'verification';

    if (time() > ($_SESSION['otp_expires'] ?? 0)) {
        $err = 'Le code a expiré. Veuillez recommencer.';
        unset($_SESSION['otp_etape'], $_SESSION['otp_code'], $_SESSION['otp_form_data']);
        $etape = 'formulaire';
    } elseif (($_SESSION['otp_tentatives'] ?? 0) >= 5) {
        $err = 'Trop de tentatives. Veuillez recommencer.';
        unset($_SESSION['otp_etape'], $_SESSION['otp_code'], $_SESSION['otp_form_data']);
        $etape = 'formulaire';
    } elseif ($code_saisi !== $_SESSION['otp_code']) {
        $_SESSION['otp_tentatives']++;
        $restantes = 5 - $_SESSION['otp_tentatives'];
        $err = 'Code incorrect. ' . $restantes . ' tentative(s) restante(s).';
    } else {
        // BON CODE — créer le compte
        $post = $_SESSION['otp_form_data'];
        $role = $post['role'] ?? 'candidat';
        $db = getDB();
        $hash = password_hash(trim($post['mdp']), PASSWORD_DEFAULT);

        if ($role === 'candidat') {
            $comp = trim($post['competences'] ?? '');
            $comp_json = '';
            if ($comp) {
                $arr = array_filter(array_map('trim', explode(',', $comp)));
                $comp_json = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
            }
            $stmt = $db->prepare("INSERT INTO candidats (nom,prenom,email,telephone,ville,titre_profil,experience_annees,competences,formation,mot_de_passe) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $stmt->execute([
                trim($post['nom']),
                trim($post['prenom']),
                trim($post['email']),
                trim($post['telephone'] ?? ''),
                trim($post['ville'] ?? ''),
                trim($post['titre_profil'] ?? ''),
                intval($post['exp'] ?? 0),
                $comp_json,
                trim($post['formation'] ?? ''),
                $hash
            ]);
            $new_id = $db->lastInsertId();
            $_SESSION['user_id'] = $new_id;
            $_SESSION['nom'] = trim($post['nom']);
            $_SESSION['prenom'] = trim($post['prenom']);
            $_SESSION['email'] = trim($post['email']);
            $_SESSION['role'] = 'candidat';
            $_SESSION['candidat_id'] = $new_id;
        } else {
            $stmt = $db->prepare("INSERT INTO recruteurs (nom,email,mot_de_passe,entreprise) VALUES (?,?,?,?)");
            $stmt->execute([trim($post['nom']), trim($post['email']), $hash, trim($post['entreprise'] ?? '')]);
            $new_id = $db->lastInsertId();
            $_SESSION['user_id'] = $new_id;
            $_SESSION['nom'] = trim($post['nom']);
            $_SESSION['email'] = trim($post['email']);
            $_SESSION['entreprise'] = trim($post['entreprise'] ?? '');
            $_SESSION['role'] = 'recruteur';
            $_SESSION['recruteur_id'] = $new_id;
        }

        unset(
            $_SESSION['otp_etape'],
            $_SESSION['otp_code'],
            $_SESSION['otp_expires'],
            $_SESSION['otp_tentatives'],
            $_SESSION['otp_form_data'],
            $_SESSION['otp_email']
        );

        rediriger($role === 'recruteur' ? 'dashboard_recruteur.php' : 'dashboard_candidat.php');
    }
}

// Renvoyer le code
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['renvoyer_otp'])) {
    $etape = 'verification';
    if (isset($_SESSION['otp_email'])) {
        $otp_nouveau = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $_SESSION['otp_code'] = $otp_nouveau;
        $_SESSION['otp_expires'] = time() + 600;
        $_SESSION['otp_tentatives'] = 0;
        $result = envoyerEmail($_SESSION['otp_email'], "Nouveau code — CVMatch IA", templateOTP($otp_nouveau, $_SESSION['otp_email']));
        $msg_renvoye = $result['ok'];
        if (!$result['ok'])
            $err = 'Erreur renvoi : ' . $result['erreur'];
    }
}

// Annuler
if (isset($_GET['annuler'])) {
    unset(
        $_SESSION['otp_etape'],
        $_SESSION['otp_code'],
        $_SESSION['otp_expires'],
        $_SESSION['otp_tentatives'],
        $_SESSION['otp_form_data'],
        $_SESSION['otp_email']
    );
    rediriger('inscription.php?role=' . ($_GET['role'] ?? 'candidat'));
}

if ($etape === 'verification' && isset($_SESSION['otp_form_data']))
    $role = $_SESSION['otp_form_data']['role'] ?? 'candidat';
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CVMatch IA — Inscription</title>
    <link rel="stylesheet" href="inscription.css">
    <style>
        .otp-wrap {
            text-align: center;
            padding: 8px 0 4px;
        }

        .otp-icon {
            font-size: 52px;
            display: block;
            margin-bottom: 16px;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1)
            }

            50% {
                transform: scale(1.07)
            }
        }

        .otp-title {
            font-family: 'Syne', sans-serif;
            font-size: 22px;
            font-weight: 700;
            color: var(--white);
            margin-bottom: 8px;
        }

        .otp-sub {
            font-size: 13.5px;
            color: var(--grey);
            line-height: 1.5;
            margin-bottom: 6px;
        }

        .otp-badge {
            display: inline-block;
            background: rgba(246, 112, 17, .1);
            border: 1px solid rgba(246, 112, 17, .3);
            color: var(--orange);
            font-weight: 700;
            font-size: 13px;
            padding: 6px 16px;
            border-radius: 50px;
            margin: 10px 0 22px;
        }

        .otp-inputs {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 22px;
        }

        .otp-inputs input {
            width: 50px !important;
            height: 58px;
            text-align: center;
            font-size: 22px;
            font-weight: 700;
            font-family: 'Syne', sans-serif;
            color: var(--orange);
            background: var(--dark);
            border: 2px solid rgba(135, 135, 135, .25);
            border-radius: 12px;
            margin: 0;
            padding: 0;
            transition: all .22s;
            caret-color: var(--orange);
        }

        .otp-inputs input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 4px rgba(246, 112, 17, .12);
            outline: none;
        }

        .otp-inputs input.filled {
            border-color: rgba(246, 112, 17, .5);
            background: rgba(246, 112, 17, .06);
        }

        .otp-timer {
            font-size: 12.5px;
            color: var(--grey);
            margin-bottom: 18px;
        }

        .otp-timer span {
            color: var(--orange);
            font-weight: 700;
        }

        .otp-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-renvoyer {
            background: transparent;
            border: 1.5px solid rgba(246, 112, 17, .4);
            color: var(--orange);
            padding: 9px 20px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .22s;
        }

        .btn-renvoyer:hover {
            background: rgba(246, 112, 17, .1);
        }

        .btn-annuler {
            background: transparent;
            border: 1.5px solid rgba(135, 135, 135, .25);
            color: var(--grey);
            padding: 9px 20px;
            border-radius: 8px;
            font-family: 'DM Sans', sans-serif;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all .22s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-annuler:hover {
            border-color: var(--grey);
            color: var(--peach);
        }

        .msg-ok {
            background: rgba(16, 185, 129, .09);
            border: 1px solid rgba(16, 185, 129, .25);
            color: #6ee7b7;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 16px;
        }
    </style>
</head>

<body>
    <div class="card" style="max-width:<?= $etape === 'verification' ? '420px' : '520px' ?>">

        <?php if ($etape === 'verification'): ?>
            <!-- ===== ÉTAPE 2 : OTP ===== -->
            <div class="otp-wrap">
                <span class="otp-icon">📬</span>
                <div class="otp-title">Vérifiez votre email</div>
                <p class="otp-sub">Code à 6 chiffres envoyé à :</p>
                <div class="otp-badge">📧 <?= s($_SESSION['otp_email'] ?? '') ?></div>
                <p class="otp-sub" style="font-size:12px">Vérifiez vos spams si besoin. Valable <strong
                        style="color:var(--orange)">10 min</strong>.</p>
            </div>

            <?php if (!empty($err)): ?>
                <div class="err">⚠️ <?= s($err) ?></div><?php endif; ?>
            <?php if (!empty($msg_renvoye)): ?>
                <div class="msg-ok">✅ Nouveau code envoyé !</div><?php endif; ?>

            <form method="POST" id="form-otp">
                <div class="otp-inputs">
                    <?php for ($i = 0; $i < 6; $i++): ?>
                        <input type="text" class="otp-digit" maxlength="1" inputmode="numeric" autocomplete="off">
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="otp_code" id="otp-hidden">

                <div class="otp-timer">Expire dans : <span id="timer">10:00</span></div>

                <button type="submit" name="verifier_otp" class="btn" id="btn-verifier" disabled>
                    ✅ Vérifier et créer mon compte
                </button>
            </form>

            <div class="otp-actions">
                <form method="POST" style="margin:0">
                    <button type="submit" name="renvoyer_otp" class="btn-renvoyer">🔄 Renvoyer le code</button>
                </form>
                <a href="inscription.php?annuler=1&role=<?= s($role) ?>" class="btn-annuler">✕ Recommencer</a>
            </div>

        <?php else: ?>
            <!-- ===== ÉTAPE 1 : FORMULAIRE ===== -->
            <h1>CVMatch <span class="ia">IA</span></h1>
            <div class="sub">Créer un compte</div>

            <div class="tabs">
                <a href="inscription.php?role=candidat" class="tab <?= $role === 'candidat' ? 'on' : '' ?>">👤 Candidat</a>
                <a href="inscription.php?role=recruteur" class="tab <?= $role === 'recruteur' ? 'on' : '' ?>">🏢 Recruteur</a>
            </div>

            <?php if (!empty($err)): ?>
                <div class="err">⚠️ <?= s($err) ?></div><?php endif; ?>

            <form method="POST">
                <input type="hidden" name="role" value="<?= $role ?>">
                <div class="sec">Informations personnelles</div>

                <?php if ($role === 'candidat'): ?>
                    <div class="row">
                        <div class="fg"><label>Nom <span class="req">*</span></label><input type="text" name="nom"
                                value="<?= s($_POST['nom'] ?? '') ?>" required></div>
                        <div class="fg"><label>Prénom <span class="req">*</span></label><input type="text" name="prenom"
                                value="<?= s($_POST['prenom'] ?? '') ?>" required></div>
                    </div>
                    <div class="fg">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="<?= s($_POST['email'] ?? '') ?>" required
                            placeholder="votre@email.com">
                        <div class="note">📬 Un code de vérification sera envoyé à cet email</div>
                    </div>
                    <div class="row">
                        <div class="fg"><label>Téléphone</label><input type="text" name="telephone"
                                value="<?= s($_POST['telephone'] ?? '') ?>"></div>
                        <div class="fg"><label>Ville</label><input type="text" name="ville"
                                value="<?= s($_POST['ville'] ?? '') ?>"></div>
                    </div>
                    <div class="row">
                        <div class="fg"><label>Mot de passe <span class="req">*</span></label><input type="password" name="mdp"
                                required></div>
                        <div class="fg"><label>Confirmer <span class="req">*</span></label><input type="password" name="mdp2"
                                required></div>
                    </div>
                    <div class="sec">Profil professionnel</div>
                    <div class="row">
                        <div class="fg"><label>Titre du profil</label><input type="text" name="titre_profil"
                                placeholder="Développeur PHP..." value="<?= s($_POST['titre_profil'] ?? '') ?>"></div>
                        <div class="fg"><label>Années d'expérience</label><input type="number" name="exp" min="0" max="50"
                                value="<?= intval($_POST['exp'] ?? 0) ?>"></div>
                    </div>
                    <div class="fg"><label>Compétences</label><textarea name="competences"
                            placeholder="PHP, MySQL, JavaScript..."><?= s($_POST['competences'] ?? '') ?></textarea></div>
                    <div class="fg"><label>Formation</label><textarea name="formation"
                            placeholder="Licence Informatique, ESATIC..."><?= s($_POST['formation'] ?? '') ?></textarea></div>

                <?php else: ?>
                    <div class="fg"><label>Nom complet <span class="req">*</span></label><input type="text" name="nom"
                            value="<?= s($_POST['nom'] ?? '') ?>" required></div>
                    <div class="fg">
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" value="<?= s($_POST['email'] ?? '') ?>" required
                            placeholder="recruteur@entreprise.com">
                        <div class="note">📬 Un code de vérification sera envoyé à cet email</div>
                    </div>
                    <div class="fg"><label>Entreprise</label><input type="text" name="entreprise"
                            value="<?= s($_POST['entreprise'] ?? '') ?>"></div>
                    <div class="row">
                        <div class="fg"><label>Mot de passe <span class="req">*</span></label><input type="password" name="mdp"
                                required></div>
                        <div class="fg"><label>Confirmer <span class="req">*</span></label><input type="password" name="mdp2"
                                required></div>
                    </div>
                    <div class="fg">
                        <label>Code d'accès recruteur <span class="req">*</span></label>
                        <input type="password" name="code_recruteur" placeholder="••••••••" required>
                        <div class="note">Pas encore de code ? <a href="envoyer_code.php">Cliquez ici</a></div>
                    </div>
                <?php endif; ?>

                <button type="submit" name="soumettre_formulaire" class="btn">
                    📬 Continuer — Vérifier mon email →
                </button>
            </form>

            <div class="bas">Déjà un compte ? <a href="index.php">Se connecter</a></div>
        <?php endif; ?>

    </div>

    <script>
        <?php if ($etape === 'verification'): ?>
            const digits = document.querySelectorAll('.otp-digit');
            const hidden = document.getElementById('otp-hidden');
            const btnVerif = document.getElementById('btn-verifier');

            function updateHidden() {
                let val = '';
                digits.forEach(d => { val += d.value; d.classList.toggle('filled', d.value !== ''); });
                hidden.value = val;
                btnVerif.disabled = val.length < 6;
            }

            digits.forEach((inp, i) => {
                inp.addEventListener('input', function () {
                    this.value = this.value.replace(/[^0-9]/g, '').slice(-1);
                    updateHidden();
                    if (this.value && i < digits.length - 1) digits[i + 1].focus();
                });
                inp.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace' && !this.value && i > 0) {
                        digits[i - 1].value = ''; digits[i - 1].focus(); updateHidden();
                    }
                });
                inp.addEventListener('paste', function (e) {
                    e.preventDefault();
                    const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                    [...pasted.slice(0, 6)].forEach((ch, idx) => { if (digits[idx]) digits[idx].value = ch; });
                    updateHidden();
                    const nxt = [...digits].findIndex(d => !d.value);
                    (nxt !== -1 ? digits[nxt] : digits[5]).focus();
                });
            });
            digits[0].focus();

            // Minuteur
            const expireAt = <?= (int) (($_SESSION['otp_expires'] ?? (time() + 600)) * 1000) ?>;
            const timerEl = document.getElementById('timer');
            function tick() {
                const left = Math.max(0, Math.floor((expireAt - Date.now()) / 1000));
                const m = String(Math.floor(left / 60)).padStart(2, '0');
                const s = String(left % 60).padStart(2, '0');
                timerEl.textContent = m + ':' + s;
                if (left === 0) { timerEl.style.color = '#ff8c8c'; timerEl.textContent = 'Expiré ⚠️'; }
            }
            tick(); setInterval(tick, 1000);
        <?php endif; ?>
    </script>
</body>

</html>
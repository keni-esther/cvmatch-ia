<?php
require_once 'config.php';

if (!estConnecte() || !estCandidat())
    rediriger('index.php');

$db = getDB();
$cid = $_SESSION['candidat_id'];

// Récupérer le candidat
$stmt = $db->prepare("SELECT * FROM candidats WHERE id = ?");
$stmt->execute([$cid]);
$candidat = $stmt->fetch();

if (!$candidat) {
    session_destroy();
    rediriger('index.php');
}

// Compétences lisibles
$comp_affichage = '';
if (!empty($candidat['competences'])) {
    $arr = json_decode($candidat['competences'], true);
    $comp_affichage = is_array($arr) ? implode(', ', $arr) : $candidat['competences'];
}

$msg_ok = '';
$msg_er = '';

// ==== TRAITEMENT POST (update profil ou upload CV) ====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // --- Mise à jour profil ---
    if ($action === 'update_profil') {
        $tel = trim($_POST['telephone'] ?? '');
        $vil = trim($_POST['ville'] ?? '');
        $exp = intval($_POST['exp'] ?? 0);
        $comp = trim($_POST['competences'] ?? '');
        $form = trim($_POST['formation'] ?? '');

        $comp_json = '';
        if ($comp) {
            $arr = array_filter(array_map('trim', explode(',', $comp)));
            $comp_json = json_encode(array_values($arr), JSON_UNESCAPED_UNICODE);
        }

        $db->prepare("UPDATE candidats SET telephone=?,ville=?,experience_annees=?,competences=?,formation=? WHERE id=?")
            ->execute([$tel, $vil, $exp, $comp_json, $form, $cid]);

        // Rafraîchir les données en session/mémoire
        $stmt = $db->prepare("SELECT * FROM candidats WHERE id = ?");
        $stmt->execute([$cid]);
        $candidat = $stmt->fetch();
        $arr = json_decode($candidat['competences'], true);
        $comp_affichage = is_array($arr) ? implode(', ', $arr) : $candidat['competences'];
        $msg_ok = 'Profil mis à jour avec succès !';
    }

    // --- Upload CV ---
    if ($action === 'upload_cv' && isset($_FILES['cv']) && $_FILES['cv']['error'] === 0) {
        $allowed = ['pdf', 'docx', 'doc'];
        $ext = strtolower(pathinfo($_FILES['cv']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed)) {
            $msg_er = 'Format non supporté. Utilisez PDF, DOCX ou DOC.';
        } elseif ($_FILES['cv']['size'] > 5 * 1024 * 1024) {
            $msg_er = 'Fichier trop volumineux (max 5 Mo).';
        } else {
            $filename = 'cv_' . $cid . '_' . time() . '.' . $ext;
            $dest = UPLOAD_DIR . $filename;
            if (!file_exists(UPLOAD_DIR)) {
                 mkdir(UPLOAD_DIR, 0777, true);
                }
            if (move_uploaded_file($_FILES['cv']['tmp_name'], $dest)) {
                // Mettre à jour ou insérer dans cv_fichiers
                $existing = $db->prepare("SELECT id FROM cv_fichiers WHERE candidat_id = ?");
                $existing->execute([$cid]);
                if ($existing->fetch()) {
                    $db->prepare("UPDATE cv_fichiers SET chemin_fichier=?,nom_original=?,date_upload=NOW() WHERE candidat_id=?")
                        ->execute(['uploads/' . $filename, $_FILES['cv']['name'], $cid]);
                } else {
                    $db->prepare("INSERT INTO cv_fichiers (candidat_id,chemin_fichier,nom_original) VALUES (?,?,?)")
                        ->execute([$cid, 'uploads/' . $filename, $_FILES['cv']['name']]);
                }
                // Aussi stocker dans candidats.cv pour compatibilité
                // $db->prepare("UPDATE candidats SET cv=? WHERE id=?")->execute(['uploads/'.$filename, $cid]);
                // $candidat['cv'] = 'uploads/'.$filename;
                $msg_ok = 'CV uploadé avec succès !';
            } else {
                $msg_er = 'Erreur lors de la sauvegarde du fichier.';
            }
        }
    }
}

// $cv = $candidat['cv'] ?? '';




$stmt_cv = $db->prepare("SELECT chemin_fichier FROM cv_fichiers WHERE candidat_id = ?");
$stmt_cv->execute([$cid]);
$cv_data = $stmt_cv->fetch();

$cv = $cv_data['chemin_fichier'] ?? '';




// ==== MODE AJAX ====
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    $tab = $_GET['tab'] ?? 'profil';
    $p = $candidat;

    // Passer les messages aux sections si besoin
    if (!empty($msg_ok))
        echo '<div class="ok">✅ ' . $msg_ok . '</div>';
    if (!empty($msg_er))
        echo '<div class="er">❌ ' . $msg_er . '</div>';

    if ($tab === 'profil')
        include 'sections/candidat_profil.php';
    elseif ($tab === 'cv')
        include 'sections/candidat_cv.php';
    elseif ($tab === 'candidatures')
        include 'sections/candidat_candidatures.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CVMatch IA — Mon espace</title>
    <link rel="stylesheet" href="candidat.css">
</head>

<body>

    <div class="header">
        <h1>CVMatch <span class="ia">IA</span></h1>
        <div style="display:flex;align-items:center;gap:20px">
            <span class="info">👤 <?= s($candidat['prenom'] . ' ' . $candidat['nom']) ?></span>
            <a href="deconnexion.php">Déconnexion</a>
        </div>
    </div>

    <div class="body">
        <div class="tabs">
            <button class="tab on" data-tab="profil">📋 Mon Profil</button>
            <button class="tab" data-tab="cv">📄 Mon CV</button>
            <button class="tab" data-tab="candidatures">📊 Mes candidatures</button>
        </div>

        <div id="content">
            <div class="loading">⏳ Chargement...</div>
        </div>
    </div>

    <script>
        async function loadTab(tab) {
            const c = document.getElementById('content');
            c.innerHTML = '<div class="loading">⏳ Chargement...</div>';
            try {
                const r = await fetch('dashboard_candidat.php?ajax=1&tab=' + tab);
                if (!r.ok) throw new Error('Erreur réseau');
                c.innerHTML = await r.text();
                // Rebind form si présent
                const f = c.querySelector('#form-profil');
                if (f) bindProfil(f);
                const fc = c.querySelector('#form-cv');
                if (fc) bindCV(fc);
            } catch (e) {
                c.innerHTML = '<div class="er">⚠️ ' + e.message + '</div>';
            }
        }

        function bindProfil(form) {
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const fd = new FormData(form);
                const r = await fetch('dashboard_candidat.php?ajax=1&tab=profil', { method: 'POST', body: fd });
                document.getElementById('content').innerHTML = await r.text();
                const f2 = document.getElementById('content').querySelector('#form-profil');
                if (f2) bindProfil(f2);
            });
        }

        function bindCV(form) {
            // Preview nom fichier
            const inp = form.querySelector('input[type="file"]');
            if (inp) {
                inp.addEventListener('change', function () {
                    const span = form.querySelector('.file-chosen');
                    if (span) span.textContent = inp.files[0] ? '📎 ' + inp.files[0].name : '';
                });
            }
            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const fd = new FormData(form);
                const r = await fetch('dashboard_candidat.php?ajax=1&tab=cv', { method: 'POST', body: fd });
                document.getElementById('content').innerHTML = await r.text();
                const f2 = document.getElementById('content').querySelector('#form-cv');
                if (f2) bindCV(f2);
            });
        }

        document.querySelectorAll('.tab').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab').forEach(b => b.classList.remove('on'));
                btn.classList.add('on');
                loadTab(btn.dataset.tab);
            });
        });

        window.addEventListener('DOMContentLoaded', () => loadTab('profil'));
    </script>
</body>

</html>

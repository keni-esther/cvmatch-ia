<?php
// sections/recruteur_recherche.php
// Variables attendues : $db (PDO), $rid (int)
if (!isset($db) || !isset($rid))
    die('Accès direct interdit.');

$requete = trim($_POST['requete'] ?? '');
$resultats = [];
$info_msg = '';
$mode_ia = false;

// ── Lancement de la recherche ─────────────────────────────────
if (!empty($_POST['rechercher']) && $requete !== '') {

    // 1) Essai Flask ──────────────────────────────────────────
    $url = IA_SERVICE_URL . '/matching';
    $payload = json_encode(['requete' => $requete]);
    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 10,
            'ignore_errors' => true,
        ]
    ];
    $response = @file_get_contents($url, false, stream_context_create($opts));

    if ($response !== false) {
        $json = json_decode($response, true);
        if (!empty($json['resultats'])) {
            $resultats = $json['resultats'];
            $mode_ia = true;
            $info_msg = '✅ Analyse Flask IA — ' . count($resultats) . ' profil(s) trouvé(s)';
        }
    }

    // 2) Fallback PHP ─────────────────────────────────────────
    if (empty($resultats)) {
        $stops = [
            'le',
            'la',
            'les',
            'de',
            'du',
            'des',
            'un',
            'une',
            'et',
            'en',
            'avec',
            'pour',
            'par',
            'dans',
            'sur',
            'qui',
            'que',
            'ou',
            'minimum',
            'ans',
            'bonne',
            'connaissance',
            'disponible',
            'avoir'
        ];
        $mots = array_values(array_diff(
            array_filter(array_map('strtolower', preg_split('/[\s,;.\/\-]+/', $requete))),
            $stops
        ));

        if (!empty($mots)) {
            $all = $db->query(
                "SELECT c.id, c.prenom, c.nom, c.email, c.telephone, c.ville,
                        c.experience_annees, c.competences, c.formation,
                        f.nom_fichier, f.chemin_fichier, f.resume_ia,
                        f.competences_extraites
                 FROM candidats c
                 LEFT JOIN cv_fichiers f
                   ON f.candidat_id = c.id
                  AND f.id = (SELECT MAX(id) FROM cv_fichiers WHERE candidat_id = c.id)"
            )->fetchAll();

            $scores = [];
            foreach ($all as $c) {
                // Décoder JSON compétences
                $comp = '';
                if (!empty($c['competences'])) {
                    $arr = json_decode($c['competences'], true);
                    $comp = is_array($arr) ? implode(' ', $arr) : $c['competences'];
                }
                if (!empty($c['competences_extraites'])) {
                    $arr2 = json_decode($c['competences_extraites'], true);
                    $comp .= ' ' . (is_array($arr2) ? implode(' ', $arr2) : $c['competences_extraites']);
                }

                $texte = strtolower(
                    ($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '') . ' ' .
                    $comp . ' ' .
                    ($c['formation'] ?? '') . ' ' .
                    ($c['ville'] ?? '') . ' ' .
                    ($c['resume_ia'] ?? '')
                );

                $score = 0;
                foreach ($mots as $m) {
                    if (strlen($m) < 2)
                        continue;
                    $cnt = substr_count($texte, $m);
                    $bonus = substr_count(strtolower($comp), $m) > 0 ? 3 : 1;
                    $score += $cnt * $bonus;
                }

                if ($score > 0) {
                    $pct = min(98, max(15, round($score / count($mots) * 35)));
                    $scores[] = [
                        'id' => $c['id'],
                        'nom_complet' => trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')),
                        'email' => $c['email'] ?? '',
                        'telephone' => $c['telephone'] ?? '',
                        'ville' => $c['ville'] ?? '',
                        'experience_annees' => intval($c['experience_annees'] ?? 0),
                        'competences' => $comp,
                        'nom_fichier' => $c['nom_fichier'] ?? '',
                        'chemin_fichier' => $c['chemin_fichier'] ?? '',
                        'score' => $pct,
                        'resume_gen' => '',
                    ];
                }
            }

            usort($scores, fn($a, $b) => $b['score'] - $a['score']);
            $resultats = array_slice($scores, 0, 12);
            $info_msg = !empty($resultats)
                ? '⚡ Moteur PHP — ' . count($resultats) . ' profil(s) trouvé(s)'
                : '';
        }
    }

    // 3) Sauvegarder la recherche
    try {
        $db->prepare("INSERT INTO recherche_ia (recruteur_id, requete_texte, resultats_json) VALUES (?,?,?)")
            ->execute([$rid, $requete, json_encode($resultats, JSON_UNESCAPED_UNICODE)]);
    } catch (Exception $e) {
    }
}
?>

<!-- Formulaire de recherche -->
<div class="card">
    <h2>🔍 Recherche IA de candidats</h2>
    <p style="font-size:13px;color:#666;margin-bottom:16px">
        Décrivez le profil recherché en langage naturel.
    </p>
    <div>
        <textarea class="search-input" id="requete" name="requete" rows="4"
            placeholder="Ex : Développeur PHP 2 ans MySQL Laravel, disponible à Abidjan..."><?= s($requete) ?></textarea>
        <button type="button" id="btn-rechercher" class="btn">🤖 Lancer la recherche IA</button>
    </div>
</div>

<!-- Message info -->
<?php if ($info_msg): ?>
    <div class="info-box"><?= $info_msg ?></div>
<?php elseif (!empty($requete) && empty($resultats)): ?>
    <div class="info-box" style="background:#fff8e1;border-color:#ffc107;color:#7b5800">
        😕 Aucun candidat ne correspond à cette recherche.
    </div>
<?php else: ?>
    <div class="info-box">
        💡 Décrivez le profil et cliquez sur <strong>Lancer la recherche IA</strong>.
    </div>
<?php endif; ?>

<!-- Résultats -->
<?php if (!empty($resultats)): ?>
    <div class="card">
        <h2>
            Résultats
            <span class="<?= $mode_ia ? 'badge-ia' : 'badge-fb' ?>">
                <?= $mode_ia ? '🤖 IA Flask' : '⚡ PHP Fallback' ?>
            </span>
        </h2>
        <div class="nb"><?= count($resultats) ?> profil(s) pour : <em>"<?= s($requete) ?>"</em></div>

        <?php foreach ($resultats as $r): ?>
            <div class="profil-card">
                <div class="pc-top">
                    <div class="pc-nom">👤 <?= s($r['nom_complet'] ?? '') ?></div>
                    <div class="pc-score"><?= intval($r['score']) ?>%</div>
                </div>
                <div class="bar">
                    <div class="bar-fill" style="width:<?= intval($r['score']) ?>%"></div>
                </div>

                <?php if (!empty($r['resume_gen'])): ?>
                    <div class="pc-resume">🤖 <?= s($r['resume_gen']) ?></div>
                <?php endif; ?>

                <div class="pc-infos">
                    <span>📧 <?= s($r['email'] ?? '') ?></span>
                    <?php if (!empty($r['telephone'])): ?>
                        <span>📞 <?= s($r['telephone']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['ville'])): ?>
                        <span>📍 <?= s($r['ville']) ?></span>
                    <?php endif; ?>
                    <span>🕒 <?= intval($r['experience_annees'] ?? 0) ?> ans d'exp.</span>
                    <?php
                    $cv = $r['chemin_fichier'] ?: (!empty($r['nom_fichier']) ? 'uploads/' . $r['nom_fichier'] : '');
                    ?>
                    <?php if ($cv): ?>
                        <span>
                            <a href="<?= s($cv) ?>" target="_blank" style="color:#1565C0;font-weight:bold">
                                📎 Voir le CV
                            </a>
                        </span>
                    <?php endif; ?>
                </div>

                <div style="margin-top:10px">
                    <button
                        onclick="contacterCandidat(<?= intval($r['id']) ?>, '<?= addslashes(s($r['nom_complet'] ?? '')) ?>')"
                        class="btn-contact">
                        📧 Contacter
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    // Ce script s'exécute à chaque injection AJAX du fragment
    (function () {
        const btn = document.getElementById('btn-rechercher');
        const inp = document.getElementById('requete');
        if (!btn || !inp) return;

        btn.addEventListener('click', async function () {
            const q = inp.value.trim();
            if (q.length < 3) {
                alert('Décrivez le profil plus en détail (minimum 3 caractères).');
                return;
            }

            const content = document.getElementById('content');
            if (!content) return;

            // Affichage loading avec chronomètre
            let secs = 0;
            content.innerHTML = '<div class="loading">🤖 L\'IA analyse les profils… <span id="sec">0</span>s</div>';
            const timer = setInterval(() => {
                secs++;
                const el = document.getElementById('sec');
                if (el) el.textContent = secs;
            }, 1000);

            try {
                const fd = new FormData();
                fd.append('requete', q);
                fd.append('rechercher', '1');

                const res = await fetch('dashboard_recruteur.php?ajax=1&tab=recherche', {
                    method: 'POST',
                    body: fd,
                });
                clearInterval(timer);

                if (!res.ok) throw new Error('Erreur serveur (' + res.status + ')');
                content.innerHTML = await res.text();

            } catch (err) {
                clearInterval(timer);
                content.innerHTML =
                    '<div style="color:#c62828;padding:20px;background:#ffebee;border-radius:8px;margin-top:12px">' +
                    '⚠️ ' + err.message + '</div>';
            }
        });

        // Soumettre aussi avec Entrée (Ctrl+Entrée dans textarea)
        inp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.ctrlKey) btn.click();
        });
    })();
</script>
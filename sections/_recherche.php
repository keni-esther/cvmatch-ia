<?php
// Section chargée en AJAX dans dashboard_recruteur.php
// Variables dispo : $db, $rid
$requete = trim($_GET['q'] ?? $_POST['requete'] ?? '');
$resultats = [];
$info_msg = '';
$mode_ia = false;

// Si recherche lancée
if (!empty($_POST['rechercher']) || !empty($_GET['q'])) {
    $requete = trim($_POST['requete'] ?? $_GET['q'] ?? '');

    if (!empty($requete)) {
        // Essayer Flask IA
        $url = IA_SERVICE_URL . '/matching';
        $payload = json_encode(['requete' => $requete]);
        $opts = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 12]];
        $ctx = stream_context_create($opts);
        $response = @file_get_contents($url, false, $ctx);

        if ($response !== false) {
            $json = json_decode($response, true);
            if (!empty($json['resultats'])) {
                $resultats = $json['resultats'];
                $mode_ia = true;
                $info_msg = '✅ Analyse IA (Flask) — ' . count($resultats) . ' profil(s) trouvé(s)';
            }
        }

        // Fallback PHP
        if (empty($resultats)) {
            $mots = array_filter(array_map('strtolower', preg_split('/[\s,;.\/\-]+/', $requete)));
            $stops = ['le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'avec', 'pour', 'par', 'dans', 'sur', 'qui', 'que', 'minimum', 'ans', 'bonne', 'connaissance'];
            $mots = array_diff($mots, $stops);

            if (!empty($mots)) {
                $all = $db->query("SELECT c.id,c.prenom,c.nom,c.email,c.ville,c.experience_annees,c.competences,c.formation,f.chemin_fichier,f.resume_ia
                                   FROM candidats c LEFT JOIN cv_fichiers f ON f.candidat_id=c.id
                                   ORDER BY f.date_upload DESC")->fetchAll();
                $scores = [];
                foreach ($all as $c) {
                    $texte = strtolower(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '') . ' ' . ($c['competences'] ?? '') . ' ' . ($c['formation'] ?? '') . ' ' . ($c['ville'] ?? '') . ' ' . ($c['resume_ia'] ?? ''));
                    $score = 0;
                    foreach ($mots as $m) {
                        if (strlen($m) < 2)
                            continue;
                        $cnt = substr_count($texte, $m);
                        $score += $cnt * (strpos($c['competences'] ?? '', $m) !== false ? 3 : 1);
                    }
                    if ($score > 0) {
                        $pct = min(98, max(15, round($score / count($mots) * 35)));
                        $scores[] = [
                            'id' => $c['id'],
                            'nom_complet' => trim(($c['prenom'] ?? '') . ' ' . ($c['nom'] ?? '')),
                            'email' => $c['email'] ?? '',
                            'ville' => $c['ville'] ?? '',
                            'experience_annees' => intval($c['experience_annees'] ?? 0),
                            'score' => $pct,
                            'chemin_fichier' => $c['chemin_fichier'] ?? '',
                        ];
                    }
                }
                usort($scores, fn($a, $b) => $b['score'] - $a['score']);
                $resultats = array_slice($scores, 0, 12);
                $info_msg = !empty($resultats)
                    ? '✅ Moteur PHP — ' . count($resultats) . ' profil(s) trouvé(s)'
                    : '😕 Aucun candidat ne correspond à cette recherche.';
            }
        }

        if (!empty($requete)) {
            try {
                $db->prepare("INSERT INTO recherche_ia (recruteur_id,requete_texte,resultats_json) VALUES (?,?,?)")
                    ->execute([$rid, $requete, json_encode($resultats, JSON_UNESCAPED_UNICODE)]);
            } catch (Exception $e) {
            }
        }
    }
}
?>
<div class="card">
    <h2>🔍 Recherche IA de candidats</h2>
    <p style="font-size:13.5px;color:var(--grey);margin-bottom:18px">
        Décrivez le profil recherché en langage naturel :
    </p>

    <form id="search-form" method="POST">
        <textarea class="search-input" id="requete" name="requete" rows="5"
            placeholder="Ex : Développeur PHP 3 ans d'expérience MySQL Laravel, disponible à Abidjan..."><?= s($requete) ?></textarea>
        <button type="submit" name="rechercher" value="1" class="btn">🤖 Lancer la recherche IA</button>
    </form>
</div>

<?php if (!empty($info_msg)): ?>
    <div class="info-box"><?= $info_msg ?></div>
<?php endif; ?>

<?php if (!empty($resultats)): ?>
    <div class="card">
        <h2>Résultats</h2>
        <?php foreach ($resultats as $r): ?>
            <div class="profil-card">
                <div class="pc-top">
                    <div class="pc-nom">👤 <?= s($r['nom_complet']) ?></div>
                    <div class="pc-score"><?= $r['score'] ?>%</div>
                </div>
                <p>📧 <strong><?= s($r['email']) ?></strong></p>
                <p>📍 <?= s($r['ville'] ?: 'Ville non renseignée') ?></p>
                <p>💼 <?= intval($r['experience_annees'] ?? 0) ?> an(s) d'expérience</p>
                <?php if (!empty($r['chemin_fichier'])): ?>
                    <a href="<?= s($r['chemin_fichier']) ?>" target="_blank">📄 Voir le CV</a>
                <?php endif; ?>
                <br>
                <button onclick="contacterCandidat(<?= intval($r['id']) ?>, '<?= addslashes(s($r['nom_complet'])) ?>')"
                    class="btn-contact">
                    📧 Contacter ce candidat
                </button>
            </div>
        <?php endforeach; ?>
    </div>
<?php elseif (!empty($requete)): ?>
    <div class="card">
        <div class="empty-state">
            <span class="es-icon">🔍</span>
            Aucun candidat ne correspond à votre recherche.
        </div>
    </div>
<?php endif; ?>

<script>
    // Soumettre via AJAX pour rester dans le tab
    const sf = document.getElementById('search-form');
    if (sf) {
        sf.addEventListener('submit', async function (e) {
            e.preventDefault();
            const req = document.getElementById('requete').value.trim();
            if (req.length < 5) { alert('Décrivez le profil plus en détail.'); return; }
            const c = document.getElementById('content');
            c.innerHTML = '<div class="loading">🤖 L\'IA analyse les CVs... Veuillez patienter.</div>';
            const fd = new FormData(sf);
            const r = await fetch('dashboard_recruteur.php?ajax=1&tab=recherche', { method: 'POST', body: fd });
            c.innerHTML = await r.text();
        });
    }
</script>
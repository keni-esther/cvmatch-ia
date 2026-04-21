<?php
require_once 'config.php';
if (!estConnecte() || !estRecruteur())
    rediriger('index.php');

$db = getDB();
$rid = $_SESSION['recruteur_id'];

$stmt = $db->prepare("SELECT * FROM recruteurs WHERE id = ?");
$stmt->execute([$rid]);
$rec = $stmt->fetch();

$resultats = [];
$requete = '';
$mode_ia = false;
$info_msg = '';

// ── TRAITEMENT DE LA RECHERCHE ────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rechercher'])) {
    $requete = trim($_POST['requete'] ?? '');

    if (!empty($requete)) {

        // 1) Essai Flask
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
                $info_msg = '✅ Analyse IA Flask — ' . count($resultats) . ' profil(s) trouvé(s)';
            }
        }

        // 2) Fallback PHP si Flask vide ou injoignable
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
                'disponible'
            ];
            $mots = array_values(array_diff(
                array_filter(array_map('strtolower', preg_split('/[\s,;.\/\-]+/', $requete))),
                $stops
            ));

            if (!empty($mots)) {
                try {
                    $all = $db->query(
                        "SELECT c.id, CONCAT(c.prenom,' ',c.nom) AS nom_complet,
                                c.email, c.telephone, c.ville, c.experience_annees,
                                c.competences, c.formation,
                                f.nom_fichier, f.chemin_fichier, f.resume_ia,
                                f.competences_extraites
                         FROM candidats c
                         LEFT JOIN cv_fichiers f
                           ON f.candidat_id = c.id
                          AND f.id = (SELECT MAX(id) FROM cv_fichiers WHERE candidat_id = c.id)"
                    )->fetchAll();
                } catch (Exception $e) {
                    $all = [];
                }

                $scores = [];
                foreach ($all as $c) {
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
                        ($c['nom_complet'] ?? '') . ' ' . $comp . ' ' .
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
                            'nom_complet' => $c['nom_complet'] ?? '',
                            'email' => $c['email'] ?? '',
                            'telephone' => $c['telephone'] ?? '',
                            'ville' => $c['ville'] ?? '',
                            'experience_annees' => intval($c['experience_annees'] ?? 0),
                            'competences' => $comp,
                            'nom_fichier' => $c['nom_fichier'] ?? '',
                            'chemin_fichier' => $c['chemin_fichier'] ?? '',
                            'resume_ia' => $c['resume_ia'] ?? '',
                            'score' => $pct,
                        ];
                    }
                }

                usort($scores, fn($a, $b) => $b['score'] - $a['score']);
                $resultats = array_slice($scores, 0, 15);
                $info_msg = !empty($resultats)
                    ? '⚡ Moteur PHP — ' . count($resultats) . ' profil(s) trouvé(s)'
                    : '😕 Aucun candidat ne correspond à cette recherche.';
            }
        }

        // 3) Sauvegarder
        try {
            $db->prepare("INSERT INTO recherche_ia (recruteur_id, requete_texte, resultats_json) VALUES (?,?,?)")
                ->execute([$rid, $requete, json_encode($resultats, JSON_UNESCAPED_UNICODE)]);
        } catch (Exception $e) {
        }
    }
}

// Stats
$nbCandidats = $db->query("SELECT COUNT(*) FROM candidats")->fetchColumn();
$nbCvs = $db->query("SELECT COUNT(*) FROM cv_fichiers")->fetchColumn();
$stR = $db->prepare("SELECT COUNT(*) FROM recherche_ia WHERE recruteur_id=?");
$stR->execute([$rid]);
$nbR = $stR->fetchColumn();

// Historique
$hist = $db->prepare("SELECT requete_texte, date_recherche, resultats_json FROM recherche_ia WHERE recruteur_id=? ORDER BY date_recherche DESC LIMIT 10");
$hist->execute([$rid]);
$historique = $hist->fetchAll();
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>CVMatch IA — Recruteur</title>
    <link rel="stylesheet" href="recruteur.css">
</head>

<body>

    <div class="header">
        <h1>CVMatch IA <span style="font-size:13px;opacity:.7">— Recruteur</span></h1>
        <div style="display:flex;align-items:center;gap:12px">
            <span style="font-size:13px;opacity:.85">🏢 <?= s($rec['entreprise'] ?: $rec['nom']) ?></span>
            <a href="deconnexion.php">Déconnexion</a>
        </div>
    </div>

    <div class="body">

        <!-- Stats -->
        <div class="stats">
            <div class="stat">
                <div class="val"><?= $nbCandidats ?></div>
                <div class="lbl">Candidats inscrits</div>
            </div>
            <div class="stat">
                <div class="val"><?= $nbCvs ?></div>
                <div class="lbl">CVs déposés</div>
            </div>
            <div class="stat">
                <div class="val"><?= $nbR ?></div>
                <div class="lbl">Mes recherches</div>
            </div>
        </div>

        <!-- Formulaire recherche -->
        <div class="card">
            <h2>🔍 Recherche IA de candidats</h2>
            <p style="font-size:13px;color:#666;margin-bottom:14px">
                Décrivez le profil recherché en langage naturel :
            </p>
            <!-- <form method="POST">
                <div class="search-bar">
                    <textarea name="requete" rows="4"
                        placeholder="Ex : Développeur PHP 2 ans d'expérience MySQL, disponible à Abidjan..."><?= s($requete) ?></textarea>
                    <button type="submit" name="rechercher" value="1" class="btn">
                        🤖 Analyser avec l'IA
                    </button>
                </div>
            </form> -->






            <form method="POST">
    <div class="search-container">
        <div class="search-bar">
            <textarea name="requete" rows="4" class="search-input"
                placeholder="Ex : Développeur PHP 2 ans d'expérience MySQL Laravel, disponible à Abidjan..."><?= s($requete) ?></textarea>
            <button type="submit" name="rechercher" value="1" class="btn-ia">
                🤖 Analyser avec l'IA
            </button>
        </div>
    </div>
</form>

            <?php if ($info_msg): ?>
                <div class="info-box"><?= $info_msg ?></div>
            <?php elseif (empty($resultats)): ?>
                <div class="info-box">
                    💡 Décrivez le profil et cliquez sur <strong>Analyser avec l'IA</strong>.
                </div>
            <?php endif; ?>
        </div>

        <!-- Résultats -->
        <?php if (!empty($resultats)): ?>
            <div class="card">
                <h2>
                    Résultats
                    <?php if ($mode_ia): ?>
                        <span class="badge-ia">🤖 IA Flask</span>
                    <?php else: ?>
                        <span class="badge-fb">⚡ PHP Fallback</span>
                    <?php endif; ?>
                </h2>
                <div class="nb">
                    <?= count($resultats) ?> profil(s) trouvé(s) pour :
                    <em>"<?= s($requete) ?>"</em>
                </div>

                <?php foreach ($resultats as $r): ?>
                    <div class="profil-card">
                        <div class="pc-top" style="display:flex;justify-content:space-between;align-items:center">

                            <div class="pc-nom">
                                <?= s($r['nom_complet'] ?? '') ?>
                            </div>

                            <div style="display:flex;align-items:center;gap:10px">
                                <div class="pc-score">
                                    <?= intval($r['score']) ?>%
                                </div>

                                <?php if (!empty($r['source']) && $r['source'] === 'cvs_pool'): ?>
                                    <span style="font-size:13px;color:#f67011;font-weight:700;">CV pool</span>
                                <?php elseif (!empty($r['email']) && intval($r['id']) > 0): ?>
                                    <button
                                        onclick="contacterCandidat(<?= intval($r['id']) ?>, '<?= addslashes(s($r['nom_complet'] ?? '')) ?>')"
                                        class="btn-contact">
                                        📧 Contacter
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn-contact" disabled style="opacity:.65;cursor:not-allowed">
                                        📄 CV pool
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>





                        <div class="bar">
                            <div class="bar-fill" style="width:<?= intval($r['score']) ?>%"></div>
                        </div>

                        <?php $resume = $r['resume_gen'] ?? $r['resume_ia'] ?? ''; ?>
                        <?php if ($resume): ?>
                            <div class="pc-resume">🤖 <?= s($resume) ?></div>
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
                                        📎 Voir CV
                                    </a>
                                </span>
                            <?php endif; ?>
                            <?php if (!empty($r['source']) && $r['source'] === 'cvs_pool'): ?>
                                <span style="color:#f67011;font-size:13px;font-weight:700;display:block;margin-top:8px;">Origine : cvs_pool</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php elseif (isset($_POST['rechercher'])): ?>
            <div class="card">
                <p style="color:#888;font-size:14px">
                    Aucun candidat ne correspond à cette recherche.
                </p>
            </div>
        <?php endif; ?>

        <!-- Historique -->
        <?php if (!empty($historique)): ?>
            <div class="card">
                <h2>📋 Historique de mes recherches</h2>
                <table>
                    <tr>
                        <th>Requête</th>
                        <th>Résultats</th>
                        <th>Date</th>
                    </tr>
                    <?php foreach ($historique as $h):
                        $nb_res = 0;
                        if (!empty($h['resultats_json'])) {
                            $arr = json_decode($h['resultats_json'], true);
                            $nb_res = is_array($arr) ? count($arr) : 0;
                        }
                        ?>
                        <tr>
                            <td><?= s(mb_strimwidth($h['requete_texte'], 0, 70, '...')) ?></td>
                            <td><?= $nb_res ?> profil(s)</td>
                            <td><?= s(date('d/m/Y H:i', strtotime($h['date_recherche']))) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        <?php endif; ?>

    </div>



    <script>
        function contacterCandidat(id, nom) {
            if (!confirm("Contacter " + nom + " ?")) return;
            window.location.href = "contacter_candidat.php?cid=" + id;
        }
    </script>



</body>

</html>
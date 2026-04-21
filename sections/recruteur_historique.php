<?php
// Variables dispo : $db, $rid
$historique = [];
try {
    $stmt = $db->prepare("SELECT * FROM recherche_ia WHERE recruteur_id = ? ORDER BY date_recherche DESC LIMIT 50");
    $stmt->execute([$rid]);
    $historique = $stmt->fetchAll();
} catch (Exception $e) {
}
?>
<div class="card">
    <h2>📋 Historique de mes recherches</h2>
    <?php if (empty($historique)): ?>
        <div class="empty-state">
            <span class="es-icon">📭</span>
            Aucune recherche effectuée pour le moment.<br>
            <small style="font-size:13px">Vos recherches apparaîtront ici après avoir utilisé la recherche IA.</small>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Requête</th>
                    <th>Résultats</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($historique as $h): ?>
                    <?php
                    $nb = 0;
                    if (!empty($h['resultats_json'])) {
                        $arr = json_decode($h['resultats_json'], true);
                        $nb = is_array($arr) ? count($arr) : 0;
                    }
                    ?>
                    <tr>
                        <td style="max-width:380px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= s($h['requete_texte']) ?>
                        </td>
                        <td><?= $nb ?> profil(s)</td>
                        <td><?= date('d/m/Y H:i', strtotime($h['date_recherche'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
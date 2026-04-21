<?php
// Récupérer l'historique des contacts/candidatures pour ce candidat
// On cherche dans la table recherche_ia les recherches où le candidat a été contacté
$historique = [];
try {
    $stmt = $db->prepare("
        SELECT ria.requete_texte AS poste_recherche,
               r.nom AS rec_nom,
               r.entreprise,
               NULL AS score_matching,
               'en_attente' AS statut,
               ria.date_recherche AS date_candidature
        FROM recherche_ia ria
        JOIN recruteurs r ON r.id = ria.recruteur_id
        WHERE ria.resultats_json LIKE ?
        ORDER BY ria.date_recherche DESC
    ");
    $stmt->execute(['%"id":' . $cid . '%']);
    $historique = $stmt->fetchAll();
} catch (Exception $e) {
    // Table peut ne pas exister encore
}
?>
<div class="card">
    <h2>📊 Mes candidatures</h2>
    <?php if (empty($historique)): ?>
        <div class="empty-state">
            <span class="es-icon">📭</span>
            Aucune candidature pour le moment.<br>
            <small style="font-size:13px">Vous apparaîtrez ici quand un recruteur vous aura trouvé via la recherche
                IA.</small>
        </div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <tr>
                    <th>Poste recherché</th>
                    <th>Recruteur</th>
                    <th>Entreprise</th>
                    <th>Statut</th>
                    <th>Date</th>
                </tr>
                <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><?= s($h['poste_recherche'] ?? '—') ?></td>
                        <td><?= s($h['rec_nom'] ?? '—') ?></td>
                        <td><?= s($h['entreprise'] ?? '—') ?></td>
                        <td>
                            <span class="statut <?= s($h['statut'] ?? 'en_attente') ?>">
                                <?= s(ucfirst(str_replace('_', ' ', $h['statut'] ?? 'En attente'))) ?>
                            </span>
                        </td>
                        <td><?= s(date('d/m/Y', strtotime($h['date_candidature']))) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>
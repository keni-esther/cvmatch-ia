<?php
// Variables disponibles depuis le parent : $p (candidat), $comp_affichage, $cv
?>
<div class="card">
    <h2>✏️ Modifier mon profil</h2>
    <form method="POST" id="form-profil">
        <input type="hidden" name="action" value="update_profil">
        <div class="row">
            <div class="fg">
                <label>Téléphone</label>
                <input type="text" name="telephone" value="<?= s($p['telephone'] ?? '') ?>"
                    placeholder="+225 07 00 00 00">
            </div>
            <div class="fg">
                <label>Ville</label>
                <input type="text" name="ville" value="<?= s($p['ville'] ?? '') ?>" placeholder="Abidjan">
            </div>
        </div>
        <div class="fg">
            <label>Années d'expérience</label>
            <input type="number" name="exp" min="0" max="50" value="<?= intval($p['experience_annees'] ?? 0) ?>">
        </div>
        <div class="fg">
            <label>Compétences <span
                    style="color:var(--grey);font-size:10px;text-transform:none;letter-spacing:0">(séparées par des
                    virgules)</span></label>
            <textarea name="competences"
                placeholder="PHP, MySQL, JavaScript, Python..."><?= s($comp_affichage) ?></textarea>
        </div>
        <div class="fg">
            <label>Formation</label>
            <textarea name="formation"
                placeholder="Licence Informatique, ESATIC Abidjan..."><?= s($p['formation'] ?? '') ?></textarea>
        </div>
        <button type="submit" class="btn">💾 Enregistrer les modifications</button>
    </form>
</div>

<div class="card">
    <h2>👤 Résumé de mon profil</h2>
    <div class="info-profil">
        <div class="info-item">
            <div class="lbl">Nom complet</div>
            <div class="val"><?= s(($p['prenom'] ?? '') . ' ' . ($p['nom'] ?? '')) ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">Email</div>
            <div class="val"><?= s($p['email'] ?? '') ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">Téléphone</div>
            <div class="val"><?= s($p['telephone'] ?: '—') ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">Ville</div>
            <div class="val"><?= s($p['ville'] ?: '—') ?></div>
        </div>
        <div class="info-item">
            <div class="lbl">Expérience</div>
            <div class="val"><?= intval($p['experience_annees'] ?? 0) ?> an(s)</div>
        </div>
        <div class="info-item">
            <div class="lbl">CV</div>
            <div class="val"><?= $cv ? '✅ Déposé' : '❌ Non déposé' ?></div>
        </div>
        <div class="info-item" style="grid-column:1/-1">
            <div class="lbl">Compétences</div>
            <div class="val"><?= s($comp_affichage ?: '—') ?></div>
        </div>
        <div class="info-item" style="grid-column:1/-1">
            <div class="lbl">Formation</div>
            <div class="val"><?= s($p['formation'] ?: '—') ?></div>
        </div>
    </div>
</div>
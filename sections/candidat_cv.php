<?php
// Variables disponibles : $candidat, $cid, $db
// Récupérer le CV actuel depuis cv_fichiers
$cv_stmt = $db->prepare("SELECT * FROM cv_fichiers WHERE candidat_id = ? ORDER BY date_upload DESC LIMIT 1");
$cv_stmt->execute([$cid]);
$cv_file = $cv_stmt->fetch();
?>
<div class="card card-cv">
    <h2>📄 Mon CV</h2>

    <?php if ($cv_file): ?>
    <div class="cv-current">
        <div class="cv-icon">📋</div>
        <div class="cv-info">
            <div class="cv-name"><?= s($cv_file['nom_original'] ?? basename($cv_file['chemin_fichier'])) ?></div>
            <div class="cv-date">Déposé le <?= date('d/m/Y', strtotime($cv_file['date_upload'])) ?></div>
        </div>
        <a href="<?= s($cv_file['chemin_fichier']) ?>" target="_blank">👁 Voir</a>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="form-cv">
        <input type="hidden" name="action" value="upload_cv">
        <div class="drop-zone" id="drop-zone">
            <div class="dz-icon">☁️</div>
            <p>
                Glissez-déposez votre CV ici<br>
                <small style="font-size:11px;color:var(--grey)">PDF, DOCX, DOC — max 5 Mo</small>
            </p>
            <input type="file" name="cv" id="cv-input" accept=".pdf,.docx,.doc">
            <label for="cv-input" class="dz-label">Choisir un fichier</label>
            <div class="file-chosen" id="file-chosen"></div>
        </div>
        <br>
        <button type="submit" class="btn">📤 Uploader mon CV</button>
    </form>
</div>

<script>
// Drag & drop
const zone = document.getElementById('drop-zone');
const inp  = document.getElementById('cv-input');
const chosen = document.getElementById('file-chosen');

if (zone && inp) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='var(--orange)'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor=''; });
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.style.borderColor='';
        if (e.dataTransfer.files.length) {
            inp.files = e.dataTransfer.files;
            chosen.textContent = '📎 ' + inp.files[0].name;
        }
    });
    inp.addEventListener('change', () => {
        chosen.textContent = inp.files[0] ? '📎 ' + inp.files[0].name : '';
    });
}
</script>

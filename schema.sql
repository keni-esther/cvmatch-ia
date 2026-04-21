-- ============================================
-- CVMatch IA — Schéma MySQL complet
-- Base : cvmatch_ia
-- ============================================

CREATE DATABASE IF NOT EXISTS cvmatch_ia
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cvmatch_ia;

-- ==================== CANDIDATS ====================
CREATE TABLE IF NOT EXISTS candidats (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    nom               VARCHAR(100)  NOT NULL,
    prenom            VARCHAR(100)  NOT NULL,
    email             VARCHAR(150)  NOT NULL UNIQUE,
    mot_de_passe      VARCHAR(255)  NOT NULL,
    telephone         VARCHAR(20)   DEFAULT '',
    ville             VARCHAR(100)  DEFAULT '',
    titre_profil      VARCHAR(200)  DEFAULT '',
    experience_annees INT           DEFAULT 0,
    competences       TEXT          DEFAULT '',   -- stocké en JSON ["php","mysql",...]
    formation         TEXT          DEFAULT '',
    cv                VARCHAR(255)  DEFAULT '',   -- chemin fichier ex: uploads/cv_1_xxx.pdf
    date_inscription  DATETIME      DEFAULT CURRENT_TIMESTAMP,
    date_maj          DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ==================== RECRUTEURS ====================
CREATE TABLE IF NOT EXISTS recruteurs (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    nom          VARCHAR(100)  NOT NULL,
    email        VARCHAR(150)  NOT NULL UNIQUE,
    mot_de_passe VARCHAR(255)  NOT NULL,
    entreprise   VARCHAR(200)  DEFAULT '',
    date_inscription DATETIME  DEFAULT CURRENT_TIMESTAMP
);

-- ==================== CV FICHIERS ====================
CREATE TABLE IF NOT EXISTS cv_fichiers (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    candidat_id     INT          NOT NULL,
    chemin_fichier  VARCHAR(255) NOT NULL,
    nom_original    VARCHAR(255) DEFAULT '',
    resume_ia       LONGTEXT     DEFAULT '',   -- texte extrait par le service Python
    date_upload     DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (candidat_id) REFERENCES candidats(id) ON DELETE CASCADE
);

-- ==================== RECHERCHES IA ====================
CREATE TABLE IF NOT EXISTS recherche_ia (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    recruteur_id    INT          NOT NULL,
    requete_texte   TEXT         NOT NULL,
    resultats_json  LONGTEXT     DEFAULT '',
    date_recherche  DATETIME     DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (recruteur_id) REFERENCES recruteurs(id) ON DELETE CASCADE
);

-- ==================== INDEX UTILES ====================
CREATE INDEX IF NOT EXISTS idx_cv_candidat  ON cv_fichiers (candidat_id);
CREATE INDEX IF NOT EXISTS idx_rech_recruteur ON recherche_ia (recruteur_id);

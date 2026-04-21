# CVMatch IA — Plateforme de recrutement intelligent

Application web full-stack (PHP + MySQL + Python Flask)

## Stack technique
- PHP 8 + MySQL (XAMPP)
- Python Flask + TF-IDF (microservice IA)
- Gmail SMTP (envoi d'emails)

## Installation

### 1. Cloner le projet
```bash
git clone https://github.com/TON_USERNAME/cvmatch-ia.git
cd cvmatch-ia
```

### 2. Configurer la base de données
```bash
# Importer le schéma dans MySQL
mysql -u root -p < schema.sql
```

### 3. Configurer PHP
```bash
cp config.example.php config.php
# Éditer config.php avec vos identifiants
```

### 4. Configurer les emails
```bash
cp mailer.example.php mailer.php
# Éditer mailer.php avec votre compte Gmail
```

### 5. Lancer le microservice Python
```bash
pip install flask mysql-connector-python
python service_ia.py
```

### 6. Créer le dossier uploads
```bash
mkdir uploads
```

## Structure
```
mijo/
├── sections/          # Fragments PHP
├── uploads/           # CVs (non versionné)
├── config.php         # Configuration (non versionné)
├── mailer.php         # Email config (non versionné)
├── schema.sql         # Structure BDD
└── service_ia.py      # Microservice Flask
```
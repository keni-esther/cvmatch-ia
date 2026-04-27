# Utilise une image Python officielle
FROM python:3.10-slim

# définit le dossier de travail
WORKDIR /app

# Installe les dépendances système (MySQL et PDF)
RUN apt-get update && apt-get install -y \
    default-libmysqlclient-dev \
    poppler-utils \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Copie tous vos fichiers (PHP et Python) dans le container
COPY . .

# Installe vos bibliothèques Python
RUN pip install --no-cache-dir -r requirements.txt

# Commande pour lancer votre script Python
# Remplacez app.py par le nom de votre fichier si nécessaire
CMD ["python3", "service_ia.py"]

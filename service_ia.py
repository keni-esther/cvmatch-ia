"""
CVMatch IA - Microservice Flask
Analyse les candidats et lit les CVs uploadés pour améliorer le matching.
"""

from flask import Flask, request, jsonify
import mysql.connector
import math
import re
import json
import os
import pdfplumber
from docx import Document

app = Flask(__name__)

DB_CONFIG = {
    'host': 'mysql.railway.internal',
    'user': 'root',
    'password': 'agLVoiIdQJqDpDycdpHSWsDRqeWwrrvB',
    'database': 'railway',
    'charset': 'utf8mb4'
}

BASE_DIR = os.path.abspath(os.path.dirname(__file__))
UPLOADS_DIR = os.path.join(BASE_DIR, 'uploads')
CVS_POOL_DIR = os.path.join(BASE_DIR, 'cvs_pool')

STOPWORDS = {
    'le', 'la', 'les', 'de', 'du', 'des', 'un', 'une', 'et', 'en', 'à', 'au', 'aux',
    'est', 'sont', 'par', 'pour', 'dans', 'sur', 'avec', 'plus', 'pas', 'que', 'qui',
    'minimum', 'bonne', 'connaissance', 'disponible', 'ans', 'année', 'années', 'avoir'
}


def get_db():
    return mysql.connector.connect(**DB_CONFIG)


def extract_text_from_file(filepath):
    try:
        ext = filepath.lower().split('.')[-1]
        if ext == 'pdf':
            with pdfplumber.open(filepath) as pdf:
                return ' '.join(page.extract_text() or '' for page in pdf.pages)
        if ext in ['docx', 'doc']:
            doc = Document(filepath)
            return ' '.join(p.text for p in doc.paragraphs)
    except Exception as e:
        print(f"[ERREUR] Lecture du fichier {filepath}: {e}")
    return ''


def tokenize(text):
    text = text.lower()
    text = re.sub(r'[^\w\s]', ' ', text)
    return [w for w in text.split() if w not in STOPWORDS and len(w) > 2]


def decode_competences(val):
    if not val:
        return ''
    try:
        data = json.loads(val)
        return ' '.join(data) if isinstance(data, list) else str(data)
    except Exception:
        return str(val)


def tf(tokens, word):
    return tokens.count(word) / len(tokens) if tokens else 0


def idf(all_docs_tokens, word):
    n = len(all_docs_tokens)
    df = sum(1 for doc in all_docs_tokens if word in doc)
    return math.log(n / (1 + df)) + 1 if df > 0 else 0


def score_tfidf(req_tokens, doc_tokens, all_docs_tokens):
    return sum(tf(doc_tokens, token) * idf(all_docs_tokens, token) for token in set(req_tokens))


def generer_resume(c, content_text, req_tokens):
    if c.get('resume_ia'):
        return c['resume_ia']
    resume = f"{c.get('nom_complet') or 'Candidat'}, {int(c.get('experience_annees') or 0)} an(s) d'expérience"
    if c.get('ville'):
        resume += f", basé(e) à {c.get('ville')}"
    mots_match = [m for m in content_text.split() if any(r in m for r in req_tokens)]
    if mots_match:
        resume += f". Compétences retrouvées : {', '.join(list(dict.fromkeys(mots_match))[:6])}"
    elif len(content_text) > 30:
        resume += f". Extrait de CV : {content_text[:120]}..."
    return resume


def resolve_file_path(path):
    if not path:
        return ''
    if os.path.isabs(path):
        return path if os.path.exists(path) else ''
    candidate = os.path.join(BASE_DIR, path)
    if os.path.exists(candidate):
        return candidate
    alternative = os.path.join(BASE_DIR, path.lstrip('/\\'))
    return alternative if os.path.exists(alternative) else ''


def analyze_folder(folder, source_name, req_tokens):
    results = []
    if not os.path.isdir(folder):
        return results

    for filename in os.listdir(folder):
        if not filename.lower().endswith(('.pdf', '.docx', '.doc')):
            continue

        filepath = os.path.join(folder, filename)
        file_text = extract_text_from_file(filepath)
        if len(file_text) < 20:
            continue

        full_text = ' '.join([filename, file_text]).strip()
        tokens = tokenize(full_text)
        if not tokens:
            continue

        results.append({
            'source': source_name,
            'nom_complet': filename,
            'email': '',
            'telephone': '',
            'ville': '',
            'experience_annees': 0,
            'competences': file_text,
            'nom_fichier': filename,
            'chemin_fichier': os.path.join(source_name, filename).replace('\\', '/'),
            'file_text': full_text,
            'tokens': tokens,
        })

    return results


@app.route('/ping')
def ping():
    return jsonify({'status': 'ok', 'service': 'CVMatch IA Flask'})


@app.route('/health')
def health():
    return jsonify({'status': 'ok'})


@app.route('/matching', methods=['POST'])
def matching():
    data = request.get_json(force=True)
    requete = (data.get('requete') or '').strip()

    if not requete:
        return jsonify({'erreur': 'Requête vide'}), 400

    try:
        db = get_db()
        cursor = db.cursor(dictionary=True)
        cursor.execute("""
            SELECT
                c.id,
                CONCAT(c.prenom, ' ', c.nom) AS nom_complet,
                c.email,
                c.telephone,
                c.ville,
                c.experience_annees,
                c.competences,
                c.formation,
                f.nom_fichier,
                f.chemin_fichier,
                f.resume_ia,
                f.competences_extraites
            FROM candidats c
            LEFT JOIN cv_fichiers f ON f.candidat_id = c.id
            AND f.id = (SELECT MAX(id) FROM cv_fichiers WHERE candidat_id = c.id)
        """)
        candidats = cursor.fetchall()
        cursor.close()
        db.close()
    except Exception as e:
        print(f"[ERREUR DB] {e}")
        return jsonify({'erreur': f'Erreur base de données : {e}'}), 500

    req_tokens = tokenize(requete)
    if not req_tokens:
        return jsonify({'resultats': [], 'total': 0})

    candidate_entries = []
    candidate_texts = []

    for c in candidats:
        competences = decode_competences(c.get('competences'))
        competences_extraites = decode_competences(c.get('competences_extraites'))

        file_text = ''
        chemin = c.get('chemin_fichier') or ''
        resolved_path = resolve_file_path(chemin)
        if resolved_path:
            file_text = extract_text_from_file(resolved_path)

        full_text = ' '.join([
            c.get('nom_complet') or '',
            competences,
            competences_extraites,
            c.get('formation') or '',
            c.get('ville') or '',
            c.get('resume_ia') or '',
            file_text
        ]).strip()

        candidate_entries.append({
            'id': int(c['id']),
            'source': 'candidat',
            'nom_complet': c.get('nom_complet') or '',
            'email': c.get('email') or '',
            'telephone': c.get('telephone') or '',
            'ville': c.get('ville') or '',
            'experience_annees': int(c.get('experience_annees') or 0),
            'competences': ' '.join(filter(None, [competences, competences_extraites, file_text])),
            'nom_fichier': c.get('nom_fichier') or '',
            'chemin_fichier': c.get('chemin_fichier') or '',
            'resume_ia': c.get('resume_ia') or '',
            'content_text': full_text,
        })
        candidate_texts.append(full_text)

    cvs_pool_matches = analyze_folder(CVS_POOL_DIR, 'cvs_pool', req_tokens)
    all_texts = [entry['content_text'] for entry in candidate_entries] + [item['file_text'] for item in cvs_pool_matches]
    docs_tokens = [tokenize(text) for text in all_texts]

    resultats = []
    for idx, entry in enumerate(candidate_entries):
        score = score_tfidf(req_tokens, docs_tokens[idx], docs_tokens)
        if score > 0:
            resultats.append({
                'id': int(entry['id']),
                'nom_complet': entry['nom_complet'],
                'email': entry['email'],
                'telephone': entry['telephone'],
                'ville': entry['ville'],
                'experience_annees': entry['experience_annees'],
                'competences': entry['competences'],
                'nom_fichier': entry['nom_fichier'],
                'chemin_fichier': entry['chemin_fichier'],
                'score': min(99, max(10, round(score * 120))),
                'resume_gen': generer_resume(entry, entry['content_text'], req_tokens)
            })

    offset = len(candidate_entries)
    for idx, item in enumerate(cvs_pool_matches):
        score = score_tfidf(req_tokens, docs_tokens[offset + idx], docs_tokens)
        if score > 0:
            resultats.append({
                'id': 0,
                'source': 'cvs_pool',
                'nom_complet': f"CV pool — {item['nom_complet']}",
                'email': '',
                'telephone': '',
                'ville': '',
                'experience_annees': 0,
                'competences': item['competences'],
                'nom_fichier': item['nom_fichier'],
                'chemin_fichier': item['chemin_fichier'],
                'score': min(99, max(10, round(score * 120))),
                'resume_gen': generer_resume(item, item['file_text'], req_tokens)
            })

    resultats.sort(key=lambda x: x['score'], reverse=True)

    print(f"[OK] '{requete}' → {len(resultats)} résultat(s)")
    return jsonify({'resultats': resultats[:15], 'total': len(resultats)})


if __name__ == '__main__':
    os.makedirs(UPLOADS_DIR, exist_ok=True)
    os.makedirs(CVS_POOL_DIR, exist_ok=True)
    print('=' * 55)
    print('  CVMatch IA — Microservice Flask :5000')
    print('=' * 55)
    app.run(host='0.0.0.0', port=5000, debug=True)

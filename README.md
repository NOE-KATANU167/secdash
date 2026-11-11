<div align="center">

# SecDash

**Voir les attaques par force brute avant qu’elles n’aboutissent.**

Un tableau de bord de sécurité qui transforme les journaux de connexion en alertes claires.

![PHP](https://img.shields.io/badge/PHP-8-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-MariaDB-4479A1?logo=mysql&logoColor=white)
![Apache](https://img.shields.io/badge/Apache-XAMPP-D22128?logo=apache&logoColor=white)
![Chart.js](https://img.shields.io/badge/Chart.js-4-FF6384?logo=chartdotjs&logoColor=white)

<br>

<img src="docs/img/dashboard.png" alt="Tableau de bord SecDash" width="860">

</div>

---

## Le constat

Une attaque par force brute commence presque toujours par une série d’échecs de connexion. Ces traces existent dans les journaux, mais personne ne les lit en temps réel : l’attaque passe inaperçue jusqu’à ce qu’elle réussisse.

SecDash rend ces signaux visibles. Chaque événement de connexion est enregistré, analysé, et les comportements suspects remontent sous forme d’alertes qu’un administrateur peut traiter en quelques clics.

## Comment ça marche

| 1. Collecte | 2. Détection | 3. Alerte | 4. Action |
|---|---|---|---|
| Chaque connexion réussie, échouée ou refusée est journalisée avec l’IP, l’utilisateur et l’horodatage. | Cinq échecs en dix minutes depuis une même adresse déclenchent la règle. | Une alerte est créée, de gravité moyenne, puis haute à partir de dix échecs. | L’administrateur la classe (nouvelle, en cours, résolue) ou bloque l’adresse. |

Les journaux peuvent venir de l’application elle-même **ou d’autres machines**, grâce à une API d’ingestion protégée par une clé secrète.

## Aperçu

<div align="center">

| Connexion | Alertes |
|:---:|:---:|
| <img src="docs/img/login.png" alt="Page de connexion" width="420"> | <img src="docs/img/alertes.png" alt="Alertes et blocage d’IP" width="420"> |

</div>

## La sécurité dès la conception

SecDash est lui-même un outil de sécurité : il doit résister aux attaques qu’il surveille.

| Menace | Réponse dans SecDash |
|---|---|
| Force brute sur la connexion | Verrouillage du compte 15 minutes après 5 échecs, alerte automatique |
| Injection SQL | Requêtes préparées PDO sur tous les accès à la base |
| CSRF | Jeton unique vérifié sur chaque formulaire |
| Vol ou fixation de session | Session régénérée à la connexion, cookie `HttpOnly` et `SameSite=Strict`, expiration après 30 minutes d’inactivité |
| XSS | Sortie HTML échappée systématiquement, en-tête `Content-Security-Policy` strict |
| Fuite d’identifiants | Mots de passe hachés (`password_hash`), message d’erreur volontairement vague, compte MySQL aux droits limités |
| Exposition de fichiers sensibles | Seul le dossier `public/` est servi ; configuration et secrets restent hors de portée du navigateur |
| Abus de l’API | Clé comparée en temps constant (`hash_equals`), validation stricte des champs, corps limité à 10 Ko |

## Architecture

```
secdash/
├── config/          connexion à la base, secrets (non publiés)
├── includes/        authentification, CSRF, détection
├── public/          seul dossier exposé au web
│   ├── api/         API d’ingestion des journaux
│   └── assets/      CSS et JavaScript
├── sql/schema.sql   structure de la base
└── tools/           création du premier administrateur
```

Aucun framework : chaque mécanisme de sécurité est écrit explicitement dans le code, ce qui le rend lisible et vérifiable.

## Limites actuelles et prochaines étapes

SecDash est un projet en cours de construction. Ce qui manque, et qui fait la suite de la feuille de route :

| Limite | Évolution prévue |
|---|---|
| Pas de HTTPS | Indispensable avant tout usage hors d’un poste local |
| Authentification par mot de passe seul | Double authentification (TOTP) pour les administrateurs |
| Pas de limitation de débit sur l’API | Plafond de requêtes par clé |
| Le blocage d’IP agit sur la page de connexion | Blocage au niveau du pare-feu |
| Consultation limitée aux dernières alertes | Page de journaux avec filtres, déblocage d’IP, export CSV |

<details>
<summary><strong>Lancer le projet en local (XAMPP, Windows)</strong></summary>

<br>

1. Placer le projet dans un dossier, par exemple `D:\secdash`, et exposer uniquement `public/` dans `httpd.conf` :

   ```apache
   Alias /secdash "D:/secdash/public"
   <Directory "D:/secdash/public">
       Require all granted
   </Directory>
   ```

2. Importer la base avec `mysql -u root < sql/schema.sql` (après avoir remplacé le mot de passe provisoire de `secdash_user` dans le fichier).
3. Copier `config/db.example.php` vers `config/db.php` et `config/secrets.example.php` vers `config/secrets.php`, puis y renseigner le mot de passe de la base et une clé générée avec `php -r "echo bin2hex(random_bytes(32));"`.
4. Créer le premier administrateur : `php tools/create_admin.php`.
5. Ouvrir `http://localhost/secdash/login.php`.

**Tester la détection** en envoyant six échecs à l’API depuis une adresse fictive :

```powershell
$body = @{ event_type="login_failed"; username="root"; ip_address="203.0.113.45"; user_agent="test" } | ConvertTo-Json
1..6 | ForEach-Object {
  Invoke-RestMethod -Uri "http://localhost/secdash/api/ingest.php" -Method Post `
    -Headers @{ "X-API-Key" = "VOTRE_CLE" } -ContentType "application/json" -Body $body
}
```

Une alerte apparaît alors dans le tableau de bord pour `203.0.113.45`, une adresse réservée à la documentation.

</details>

---

<div align="center">

## À propos de l’auteur

<img src="docs/img/auteur.jpeg" alt="Noé Kamba Katanu" width="160">

### Noé Kamba Katanu

Étudiant en master réseaux et cybersécurité à l’Université de Kinshasa (UNIKIN).<br>
Détection des attaques, sécurité défensive, et impact de l’intelligence artificielle sur la cybersécurité.

[GitHub](https://github.com/NOE-KATANU167) · [LinkedIn](https://www.linkedin.com/in/no%C3%A9-katanu-80147b343)

</div>

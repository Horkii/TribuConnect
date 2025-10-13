TribuConnect — Calendrier familial partagé

Prérequis
- PHP 8.1+
- Composer 2
- SQLite (par défaut) ou MySQL/PostgreSQL
- (Optionnel) Redis pour le compteur de visites NoSQL

Installation (Docker recommandé)
- Prérequis: Docker Desktop (ou Docker + Compose v2)
- Démarrer l’environnement: `docker compose up --build`
- Ouvrir: http://localhost:8080

Ce que fait le conteneur `app` au démarrage
- Installe les dépendances Composer si absentes
- Attend que Postgres soit prêt
- Exécute les migrations Doctrine (création du schéma)
- Lance Apache (DocumentRoot `public/`)

Fonctionnalités
- Inscription/connexion sécurisée (CSRF, hash de mot de passe)
- Profil utilisateur (CRUD des informations personnelles)
- Création d’une seule famille par utilisateur et invitations email
- Discussions partagées de la famille
- Calendrier des événements (annuel pour anniversaires)
- Album photo lié à des événements
- Compteur de visites de la page d’accueil via Redis (NoSQL), fallback fichier

Sécurité
- Formulaires avec CSRF et validation serveur (Validator)
- Accès contrôlés (SecurityBundle)
- Doctrine ORM (requêtes paramétrées) pour éviter l’injection SQL
- Upload de fichiers avec validation des types MIME et nommage aléatoire

Notes
- Les emails d’invitation utilisent `MAILER_DSN`. Pour dev, `file:///var/mails` les écrit sur disque.
- Par défaut la DB est SQLite (fichier `var/data.db`). Remplacez par MySQL/PostgreSQL en prod.

Configuration Postgres (via Docker)
- Service: `db` (Postgres 16)
- DSN (dans le conteneur app): `postgresql://tribu:tribu@db:5432/tribu?serverVersion=16&charset=utf8`
- Redéfinissez via variables d’environnement dans `docker-compose.yml` si besoin

Exécution locale (sans Docker)
1. `composer install`
2. Dans `.env`, définir `DATABASE_URL` pour votre Postgres
3. `php bin/console doctrine:migrations:migrate -n`
4. `php -S localhost:8000 -t public`

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

Déploiement (production)

Option A — Railway (le plus simple)
- Déployez le repo depuis GitHub sur https://railway.app
- Ajoutez Postgres et Redis managés
- Variables d’environnement:
  - `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET=<générez>`
  - `DATABASE_URL` (fourni par Railway Postgres)
  - `REDIS_URL` (fourni par Railway Redis)
  - `MAILER_DSN` (SMTP réel: Brevo/Sendgrid/OVH)
- Démarrez: le conteneur applique automatiquement les migrations.
- Attachez votre domaine et testez `/health`.

Option B — VPS avec Docker Compose
- Créez un fichier `.env.prod`:
  - `APP_ENV=prod`, `APP_DEBUG=0`, `APP_SECRET=<secret>`
  - `DATABASE_URL=postgresql://tribu:tribu@db:5432/tribu?serverVersion=16&charset=utf8`
  - `MAILER_DSN=smtp://<user>:<pass>@<host>:<port>`
  - `REDIS_URL=redis://redis:6379`
- Lancez: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build`
- Placez un reverse proxy (Nginx/Caddy) devant `:8080`, activez HTTPS.
- Vérifiez `/health`.

Post‑déploiement
- Créez un compte admin: `docker compose exec app php bin/console app:create-admin admin@tribuconnect.fr --enable-2fa`
- Migrations (forçage manuel si besoin): `docker compose exec app php bin/console doctrine:migrations:migrate -n`
- Configurez l’email expéditeur (`config/services.yaml: app.mail_from`) et `MAILER_DSN`.

Sécurité en prod
- Anti brute‑force + rate limit (5/min) pour connexion/inscription/contact.
- Formulaires protégés par CSRF; Contact a un honeypot.
- Nettoyage des entrées et échappement Twig.

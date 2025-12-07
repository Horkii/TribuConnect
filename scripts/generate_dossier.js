/*
  Génère un dossier projet Word (DOCX) en français pour TribuConnect
  - Ajoute des sections explicatives (architecture, sécurité, routes, etc.)
  - Insère des images de code générées automatiquement à partir des fichiers du dépôt
*/

const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const { Document, Packer, Paragraph, HeadingLevel, TextRun, TableOfContents, ImageRun, AlignmentType, PageOrientation } = require('docx');
const sizeOf = require('image-size');
// (images désactivées) const puppeteer = undefined;

const ROOT = process.cwd();
const OUT_DIR = path.join(ROOT, 'docs');
// Images désactivées dans cette version texte uniquement
const OUTPUT_DOCX = path.join(OUT_DIR, 'TribuConnect_Dossier_Projet.docx');

function ensureDirSync(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

function readFileSafe(filePath) {
  try {
    return fs.readFileSync(filePath, 'utf8');
  } catch (e) {
    return '';
  }
}

function chunkByLines(text, linesPerChunk = 60) {
  const lines = text.split(/\r?\n/);
  const chunks = [];
  for (let i = 0; i < lines.length; i += linesPerChunk) {
    const part = lines.slice(i, i + linesPerChunk).join('\n');
    chunks.push({ start: i + 1, end: Math.min(i + linesPerChunk, lines.length), text: part });
  }
  return chunks;
}

function escapeHtml(s) {
  return s
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;');
}

async function renderCodeChunkImage(browser, codeText, title, outPath, options = {}) {
  const width = options.width || 1200;
  const fontSize = options.fontSize || 14;
  const bg = options.bg || '#0b1020';
  const fg = options.fg || '#e6edf3';
  const border = options.border || '#1f2a44';
  const titleBg = options.titleBg || '#111936';
  const titleFg = options.titleFg || '#b9c1d9';
  const code = escapeHtml(codeText);
  const html = `<!doctype html>
  <html><head>
    <meta charset="utf-8" />
    <style>
      html, body { margin:0; padding:0; background: ${bg}; }
      .wrap { width:${width}px; box-sizing: border-box; padding: 16px; background:${bg}; color:${fg}; font-family: 'Consolas', 'SFMono-Regular', 'Menlo', monospace; }
      .title { background:${titleBg}; color:${titleFg}; padding:10px 14px; border:1px solid ${border}; border-bottom:0; border-radius:8px 8px 0 0; font: 600 16px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; }
      pre { margin:0; padding: 14px 16px; box-sizing: border-box; white-space: pre; overflow: hidden; font-size:${fontSize}px; line-height:1.4; background:${bg}; color:${fg}; border:1px solid ${border}; border-top:0; border-radius:0 0 8px 8px; }
      .code { counter-reset: line; }
      .line { display:block; }
    </style>
  </head>
  <body>
    <div class="wrap">
      <div class="title">${title}</div>
      <pre id="code"><code class="code">${code}</code></pre>
    </div>
  </body></html>`;

  const page = await browser.newPage();
  await page.setViewport({ width, height: 2000, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'domcontentloaded' });
  const el = await page.$('.wrap');
  if (!el) {
    await page.screenshot({ path: outPath, fullPage: true });
  } else {
    await el.screenshot({ path: outPath });
  }
  await page.close();
}

async function generateCodeImages(browser, filePath, title, maxLinesPerImage = 60) {
  const abs = path.isAbsolute(filePath) ? filePath : path.join(ROOT, filePath);
  const content = readFileSafe(abs);
  if (!content) return [];
  const chunks = chunkByLines(content, maxLinesPerImage);
  const out = [];
  const baseName = filePath.replace(/[\\/:]/g, '_');
  for (let i = 0; i < chunks.length; i++) {
    const c = chunks[i];
    const caption = `${filePath} (lignes ${c.start}–${c.end})`;
    const imgPath = path.join(IMG_DIR, `${baseName}_${c.start}-${c.end}.png`);
    await renderCodeChunkImage(browser, c.text, caption, imgPath);
    out.push({ path: imgPath, caption });
  }
  return out;
}

function p(text) {
  return new Paragraph({ children: [new TextRun(text)] });
}

function h(text, level = HeadingLevel.HEADING_2) {
  return new Paragraph({ text, heading: level });
}

function center(text, size = 48) {
  return new Paragraph({ alignment: AlignmentType.CENTER, children: [new TextRun({ text, size })] });
}

async function main() {
  ensureDirSync(OUT_DIR);
  // Pas de dossier images: version texte uniquement

  const doc = new Document({
    creator: 'TribuConnect',
    title: 'Dossier Projet — TribuConnect',
    description: 'Dossier projet (FR) du calendrier familial partagé TribuConnect.',
    sections: []
  });

  // Page de garde
  const coverImagePath = path.join(ROOT, 'public', 'TribuConnect.png');
  const coverImageExists = fs.existsSync(coverImagePath);
  const coverChildren = [
    new Paragraph({ children: [new TextRun({ text: 'TribuConnect', bold: true, size: 64 })], alignment: AlignmentType.CENTER }),
    new Paragraph({ children: [new TextRun({ text: 'Calendrier familial partagé et organisation de tribu', size: 28 })], alignment: AlignmentType.CENTER }),
    new Paragraph({ text: '' }),
  ];
  if (coverImageExists) {
    const buf = fs.readFileSync(coverImagePath);
    let w = 320, h = 320;
    try {
      const dim = sizeOf.imageSize(buf);
      if (dim && dim.width && dim.height) {
        const maxW = 320;
        w = maxW;
        h = Math.round(dim.height * (maxW / dim.width));
      }
    } catch {}
    const img = new ImageRun({ data: buf, transformation: { width: w, height: h } });
    coverChildren.push(new Paragraph({ children: [img], alignment: AlignmentType.CENTER }));
  }
  coverChildren.push(new Paragraph({ text: '' }));
  coverChildren.push(new Paragraph({ children: [new TextRun({ text: `Date: ${new Date().toLocaleDateString('fr-FR')}` })], alignment: AlignmentType.CENTER }));

  doc.addSection({ properties: { page: { margin: { top: 720, bottom: 720, left: 720, right: 720 } } }, children: coverChildren });

  // Sommaire
  doc.addSection({
    children: [
      h('Sommaire', HeadingLevel.HEADING_1),
      new TableOfContents('Table des matières', {
        hyperlink: true,
        headingStyleRange: '1-5',
      }),
    ],
  });

  // Introduction et Contexte
  const intro = [
    h('Introduction', HeadingLevel.HEADING_1),
    p("TribuConnect est une application web en PHP 8.1+/Symfony 6.4 visant à faciliter l’organisation au sein d’une tribu familiale: gestion d’utilisateurs, familles, invitations, calendrier d’évènements, discussions et albums photos. L’interface s’appuie sur Twig et PicoCSS pour des écrans sobres et responsive. La persistance utilise Doctrine ORM (PostgreSQL/SQLite) et un composant NoSQL via Redis pour des usages scalaires (compteur, rate limiting)."),
    p("Ce dossier projet s’adresse à un public de développeurs. Il détaille les décisions d’architecture, les compromis techniques, la sécurité, les composants métiers et les patterns appliqués côté back-end et front-end. Chaque compétence ciblée est documentée avec des explications techniques approfondies et illustrée par des extraits de code."),
    p("Au-delà des fonctionnalités, l’accent est mis sur: (1) la reproductibilité de l’environnement (Docker), (2) la maintenabilité (structure modulaire, conventions Symfony), (3) l’évolutivité (séparation des responsabilités, ORM, services), (4) la sécurité (CSRF, validation, RBAC, anti brute-force), et (5) l’opérabilité (healthcheck, logs, migrations)."),
    h('Objectifs fonctionnels', HeadingLevel.HEADING_2),
    p("- Proposer un calendrier familial partagé pour planifier et consulter des évènements (annuels pour anniversaires)."),
    p("- Offrir des espaces privés par famille (discussions, album photos)."),
    p("- Simplifier l’onboarding via invitations par email et gestion de profil utilisateur."),
    p("- Instrumenter un compteur de visites NoSQL/Redis avec repli fichier en cas d’indisponibilité."),
  ];
  doc.addSection({ children: intro });

  // Choix techniques et environnement
  const choices = [
    h('Choix Techniques & Environnement', HeadingLevel.HEADING_1),
    p("Langages et frameworks: PHP 8.1+ et Symfony 6.4 pour la maturité de l’écosystème, la sécurité intégrée (SecurityBundle, CSRF, Validator), la productivité (Form, Twig, Maker) et la maintenabilité (conventions claires, injection de dépendances). Twig est privilégié à un framework SPA pour un coût moindre côté front et une surface d’attaque réduite."),
    p("Base de données: PostgreSQL en cible (transactions ACID, opérateurs avancés, stabilité), SQLite par défaut pour la simplicité locale. Doctrine ORM offre l’abstraction des requêtes, la migration du schéma, la conversion objet-relationnel et une testabilité accrue (repositories, QueryBuilder)."),
    p("NoSQL: Redis, via Predis, est utilisé pour des données à faible criticité et à forte contention (compteurs, rate limiting). Le modèle clé-valeur, la latence faible et TTL simplifient ces cas d’usage. Un fallback disque est implémenté pour assurer une disponibilité dégradée."),
    p("Conteneurisation: Docker Compose isole l’app, la base et les services annexes. L’environnement est reproductible, versionné et proche de la production. Les variables d’environnement pilotent le comportement (APP_ENV, DATABASE_URL, MAILER_DSN, REDIS_URL)."),
    p("Sécurité: Hachage de mots de passe (PasswordHasher), CSRF systématique sur formulaires sensibles, RBAC (ROLE_USER/ROLE_ADMIN), validation serveur, sanitation des saisies et contrôles d’upload (MIME, nommage aléatoire). Un subscriber implémente l’anti brute-force et le rate limit (5/min)."),
    p("Front-end: HTML sémantique, PicoCSS, Roboto, et un JS léger (navigation responsive, interactions de base) pour privilégier l’accessibilité, la performance et la simplicité. Les pages sont SSR (Twig) ce qui réduit la charge côté client et facilite l’indexation.")
  ];
  doc.addSection({ children: choices });

  // Environnement de travail & installation (détaillé)
  const envDetailed = [
    h('Environnement de Travail & Installation', HeadingLevel.HEADING_1),
    p("Outils utilisés: PHP 8.1+, Composer 2, Node.js (utilitaires), Docker Desktop (Compose v2), PostgreSQL 16, Redis (optionnel). Mon choix d’outillage vise à standardiser l’environnement de développement et réduire les écarts entre machines. Docker me permet d’avoir une base de données et un serveur web reproductibles."),
    p("Installation: après clonage du projet, j’exécute `composer install` pour résoudre les dépendances Symfony/Doctrine. En mode Docker, `docker compose up --build` lance l’orchestration (service app, base, etc.). Sans Docker, je configure `DATABASE_URL` dans `.env` puis `php bin/console doctrine:migrations:migrate -n` pour créer le schéma."),
    p("Configuration des variables: `APP_ENV`, `APP_DEBUG`, `APP_SECRET` (clé cryptographique), `MAILER_DSN` (SMTP en prod, file:// en dev), `REDIS_URL` (si Redis est actif). Ces variables pilotent le comportement applicatif, par exemple la destination des mails d’invitation ou l’activation du compteur de visites sur Redis."),
    p("Structure du code: `src/Controller` (contrôleurs HTTP), `src/Entity` (entités Doctrine), `src/Repository` (requêtes typées), `src/Form` (formulaires + validation), `src/Service` (préoccupations transverses), `templates/` (Twig), `config/` (fichiers YAML), `migrations/` (évolution du schéma), `public/` (assets, index.php)."),
    p("Commandes de base: `php -S localhost:8000 -t public` pour un test rapide sans Docker; en Docker: `docker compose exec app php bin/console ...` pour exécuter les commandes Symfony à l’intérieur du conteneur (migrations, création d’admin, etc.).")
  ];
  doc.addSection({ children: envDetailed });

  // Architecture
  const arch = [
    h('Architecture & Stack', HeadingLevel.HEADING_1),
    p("- Framework: Symfony 6.4 (Controller, Security, Form, Validator, Twig)."),
    p("- Persistance: Doctrine ORM (PostgreSQL/SQLite), Migrations Doctrine."),
    p("- Front: Twig + PicoCSS + assets simples."),
    p("- Services internes: compteur de visites Redis (fallback fichier), envoi email via Mailer, SMS (dev simulé)."),
    p("- Conteneurisation: Docker Compose (app, db, mailer/redis si activés)."),
    p("- Sécurité: CSRF, validation serveur, contrôle d’accès par rôle, protection anti-brute-force."),
  ];
  doc.addSection({ children: arch });

  // Mise en place (README)
  const readme = readFileSafe(path.join(ROOT, 'README.md'));
  const setup = [
    h('Mise en Place & Déploiement', HeadingLevel.HEADING_1),
    h('Prérequis', HeadingLevel.HEADING_2),
    p("PHP 8.1+, Composer, Docker Desktop (recommandé) et une base PostgreSQL/SQLite. Redis est optionnel pour le compteur de visites."),
    h('Démarrage (Docker)', HeadingLevel.HEADING_2),
    p("• docker compose up --build => application servie sur http://localhost:8080"),
    h('Exécution locale (sans Docker)', HeadingLevel.HEADING_2),
    p("• composer install, configuration DATABASE_URL dans .env, doctrine:migrations:migrate, puis serveur PHP intégré."),
    h('Déploiement (prod)', HeadingLevel.HEADING_2),
    p("• Variables d’environnement (APP_ENV=prod, APP_DEBUG=0, MAILER_DSN, DATABASE_URL, REDIS_URL), reverse proxy et HTTPS."),
  ];
  doc.addSection({ children: setup });

  // Sécurité (approfondie)
  const security = [
    h('Sécurité (Approfondie)', HeadingLevel.HEADING_1),
    p("Authentification: formulaire sécurisé (CSRF, POST uniquement), gestion d’erreurs discrète (éviter l’énumération d’utilisateurs), hashage via PasswordHasher (algorithme auto, salage et paramétrage dépendant de la plateforme)."),
    p("Autorisations: RBAC par rôles (ROLE_USER, ROLE_ADMIN), contrôle d’accès dans security.yaml (access_control) et vérifications d’autorisation contextuelles dans les contrôleurs (ex: propriétaire de la famille)."),
    p("CSRF & Validation: chaque formulaire sensible inclut un jeton synchronisé, les données sont validées par contraintes (Assert) côté serveur. Twig échappe les variables pour prévenir l’injection XSS. Les uploads vérifient le MIME et imposent un nom fichier aléatoire pour briser la prédictibilité."),
    p("Rate limiting & Anti brute‑force: un EventSubscriber limite à 5 requêtes/min par IP pour les routes critiques (connexion, inscription, contact, actions famille), via Redis si disponible sinon par session. Un délai fixe (2s) est introduit pour augmenter le coût d’attaque."),
    p("Sécurité opérationnelle: séparation des secrets (APP_SECRET, MAILER_DSN) en env vars, HTTPS via reverse proxy, entêtes de sécurité (via proxy), logs d’accès et d’erreurs, mise à jour régulière des dépendances, principe du moindre privilège pour les services (DB user)."),
    p("Gestion des sessions et cookies: en production, les cookies sont marqués `Secure`/`HttpOnly` et le SameSite est configuré pour prévenir les CSRF inter‑sites. Les identifiants de session ne sont jamais exposés côté client. La rotation des sessions est déclenchée lors des événements sensibles."),
    p("Revue de surface d’attaque: endpoints publics restreints, aucune inclusion dynamique de templates non maîtrisés, pas de sérialisation non sécurisée, pas d’upload arbitraire sans contrôle de MIME/extension, et pas de données sensibles exposées dans les messages d’erreur.")
  ];
  doc.addSection({ children: security });

  // Modèle de données
  const model = [
    h('Modèle de Données', HeadingLevel.HEADING_1),
    p("Entités principales: User, Family, Invitation, Event, Message, Photo, ContactMessage."),
    p("Relations: User<->Family (ManyToMany), Family->Messages/Events/Invitations/Photos (OneToMany)."),
  ];
  doc.addSection({ children: model });

  // Contrôleurs & Routes
  const routes = [
    h('Routes & Contrôleurs', HeadingLevel.HEADING_1),
    p("- Accueil: /, Santé: /health."),
    p("- Authentification: /connexion, /inscription, /deconnexion."),
    p("- Espaces: /famille, /calendrier, /profil, /photos, /administration."),
    p("Contrôleurs: HomeController, SecurityController, FamilyController, ProfileController, PhotoController, etc."),
  ];
  doc.addSection({ children: routes });

  // Templates & UI
  const ui = [
    h('Templates & UI', HeadingLevel.HEADING_1),
    p("Base Twig (templates/base.html.twig) avec en-tête, navigation responsive, zones de contenu (hero, body), meta SEO/OG."),
    p("PicoCSS pour un style minimaliste, fonts Google, et images (logo, bannière)."),
  ];
  doc.addSection({ children: ui });

  // Templates & UI — Détails
  const uiDetails = [
    h('Templates & UI — Détails', HeadingLevel.HEADING_2),
    p("Blocks et héritage Twig: j'ai défini des blocks (title, meta, og, javascripts, body) afin de factoriser l'ossature et n'écrire que le spécifique dans chaque page. Cette approche réduit le coût de maintenance et aligne l'UX sur tout le site."),
    p("Accessibilité: la navigation est opérable au clavier, le menu mobile se ferme à la touche Échap et au clic sur les liens, les images possèdent des attributs alt et les contrastes sont vérifiés pour garantir une lecture confortable."),
  ];
  doc.addSection({ children: uiDetails });

  // Services
  const services = [
    h('Services & Intégrations', HeadingLevel.HEADING_1),
    p("VisitCounter: compteur de visites (Redis avec repli fichier)."),
    p("SmsSender (dev): simulation d’envoi SMS via Mailer + logs."),
  ];
  doc.addSection({ children: services });

  // Services — Détails
  const servicesDetails = [
    h('Services — Détails', HeadingLevel.HEADING_2),
    p("Mailer: en développement, le transport `file://` écrit les emails sur disque pour inspection. En production, `MAILER_DSN` pointe vers un SMTP authentifié et l'expéditeur par défaut est défini dans `services.yaml` pour homogénéiser l'envoi."),
    p("Normalisation des entrées: une extension de formulaire (ex.: SanitizeExtension) permet de nettoyer les champs (trim, suppression d'espaces superflus) avant traitement. Cela améliore la qualité des données et diminue la complexité des validations personnalisées."),
  ];
  doc.addSection({ children: servicesDetails });

  // Processus de développement (approfondi)
  const process = [
    h('Processus de Développement', HeadingLevel.HEADING_1),
    p("Cadrage: définition des personas (parents, enfants majeurs), parcours clés (inscription, création d’une famille, invitation, ajout d’évènements/photos), contraintes (RGPD, simplicité, mobile-first). Priorisation en MVP: auth, famille unique, calendrier annuel, messages, photos."),
    p("Modélisation: identification des agrégats (Family comme racine pour Messages/Events/Invitations/Photos), mapping Doctrine, contraintes d’intégrité (CASCADE / SET NULL), indexes (uniques sur email, token)."),
    p("Sécurité by design: décisions dès la modélisation (owner de la famille, droits d’édition/suppression), traitement d’erreurs explicite, validation systématique, séparation des responsabilités (contrôleurs minces + services)."),
    p("UI & DX: templates réutilisables, navigation responsive, composants de formulaire homogènes, messages flash. DX: scripts Composer/NPM, Docker Compose, make-like tasks, healthcheck."),
    p("Observabilité: endpoint /health, logs applicatifs (Monolog), métriques simples (compteurs Redis), journalisation des erreurs critiques et alertes côté infrastructure (à brancher)."),
    p("Livraison: migrations Doctrine à chaque itération, création d’admin via commande CLI, documentation de déploiement et de rollback."),
  ];
  doc.addSection({ children: process });

  // Validation des compétences — Front-end
  const compFE = [
    h('Compétences — Front‑end', HeadingLevel.HEADING_1),
    h('Installer son environnement de travail', HeadingLevel.HEADING_2),
    p("Poste Dev: Node.js pour les utilitaires (génération de dossier, assets), PHP 8.1+, Composer 2 pour les dépendances Symfony, Docker Desktop pour un environnement reproductible. Variables d’environnement dans .env/.env.local pour paramétrer DB, mailer, Redis."),
    p("Stack front: Twig pour le SSR; PicoCSS pour un design sobre sans surcharge; Roboto depuis Google Fonts; icônes PNG/Apple Touch; CDN minimal pour limiter la dette front. Les pages utilisent des balises sémantiques et des attributs ARIA quand nécessaire."),
    h('Réaliser des interfaces utilisateurs statiques', HeadingLevel.HEADING_2),
    p("Mise en page: un template ‘base.html.twig’ définit ‘header/nav/main/footer’ et une hero image. Le layout responsif s’appuie sur flexbox et media queries, sans framework JS. Les métadonnées SEO/OG et JSON‑LD sont fournies pour l’indexation et le partage."),
    p("Styles: usage de PicoCSS + styles ciblés (barre de navigation, dropdown, drawer mobile). Les composants sont basés sur des classes utilitaires; pas de préprocesseur nécessaire à ce stade pour réduire la complexité."),
    h('Développer la partie dynamique des interfaces', HeadingLevel.HEADING_2),
    p("JS léger: un script inline gère l’ouverture/fermeture du menu burger et le backdrop, y compris l’accessibilité au clavier (Escape) et la fermeture au clic dans le menu. Les formulaires s’appuient sur la validation serveur avec feedback via messages flash pour garantir la cohérence métier."),
    p("Approche: progressive enhancement — l’UX ne dépend pas du JS pour les fonctionnalités critiques (auth, soumissions), le JS améliore seulement le confort. Cela réduit le risque en cas de désactivation du JS ou d’erreurs de chargement côté client."),
  ];
  doc.addSection({ children: compFE });

  // Compétences — Front-end (Détails)
  const compFEDetails = [
    h('Compétences — Front-end (Détails)', HeadingLevel.HEADING_2),
    p("Commandes utiles: `npm init -y` pour initialiser un outillage minimal, `npm install` pour ajouter des utilitaires front au besoin. J’ai volontairement limité la dette front (pas de bundler ni de framework JS) pour maximiser la sécurité et la maintenabilité, tout en répondant aux besoins du projet."),
  ];
  doc.addSection({ children: compFEDetails });

  // Validation des compétences — Back‑end
  const compBE = [
    h('Compétences — Back‑end', HeadingLevel.HEADING_1),
    h('Développer des composants d’accès SQL et NoSQL', HeadingLevel.HEADING_2),
    p("SQL: Doctrine ORM mappe les entités (User, Family, Event, Message, Photo, Invitation, ContactMessage) et gère le UnitOfWork. Les repositories exposent des méthodes de requêtage typées (ex: PhotoRepository::findByFamily via QueryBuilder). Les relations sont configurées avec contraintes de suppression (‘onDelete’) pour garantir l’intégrité."),
    p("NoSQL: Redis (via Predis) pour le compteur de visites (VisitCounter) et le rate limiting (AntiBruteForceSubscriber). Les clés sont nommées (visits:homepage, rl:<contexte>:<ip>) et TTL pour les fenêtres temporelles. Un fallback disque assure la disponibilité hors Redis."),
    h('Développer des composants métier côté serveur', HeadingLevel.HEADING_2),
    p("Contrôleurs: implémentent les cas d’usage: inscription, création/gestion de famille, invitations (token signifiant), messages, photos, profil. La logique transversale (email, SMS simulé, compteur) est extraite en services. Les FormType encapsulent la validation métier et la présentation des champs."),
    p("Transactions & invariants: Doctrine persiste via flush() transactionnelle par requête. Les invariants sont garantis par validation (Assert) et contrôles applicatifs (ex: autorisations conditionnées au propriétaire de la famille; CSRF sur mutations; vérifications de membre avant transfert de propriété)."),
    h('Documenter le déploiement d’une application web', HeadingLevel.HEADING_2),
    p("Docker Compose: services app (PHP‑Apache), db (PostgreSQL), redis (optionnel). Au démarrage, l’app installe les dépendances et exécute les migrations. Variables d’environnement en .env/.env.prod: APP_ENV, APP_DEBUG, APP_SECRET, DATABASE_URL, MAILER_DSN, REDIS_URL."),
    p("Procédure: build et up en mode détaché, reverse proxy en frontal (Nginx/Caddy) avec HTTPS, vérification via /health, création d’un compte admin par commande CLI. Mises à jour: schéma DB via doctrine:migrations:migrate, rollback si nécessaire, sauvegardes régulières de la base et des uploads."),
  ];
  doc.addSection({ children: compBE });

  // Compétences — Back-end (Détails)
  const compBEDetails = [
    h('Compétences — Back-end (Détails)', HeadingLevel.HEADING_2),
    p("Commandes récapitulatives: `composer install`, `php bin/console doctrine:migrations:migrate -n`, `docker compose up -d --build`, `docker compose exec app php bin/console app:create-admin ...`. Ces commandes standardisent l’installation et l’exploitation, réduisant les écarts d’environnements."),
    p("Justification: ce flux favorise la transparence et prépare une future CI/CD. Les secrets restent dans les variables d’environnement, et l’endpoint `/health` expose un signal de disponibilité pour l’orchestrateur et la supervision."),
  ];
  doc.addSection({ children: compBEDetails });

  // Code: captures en images
  const codeFiles = [
    { file: 'composer.json', title: 'Configuration Composer' },
    { file: path.join('config', 'routes.yaml'), title: 'Routes Symfony' },
    { file: path.join('config', 'packages', 'security.yaml'), title: 'Sécurité (firewalls, ACL)' },
    { file: path.join('templates', 'base.html.twig'), title: 'Template de base (Twig)' },
    { file: path.join('src', 'Controller', 'SecurityController.php'), title: 'Contrôleur de sécurité' },
    { file: path.join('src', 'Controller', 'HomeController.php'), title: 'Contrôleur accueil & santé' },
    { file: path.join('src', 'Controller', 'FamilyController.php'), title: 'Contrôleur famille' },
    { file: path.join('src', 'Controller', 'ProfileController.php'), title: 'Contrôleur profil' },
    { file: path.join('src', 'Service', 'VisitCounter.php'), title: 'Service VisitCounter (Redis + fallback)' },
    { file: path.join('src', 'EventSubscriber', 'AntiBruteForceSubscriber.php'), title: 'Subscriber anti brute‑force (rate limiting)' },
    { file: path.join('src', 'Entity', 'User.php'), title: 'Entité User' },
    { file: path.join('src', 'Entity', 'Family.php'), title: 'Entité Family' },
    { file: path.join('src', 'Entity', 'Event.php'), title: 'Entité Event' },
    { file: path.join('src', 'Entity', 'Message.php'), title: 'Entité Message' },
    { file: path.join('src', 'Entity', 'Invitation.php'), title: 'Entité Invitation' },
    { file: path.join('src', 'Entity', 'ContactMessage.php'), title: 'Entité ContactMessage' },
    { file: path.join('src', 'Repository', 'PhotoRepository.php'), title: 'Repository Photo (requêtes SQL via QB)' },
    { file: path.join('src', 'Repository', 'UserRepository.php'), title: 'Repository User' },
    { file: path.join('src', 'Form', 'RegistrationFormType.php'), title: 'FormType Inscription (validation)' },
    { file: 'docker-compose.yml', title: 'Docker Compose' },
    { file: path.join('docker', 'Dockerfile'), title: 'Dockerfile de l’application' },
  ];

  /* Images de code désactivées — bloc commenté et remplacé par une section narrative
  const browser = await puppeteer.launch({ headless: 'new' });
  try {
    for (const item of codeFiles) {
      const abs = path.join(ROOT, item.file);
      if (!fs.existsSync(abs)) continue;
      const sectionChildren = [h(`Code — ${item.title}`, HeadingLevel.HEADING_1)];
      const images = await generateCodeImages(browser, item.file, item.title, 50);
      for (const img of images) {
        const imgBuf = fs.readFileSync(img.path);
        let width = 700, height = 400;
        try {
          const dim = sizeOf.imageSize(imgBuf);
          if (dim && dim.width && dim.height) {
            const maxW = 700;
            width = maxW;
            height = Math.round(dim.height * (maxW / dim.width));
          }
        } catch {}
        const image = new ImageRun({ data: imgBuf, transformation: { width, height } });
        sectionChildren.push(new Paragraph({ children: [image] }));
        sectionChildren.push(new Paragraph({ children: [new TextRun({ text: img.caption, italics: true })] }));
      }
      doc.addSection({ children: sectionChildren });
    }
  } finally {
    await browser.close();
  }
  */

  // Développement pas à pas (ajouté)
  const steps = [
    h('Développement Pas à Pas', HeadingLevel.HEADING_1),
    p("Étape 1 — Cadrage initial: j’ai posé les objectifs, le périmètre MVP et les contraintes (sécurité, RGPD, performance raisonnable). J’ai choisi Symfony 6.4 pour sa robustesse et la richesse de son écosystème, ainsi que Twig pour un rendu SSR accessible et performant."),
    p("Étape 2 — Environnement reproductible: j’ai écrit `docker-compose.yml` et un `Dockerfile` pour encapsuler l’app et la base de données. L’usage de Docker standardise le développement et simplifie les tests de déploiement."),
    p("Étape 3 — Sécurité de base: configuration de `security.yaml` avec authentification par formulaire (CSRF activé), firewall principal et règles d’accès par rôles. Mise en place d’un contrôle minimal des routes publiques et privées."),
    p("Étape 4 — Modèle utilisateur: création de l’entité `User` (email unique, rôles, champs profil), choix d’un hash de mot de passe géré par `PasswordHasher` et d’un stockage immuable des timestamps."),
    p("Étape 5 — Domaine famille: entité `Family` avec propriétaire et membres, logique de sélection de la famille courante, et premiers écrans sous `base.html.twig` avec navigation responsive."),
    p("Étape 6 — Inscription/Connexion: `SecurityController` et `RegistrationFormType` pour un flux complet, validations (longueur/complexité de mot de passe), feedback utilisateur via flash, et gestion des erreurs sans fuite d’information."),
    p("Étape 7 — Invitations: entité `Invitation` et token aléatoire (ByteString). Envoi d’un email d’invitation (transport `file://` en dev) et route d’acceptation pour rattacher l’utilisateur à la famille."),
    p("Étape 8 — Messages: entité `Message`, création et suppression encadrées (CSRF + autorisations propriétaire/admin). Structuration des vues pour rendre la discussion claire et contextualisée à la famille."),
    p("Étape 9 — Photos: gestion d’upload (MIME), nommage aléatoire, stockage sous `public/uploads/photos`. Autorisations de suppression cohérentes (auteur, propriétaire, admin) et retour utilisateur par flash messages."),
    p("Étape 10 — Calendrier: entité `Event` avec récurrence annuelle. Contrôleurs et templates pour la consultation et la création; formatage lisible des dates et gestion des fuseaux via `DateTimeImmutable`."),
    p("Étape 11 — Profil & suppression: mise à jour des informations personnelles et suppression encadrée du compte (transferts/suppressions cohérentes pour éviter les orphelins, nettoyage de la session)."),
    p("Étape 12 — NoSQL & métriques: `VisitCounter` s’appuie sur Redis, avec repli fichier pour tolérance aux pannes. Les clés sont nommées et TTLisées si nécessaire pour éviter la croissance indéfinie."),
    p("Étape 13 — Anti brute‑force: subscriber dédié, fenêtre de 5/min par IP sur les routes sensibles et délai fixe. Repli par session quand Redis est absent pour conserver une protection minimale."),
    p("Étape 14 — Légal/RGPD: rédaction des pages légales (CGU, confidentialité) et relecture des traitements de données personnelles pour cohérence avec la réglementation."),
    p("Étape 15 — Déploiement: documentation d’un déploiement reproductible via Docker Compose, `APP_ENV=prod`, reverse proxy TLS, `MAILER_DSN` configuré, endpoint `/health` pour supervision. Procédures d’update/rollback alignées sur les migrations Doctrine."),
  ];
  doc.addSection({ children: steps });

  // Développement Pas à Pas — Version détaillée (15 étapes ~1 page chacune)
  const deepSteps = [];
  deepSteps.push(h('Développement Pas à Pas — Version détaillée', HeadingLevel.HEADING_1));

  // Étape 1 — Cadrage initial (détaillé)
  deepSteps.push(h('Étape 1 — Cadrage initial', HeadingLevel.HEADING_2));
  deepSteps.push(p("Avant toute implémentation, j’ai formalisé le pourquoi du projet: aider une tribu familiale à s’organiser via un espace privé qui centralise calendriers, échanges et photos. J’ai précisé les personas (parent organisateur, membres invités), dressé la liste des scénarios critiques (s’inscrire, créer une famille, inviter, publier un message, ajouter une photo, planifier un évènement) et défini un périmètre MVP réaliste pour itérer vite sans sacrifier la sécurité ni la maintenabilité."));
  deepSteps.push(p("J’ai opté pour PHP 8.1 et Symfony 6.4 car l’écosystème est solide, l’outillage intégré (SecurityBundle, Form, Validator, Twig) couvre la plupart des besoins, et la communauté apporte des bonnes pratiques éprouvées. Le rendu côté serveur (Twig SSR) a été privilégié pour limiter la complexité (pas de SPA ni d’API publique à exposer) et réduire la surface d’attaque. Doctrine ORM a été choisi pour bénéficier d’un mapping expressif, des migrations et d’une couche d’abstraction qui clarifie le code métier."));
  deepSteps.push(p("J’ai fixé des objectifs non fonctionnels: accessibilité de base, performance acceptable sans optimiser prématurément, sécurité by design (CSRF systématique, validation serveur), lisibilité du code (contrôleurs fins, services dédiés au transverse, entités explicites), et documentation continue (README, scripts de lancement). Cette phase a aussi établi le principe de tolérance à la dégradation (ex.: Redis optionnel, repli fichier)."));
  deepSteps.push(p("Côté organisation, j’ai planifié un déroulé en 15 étapes, chacune apportant une valeur complète (vertical slice): modélisation, contrôleur, formulaire, template, sécurité, puis mise au propre. Ce guidage m’a permis d’enchaîner sans revenir en arrière en permanence, tout en gardant une vision d’ensemble cohérente. Le succès de cette phase conditionne la fluidité du développement ultérieur."));
  deepSteps.push(p("Enfin, j’ai posé des critères d’acceptation pour chaque étape (par exemple: un utilisateur peut s’inscrire, se connecter et voir sa famille; un propriétaire peut inviter; une suppression ne casse pas l’intégrité des données) afin d’éviter la dérive des objectifs. Cela s’est traduit par des tests manuels systématiques et des vérifications ciblées après chaque flux livré."));

  // Étape 2 — Environnement reproductible (détaillé)
  deepSteps.push(h('Étape 2 — Environnement reproductible', HeadingLevel.HEADING_2));
  deepSteps.push(p("J’ai créé un `docker-compose.yml` avec au minimum deux services: `app` (PHP‑Apache) et `db` (PostgreSQL 16), et la possibilité d’ajouter `redis`. Le service `app` monte le code, expose le port 8080, définit `public/` comme DocumentRoot et injecte des variables d’environnement. Au démarrage, l’entrée `docker-entrypoint.sh` installe les dépendances Composer si besoin, attend la base, exécute les migrations, puis lance Apache."));
  deepSteps.push(p("Cette encapsulation garantit que `composer.lock` est respecté (mêmes versions de dépendances), que la base est au bon schéma (migrations Doctrine), et que l’application est immédiatement accessible sur `http://localhost:8080`. Pour les développeurs qui préfèrent travailler sans Docker, j’ai documenté la séquence locale: `composer install`, configuration `DATABASE_URL` dans `.env`, `php bin/console doctrine:migrations:migrate -n`, puis `php -S localhost:8000 -t public`."));
  deepSteps.push(p("J’ai également spécifié un `.env.prod` type pour la production (APP_ENV=prod, APP_DEBUG=0, APP_SECRET, DATABASE_URL, MAILER_DSN, REDIS_URL). L’idée est de séparer clairement la configuration des environnements et d’éviter toute fuite de secrets dans le dépôt. Le docker-compose de prod peut être lancé avec `--env-file .env.prod` afin d’injecter correctement ces valeurs."));
  deepSteps.push(p("En résumé, cette étape réduit les frictions: un `docker compose up --build` suffit pour lancer le projet, et les commandes d’administration (migrations, création d’admin) sont encapsulées via `docker compose exec app php bin/console ...`. Cette approche aligne développement et déploiement et me permet d’itérer rapidement."));

  // Étape 3 — Sécurité de base (détaillé)
  deepSteps.push(h('Étape 3 — Sécurité de base', HeadingLevel.HEADING_2));
  deepSteps.push(p("Dans `config/packages/security.yaml`, le firewall `main` utilise `form_login` avec CSRF, et `logout` redirige vers l’accueil. Le provider charge les utilisateurs via `UserRepository` par l’email. Les règles `access_control` rendent publiques les routes de connexion, d’inscription et de contact, et exigent `ROLE_USER` pour famille, calendrier et profil. L’admin est restreint à `ROLE_ADMIN`."));
  deepSteps.push(p("Côté protection des formulaires, chaque formulaire important intègre un jeton CSRF synchronisé. Twig échappe les variables par défaut, ce qui limite les injections XSS. Les données sont validées sur le serveur avec des contraintes (Assert) afin de garantir l’intégrité indépendamment du front. Pour les mots de passe, le PasswordHasher applique un algorithme configurable et évolutif, évitant d’exposer des détails sensibles."));
  deepSteps.push(p("L’autre point clé est la gestion des sessions: en production, cookies `Secure` et `HttpOnly`, SameSite strict quand cela ne gêne pas l’UX, rotation de session lors d’événements sensibles. L’objectif est de réduire le risque de vol de session et de Cross‑Site Request Forgery. Cette base de sécurité sert de garde‑fou à toutes les fonctionnalités suivantes."));

  // Étape 4 — Utilisateurs (détaillé)
  deepSteps.push(h('Étape 4 — Modèle utilisateur', HeadingLevel.HEADING_2));
  deepSteps.push(p("L’entité `User` comprend l’`email` (unique), le `password` haché, les `roles` (ajout implicite de `ROLE_USER`) et des attributs de profil (noms, âge, code postal, ville/région, téléphone). J’ai ajouté des champs pour une double authentification (code et expiration) permettant d’étendre la sécurité ultérieurement sans refactor. Chaque champ critique s’accompagne d’une contrainte (ex.: `@Email`, `@NotBlank`, `@GreaterThanOrEqual`)."));
  deepSteps.push(p("Au niveau du formulaire d’inscription, j’impose une politique de mot de passe (longueur minimale, complexité) avec des messages explicites. Le contrôleur vérifie l’unicité de l’email et hache le mot de passe via le hasher de Symfony. Des messages flash guident l’utilisateur après la création et en cas d’erreur. Le flux reste constant: on redirige vers la connexion une fois le compte créé."));
  deepSteps.push(p("J’ai également prévu la normalisation des données en entrée (ex.: lowercase sur l’email, trim). Ces petits détails améliorent la qualité des données et réduisent les surprises lors des requêtes. À ce stade, un utilisateur peut s’inscrire, se connecter et accéder à son espace privé, ce qui valide la colonne vertébrale de l’authentification."));

  // Étape 5 — Familles (détaillé)
  deepSteps.push(h('Étape 5 — Domaine famille', HeadingLevel.HEADING_2));
  deepSteps.push(p("`Family` représente l’agrégat central côté métier: un propriétaire (`owner`) peut inviter des membres et gérer les éléments rattachés (messages, évènements, photos). La relation ManyToMany avec `User` permet l’appartenance à plusieurs familles. J’ai mis en place une logique de sélection (via `fid`) pour faciliter la navigation entre familles quand un utilisateur en a plusieurs."));
  deepSteps.push(p("Dans le contrôleur, j’ai cadré les autorisations: un membre peut consulter et publier, mais certaines actions (suppression, changement de propriétaire) sont réservées au propriétaire ou à l’admin. Les jetons CSRF sont exigés pour toute action destructive. Côté vue, `base.html.twig` expose les liens adaptés à l’état (connecté/non connecté) et aux rôles, tout en restant responsive."));
  deepSteps.push(p("Cette étape a servi de socle à plusieurs modules (invitations, messages, photos, évènements) et m’a permis de valider l’ergonomie de la navigation et la gestion de la famille courante. Elle a aussi établi la convention des messages flash pour informer l’utilisateur des résultats d’actions."));

  // Étape 6 — Inscription/Connexion (détaillé)
  deepSteps.push(h('Étape 6 — Inscription & Connexion', HeadingLevel.HEADING_2));
  deepSteps.push(p("`SecurityController` pilote l’authentification: la méthode `login` passe l’éventuelle erreur et le dernier identifiant saisi à la vue; `register` crée l’utilisateur via `RegistrationFormType`, vérifie l’unicité de l’email, hache le mot de passe et persiste. Après succès, un flash message confirme la création et l’utilisateur est redirigé vers la connexion. Tout est réalisé en POST avec CSRF."));
  deepSteps.push(p("J’ai testé les chemins d’erreur: mot de passe trop court, email invalide, email déjà présent. Dans tous les cas, la remontée d’information reste utile sans divulguer de détails exploitables par un attaquant. La déconnexion invalide la session et renvoie à l’accueil, ce qui simplifie la reprise de navigation."));

  // Étape 7 — Invitations (détaillé)
  deepSteps.push(h('Étape 7 — Invitations', HeadingLevel.HEADING_2));
  deepSteps.push(p("Pour inviter, j’ai ajouté `Invitation` (email, token, status, timestamps). Le token est généré via `ByteString::fromRandom(32)` pour être imprévisible. L’email contient un lien vers une route d’acceptation. En dev, `MAILER_DSN` pointe vers `file://` pour écrire les emails sur disque. En production, un SMTP authentifié prend le relais. L’UX guide l’émetteur sur l’état de l’invitation (envoyée / erreur d’envoi / à renvoyer manuellement)."));
  deepSteps.push(p("La sécurité du flux repose sur la validation du token, la vérification de la famille cible et l’authentification de l’invitée lors de l’acceptation. Les statuts passent de `pending` à `accepted` avec `acceptedAt` horodaté. Ce composant illustre la collaboration entre le domaine (Invitation), l’infrastructure (Mailer) et l’interface (Form + Controller)."));

  // Étape 8 — Messages (détaillé)
  deepSteps.push(h('Étape 8 — Messages', HeadingLevel.HEADING_2));
  deepSteps.push(p("`Message` relie un contenu à un auteur et à une famille. La création est ouverte aux membres; la suppression est limitée au propriétaire de la famille et à l’admin, avec CSRF obligatoire. Les contrôleurs restent fins: ils orchestrent la validation, la sécurité, puis délèguent à Doctrine la persistance. L’affichage trie par date de création décroissante et reste confiné à la famille courante."));
  deepSteps.push(p("Ce module a renforcé la cohérence des patterns: formulaire + validation, autorisations au plus proche de l’action, messages flash explicites, et absence de logique métier superflue dans les vues. Cette rigueur simplifie la maintenance et réduit les effets de bord."));

  // Étape 9 — Photos (détaillé)
  deepSteps.push(h('Étape 9 — Photos', HeadingLevel.HEADING_2));
  deepSteps.push(p("L’upload exige de la prudence: contrôle de type MIME, nommage aléatoire pour éviter la prédictibilité, stockage dans un répertoire dédié sous `public/`. Le chemin public est persisté dans `Photo` avec la légende, l’auteur et la famille. La suppression vérifie que l’appelant est soit l’auteur, soit le propriétaire de la famille, soit un administrateur, et impose un jeton CSRF valide."));
  deepSteps.push(p("Pour rester focalisé sur la sécurité et la simplicité, je n’ai pas introduit de transformation d’images à ce stade. Les améliorations envisagées incluent la génération de vignettes et la limitation de taille d’upload côté serveur. Le but du MVP est de permettre un partage rapide et sûr sans complexifier l’infrastructure."));

  // Étape 10 — Calendrier (détaillé)
  deepSteps.push(h('Étape 10 — Calendrier', HeadingLevel.HEADING_2));
  deepSteps.push(p("`Event` permet de planifier des moments importants (réunions, anniversaires). Le champ `recurrence` accepte `yearly` pour couvrir les anniversaires. J’ai utilisé `DateTimeImmutable` pour éviter toute mutation accidentelle. Les vues affichent les évènements de la famille courante, et des FormType dédiés assurent la validation des dates et des champs obligatoires."));
  deepSteps.push(p("Le design reste simple et extensible: rien n’empêche d’ajouter la coloration par type d’évènement, des rappels par email/SMS (via `SmsSender` ou Mailer) ou un export iCal. L’important est d’avoir une base saine, testée manuellement et cohérente avec l’ergonomie générale."));

  // Étape 11 — Profil & suppression (détaillé)
  deepSteps.push(h('Étape 11 — Profil & suppression de compte', HeadingLevel.HEADING_2));
  deepSteps.push(p("Le formulaire de profil met à jour les champs personnels avec validation. Pour la suppression du compte, j’ai orchestré un flux en plusieurs passes pour respecter les contraintes de clé étrangère: supprimer les familles dont l’utilisateur est propriétaire (ou transférer), supprimer ses messages, annuler la référence `createdBy` dans `Event`, détacher l’utilisateur des familles (ManyToMany), supprimer l’utilisateur, puis invalider le token de session et la session elle‑même."));
  deepSteps.push(p("Ce fonctionnement évite les incohérences (orphelins, erreurs de contrainte) et garantit une expérience propre: l’utilisateur est effectivement déconnecté et redirigé. Un CSRF protège l’action, et un garde‑fou empêche la suppression d’un compte admin via cette route pour éviter un scénario de blocage."));

  // Étape 12 — NoSQL & métriques (détaillé)
  deepSteps.push(h('Étape 12 — NoSQL & métriques', HeadingLevel.HEADING_2));
  deepSteps.push(p("`VisitCounter` démontre l’intérêt de Redis pour des compteurs: incrément atomique, latence faible, simplicité d’usage. En cas d’indisponibilité de Redis, le repli fichier permet de maintenir une fonctionnalité non critique sans stopper l’application. C’est une illustration de résilience pragmatique: le cœur de l’app (famille, messages, évènements) reste fonctionnel même sans Redis."));
  deepSteps.push(p("J’ai utilisé des conventions de nommage pour les clés (`visits:homepage`) afin de faciliter l’observabilité. Cette base peut évoluer vers des tableaux de bord, ou une intégration Prometheus/Grafana si le besoin s’en fait sentir. L’important est d’avoir un point d’extension clair sans dette technique cachée."));

  // Étape 13 — Anti brute‑force (détaillé)
  deepSteps.push(h('Étape 13 — Anti brute‑force', HeadingLevel.HEADING_2));
  deepSteps.push(p("Le subscriber se branche tôt dans le cycle de requête (`KernelEvents::REQUEST`) pour intercepter les POST des routes sensibles. Je combine un délai fixe (2s) pour ralentir les attaques avec un plafond de 5 requêtes/minute par IP. Redis gère la fenêtre temporelle via TTL; la session sert de repli si Redis est absent. Au‑delà du plafond, une réponse 429 est renvoyée avec un `Retry-After`."));
  deepSteps.push(p("Ce mécanisme est volontairement minimaliste et compréhensible: peu d’états, des clefs de comptage explicites, et une dégradation propre. Il complète utilement les protections par CSRF et par hachage de mot de passe, et rend l’énumération/force brute nettement moins rentable."));

  // Étape 14 — Légal & RGPD (détaillé)
  deepSteps.push(h('Étape 14 — Légal & RGPD', HeadingLevel.HEADING_2));
  deepSteps.push(p("J’ai ajouté des pages légales (règlement, confidentialité) et revu les traitements de données: emails (inscription, invitations), contenus (messages, photos), métadonnées (timestamps). Le principe appliqué est la minimisation: ne collecter que ce qui est utile, respecter le droit à l’effacement via la suppression de compte, et prévoir un export/accès sur demande. Ces éléments posent une base de conformité pour une future montée en charge."));
  deepSteps.push(p("J’ai aussi pensé à l’avenir: centraliser la configuration des durées de rétention, consigner les actions sensibles (journal d’audit), et rendre plus explicite le consentement aux notifications (email/SMS). Même si tout n’est pas nécessaire au MVP, la structure actuelle permet ces ajouts sans remise en cause."));

  // Étape 15 — Déploiement (détaillé)
  deepSteps.push(h('Étape 15 — Déploiement', HeadingLevel.HEADING_2));
  deepSteps.push(p("La procédure cible un serveur avec Docker et un reverse proxy TLS (Nginx/Caddy). Je prépare un fichier `.env.prod` avec les variables nécessaires (APP_ENV=prod, APP_DEBUG=0, APP_SECRET, DATABASE_URL vers Postgres, MAILER_DSN, REDIS_URL), puis je lance `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build`. Après le démarrage, j’exécute les migrations Doctrine, je crée un compte admin via la commande CLI dédiée, et je vérifie `/health`."));
  deepSteps.push(p("Les mises à jour suivent un chemin maîtrisé: build de la nouvelle version, migrations vers l’avant, redémarrage contrôlé. En cas de besoin, un rollback s’effectue via des migrations inverses et une restauration des sauvegardes de base/uploads. Cette simplicité est volontaire: moins de magie, plus de prévisibilité et une base prête pour une CI/CD ultérieure."));

  doc.addSection({ children: deepSteps });

  // Conclusion
  doc.addSection({
    children: [
      h('Conclusion & Suites', HeadingLevel.HEADING_1),
      p("TribuConnect propose une base solide pour l’organisation familiale : une architecture Symfony claire, un modèle de données cohérent et des fonctionnalités essentielles (authentification, familles, calendrier, photos, messages). Les pistes d’amélioration incluent l’affinage des rôles, la recherche, les notifications en temps réel et des tests automatisés."),
    ],
  });

  const buffer = await Packer.toBuffer(doc);
  await fsp.writeFile(OUTPUT_DOCX, buffer);
  console.log('Dossier généré:', OUTPUT_DOCX);
}

main().catch((err) => {
  console.error('Erreur lors de la génération du dossier:', err);
  process.exit(1);
});

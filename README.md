# MaTontine — Backend API

Backend Laravel pour **MaTontine**, une application de gestion de tontines (cotisations hebdomadaires, sanctions, distributions et compléments) destinée à un usage administratif via une API REST consommée par une application mobile Flutter.

## Sommaire

- [Aperçu](#aperçu)
- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Modèle métier](#modèle-métier)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Lancer le projet](#lancer-le-projet)
- [Structure du projet](#structure-du-projet)
- [Routes API](#routes-api)
- [Tests](#tests)
- [Licence](#licence)

## Aperçu

MaTontine permet à un(e) administrateur(trice) de gérer une ou plusieurs tontines : inscription des membres, encaissement des cotisations hebdomadaires, application de sanctions en cas de retard/abandon, distribution des fonds, gestion de compléments (ex. attribution de motos), et suivi via un tableau de bord avec statistiques.

L'API est entièrement stateless et sécurisée par token (Laravel Sanctum), pensée pour être consommée par une application mobile (Flutter).

## Fonctionnalités

- **Authentification admin** — connexion par email/mot de passe, déconnexion, gestion du profil, réinitialisation de mot de passe par OTP envoyé par email.
- **Gestion des tontines** — plusieurs tontines regroupées par catégorie (petits, moyens, grands, premium montants), chacune avec son propre montant de cotisation.
- **Gestion des membres** — inscription, activation/désactivation, abandon/réactivation, historique, export PDF, calcul automatique du dû/crédit envers la tontine.
- **Cotisations hebdomadaires** — encaissement, annulation, consultation par semaine ou par membre, calcul automatique du calendrier (semaine basée sur le premier samedi de janvier).
- **Sanctions** — création manuelle, annulation, marquage comme payées, **et génération automatique hebdomadaire** (300 CFA) pour tout membre actif n'ayant pas cotisé le samedi, via une tâche planifiée.
- **Distributions** — répartition des fonds entre membres, suivi des soldes, export PDF.
- **Compléments (ex. motos)** — demandes, approbation/refus, attribution, export PDF.
- **Bénéfices & Dépenses** — calcul des bénéfices, statistiques, suivi des dépenses (avec upload d'image justificative).
- **Tableau de bord** — statistiques globales sur les cotisations, sanctions et membres.
- **Stockage de fichiers** — service de diffusion de fichiers (justificatifs, exports) via une route dédiée.

## Stack technique

- **PHP** 8.3+
- **Laravel** 13
- **Laravel Sanctum** — authentification par token pour l'API
- **barryvdh/laravel-dompdf** — génération de PDF (fiches membres, distributions, compléments)
- **MySQL** (via Eloquent ORM)
- **Vite** + **Tailwind CSS** — assets front (pages d'e-mail/erreurs, le cas échéant)

## Modèle métier

Le cœur de la logique métier est centralisé dans `App\Services\TontineCalcService`, qui définit les règles de la tontine :

| Constante | Valeur | Description |
|---|---|---|
| `MONTANT_HEBDO` | 10 000 CFA | Montant de cotisation hebdomadaire par défaut |
| `TOTAL_SEMAINES` | 52 | Nombre de semaines d'un cycle de tontine |
| `SEUIL_ABANDON` | 50 000 CFA | En dessous de ce seuil cotisé, un membre qui abandonne perd son argent |
| `SEUIL_ELIGIBILITE` | 12 semaines | Nombre minimum de semaines cotisées pour être éligible à un complément (ex. moto) |

Le calendrier des cotisations est calculé automatiquement à partir du premier samedi de janvier de chaque année. Chaque membre dispose d'accesseurs calculés (`du_vers_tontine`, `credit_vers_membre`, `solde_net`, `est_eligible_moto`, etc.) qui reflètent en temps réel sa situation par rapport à la tontine, sans saisie manuelle.

### Sanction automatique des retards de cotisation

Une commande artisan dédiée, `tontine:sanctionner-retard`, applique automatiquement une sanction de **300 CFA** (motif `retard_cotisation`) à chaque membre actif n'ayant pas cotisé pour la semaine en cours.

- **Planification** — exécutée automatiquement chaque **dimanche à 08h00** (fuseau `Africa/Porto-Novo`) via le scheduler Laravel défini dans `bootstrap/app.php`, avec protection anti-chevauchement (`withoutOverlapping`) et exécution en arrière-plan. Les logs sont écrits dans `storage/logs/sanctions-auto.log`.
- **Anti-doublon** — avant de créer une sanction, la commande vérifie qu'aucune sanction automatique `retard_cotisation` n'existe déjà pour ce membre sur la même semaine.
- **Traçabilité** — chaque sanction générée automatiquement est marquée `auto_genere = true` (visible dans la réponse de l'API), pour la distinguer des sanctions créées manuellement par un administrateur (retard réunion, non-respect des statuts, absence non justifiée, etc.).
- **Options manuelles** — la commande peut aussi être lancée à la main :

```bash
# Aperçu sans création (dry-run)
php artisan tontine:sanctionner-retard --dry-run

# Cibler une semaine précise
php artisan tontine:sanctionner-retard --semaine=15
```

> Pour que la planification fonctionne en production, le cron Laravel doit être actif sur le serveur : `* * * * * php artisan schedule:run >> /dev/null 2>&1`.

### Entités principales

- **Tontine** — regroupe des membres autour d'un montant de cotisation et d'une catégorie.
- **Membre** — participant à une tontine (numéro de registre, statut actif/abandonné, etc.).
- **Cotisation** — paiement hebdomadaire d'un membre.
- **Sanction** — pénalité appliquée à un membre.
- **Distribution** — versement de fonds à un ou plusieurs membres.
- **Complément** — avantage complémentaire (ex. attribution de moto) soumis à approbation.
- **Bénéfice / Dépense** — suivi financier global de la tontine.
- **Admin** — utilisateur administrateur authentifié via Sanctum.

## Prérequis

- PHP >= 8.3 avec les extensions usuelles de Laravel
- Composer
- Node.js + npm
- MySQL
- Une extension PHP pour DomPDF (gd ou mbstring selon config)

## Installation

```bash
git clone https://github.com/dossouyovo123/tontine-backend.git
cd tontine-backend

composer install
npm install
```

## Configuration

1. Copier le fichier d'environnement et générer la clé d'application :

```bash
cp .env.example .env
php artisan key:generate
```

2. Renseigner les variables dans `.env`, notamment :

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=tontine
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
```

> La configuration mail est utilisée pour l'envoi des codes OTP lors de la réinitialisation de mot de passe.

3. Créer la base de données puis lancer les migrations :

```bash
php artisan migrate
```

4. (Optionnel) Créer un compte admin via `php artisan tinker` :

```bash
php artisan tinker
>>> App\Models\Admin::create(['nom' => 'Admin', 'email' => 'admin@example.com', 'password' => Hash::make('password')]);
```

## Lancer le projet

En développement, un script unique démarre le serveur, la queue, les logs et Vite en parallèle :

```bash
composer run dev
```

Ou séparément :

```bash
php artisan serve
```

L'API est alors accessible sur `http://localhost:8000/api/v1`.

## Structure du projet

```
app/
├── Console/                 # Commandes artisan personnalisées
├── Http/Controllers/Api/    # Contrôleurs de l'API (Auth, Membre, Cotisation, Sanction, ...)
├── Mail/                    # Mailables (OtpMail)
├── Models/                  # Modèles Eloquent (Membre, Tontine, Cotisation, Sanction, ...)
├── Providers/
└── Services/                # Logique métier centralisée (TontineCalcService, CotisationService)

database/
├── factories/
├── migrations/
└── seeders/

routes/
├── api.php                  # Toutes les routes de l'API (préfixe /api/v1)
├── console.php
└── web.php
```

## Routes API

Toutes les routes sont préfixées par `/api/v1`.

### Publiques

| Méthode | Endpoint | Description |
|---|---|---|
| POST | `/login` | Connexion admin |
| POST | `/forgot-password` | Demande de réinitialisation (envoi OTP) |
| POST | `/verify-otp` | Vérification du code OTP |
| POST | `/reset-password` | Réinitialisation du mot de passe |
| GET | `/storage/{path}` | Diffusion de fichiers stockés |

### Protégées (`auth:sanctum`)

**Auth & profil**
- `POST /logout`, `GET /me`, `PUT /me`

**Dashboard**
- `GET /dashboard`, `GET /stats/cotisations`, `GET /stats/sanctions`, `GET /stats/membres`

**Tontines**
- `GET /tontines`, `GET /tontines/{tontine}`

**Membres**
- `GET|POST /membres`, `GET|PUT|DELETE /membres/{membre}`
- `POST /membres/{membre}/abandonner`, `POST /membres/{membre}/reactiver`
- `GET /membres/{membre}/historique`, `GET /membres/{membre}/pdf`, `GET /membres/{membre}/cotisations`

**Cotisations**
- `GET /cotisations`, `POST /cotisations/encaisser`
- `GET /cotisations/semaine/{semaine?}`, `PUT /cotisations/{cotisation}/annuler`

**Sanctions**
- `GET|POST /sanctions`, `GET|DELETE /sanctions/{sanction}`
- `PUT /sanctions/{sanction}/annuler`, `POST /sanctions/{sanction}/marquer-paye`

**Distributions**
- `GET|POST /distributions`, `GET|PUT|DELETE /distributions/{distribution}`
- `GET /distributions/soldes`, `GET /distributions/{distribution}/pdf`

**Compléments**
- `GET|POST /complements`, `GET|DELETE /complements/{complement}`
- `POST /complements/{complement}/approuver`, `POST /complements/{complement}/refuser`
- `POST /complements/{complement}/attribuer`, `GET /complements/{complement}/pdf`

**Bénéfices**
- `GET /benefices`, `POST /benefices/calculer`, `GET /benefices/stats`, `DELETE /benefices/{benefice}`

**Dépenses**
- `GET|POST /depenses`, `GET|DELETE /depenses/{depense}`, `POST /depenses/{depense}/update`

## Tests

```bash
composer run test
```

## Licence

Ce projet est un projet privé développé par [DOSSOU-YOVO José Mario](https://github.com/dossouyovo123). Sauf mention contraire, tous droits réservés.
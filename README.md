# SamaBoutik – BACKEND LARAVEL

SamaBoutik est une application mobile pour les petits commerçants du Sénégal. Ce dépôt contient son **API**, écrite avec Laravel : c’est elle qui gère les comptes, les produits, le stock, les ventes et les paiements (espèces, mobile money avec PayDunya, Bitcoin Lightning avec LNbits).

- API en ligne : https://samaboutik.alwaysdata.net/api
- Pour vérifier qu’elle répond : https://samaboutik.alwaysdata.net/api/ping
- Application mobile (Flutter) : https://github.com/Faith-time/samaboutik-app

## Ce que fait l’API

- Création de compte et connexion avec un numéro de téléphone et un mot de passe.
- Gestion des produits, avec photo, prix de vente et prix d’achat.
- Suivi du stock en unités et en paquets, avec un seuil d’alerte, et enregistrement des achats de marchandises.
- Création des ventes avec plusieurs produits, historique et annulation.
- Paiement en espèces, par mobile money (Wave, Orange Money, Free Money) ou en Bitcoin Lightning.
- Tableau de bord, classement des produits (par jour, semaine, mois ou année) et analyse avec des conseils.
- Retraits en FCFA, mais simulés : aucun argent réel ne bouge.

## Technologies utilisées

PHP 8.3 ou plus (8.4 sur le serveur), Laravel 13, Laravel Sanctum pour la connexion, spatie/laravel-permission pour les rôles, MySQL, LNbits pour le Bitcoin Lightning, PayDunya pour le mobile money, CoinGecko pour le cours du Bitcoin. Hébergement sur alwaysdata (offre gratuite).

## Les routes de l’API

Toutes les routes commencent par `/api`. Les requêtes protégées demandent l’en-tête `Authorization: Bearer <jeton>`, que l’on reçoit en se connectant.

- **Sans connexion** : `GET /ping`, `POST /inscription`, `POST /connexion`, et les deux webhooks (`POST /webhooks/lightning` et `POST /webhooks/paydunya`).
- **Compte** : `POST /deconnexion`, `GET /moi`.
- **Produits** : `GET`, `POST`, `PUT` et `DELETE` sur `/produits`, plus `POST` et `DELETE` sur `/produits/{id}/image` pour la photo.
- **Stock** : `GET /stock`, `POST /produits/{id}/stock/correction`, `GET /classement`, et `GET` / `POST` sur `/approvisionnements`.
- **Ventes** : `GET` / `POST` sur `/ventes`, `GET /ventes/{id}`, `POST /ventes/{id}/annuler`.
- **Paiements** : `POST /ventes/{id}/paiements/especes`, `.../lightning` et `.../mobile`, puis `GET /paiements/{id}` pour suivre le statut.
- **Autres** : `GET /tableau-de-bord`, `GET /taux-change`, `GET /rapport-ia`, `GET /rapports-ia`, `GET /retraits`, `POST /retraits`.

La liste complète (39 routes) s’obtient avec `php artisan route:list`.

## Comment marchent les paiements

**Bitcoin Lightning.** Quand le commerçant choisit Bitcoin, l’API convertit le montant en FCFA en euros (avec la parité fixe 655,957), puis en satoshis grâce au cours du Bitcoin récupéré chez CoinGecko. Elle ajoute une petite marge de sécurité de 0,5 %, puis demande à LNbits de créer une facture valable 5 minutes. L’application affiche cette facture sous forme de QR code. Quand le client paie, LNbits prévient l’API par un webhook et la vente passe à « payée ». Si les 5 minutes sont dépassées, on recrée une facture au cours du moment.

Le webhook est protégé par un secret que l’on invente soi-même (par exemple avec `openssl rand -hex 32`) et que l’on met dans `LIGHTNING_WEBHOOK_SECRET`. Attention : `demo.lnbits.com` est un serveur de démonstration, parfait pour un prototype mais pas pour de vrais clients.

**Mobile money.** L’API crée une facture chez PayDunya et garde l’adresse de la page de paiement. L’application ouvre cette page, le client paie, puis PayDunya prévient l’API par son webhook. Pour l’instant on est en **mode test** : aucun vrai paiement n’a lieu.

## Installer le projet chez soi

Il faut PHP 8.3 ou plus, Composer, MySQL et Git (j’utilise Laragon sous Windows).

```bash
git clone https://github.com/Faith-time/samaboutik-api.git
cd samaboutik-api

composer install
cp .env.example .env
php artisan key:generate
```

Créez une base MySQL (par exemple `samaboutik`), renseignez les lignes `DB_` du fichier `.env`, puis :

```bash
php artisan migrate
php artisan storage:link
php artisan serve
```

L’API répond alors sur http://localhost:8000/api/ping. Depuis un émulateur Android, on l’atteint avec `http://10.0.2.2:8000/api`. Pour les tests : `composer test`.

## Le fichier .env

Copiez `.env.example` vers `.env` et ne publiez jamais ce fichier sur GitHub. Les réglages importants :

- `APP_ENV=production`, `APP_DEBUG=false` et `APP_URL=https://samaboutik.alwaysdata.net` en production.
- `APP_TIMEZONE=Africa/Dakar` et `APP_LOCALE=fr`.
- `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` : les informations de la base fournies par alwaysdata.
- `SESSION_DRIVER=database` (il faut alors la table `sessions`) ou `file`, et `QUEUE_CONNECTION=sync`.
- `LIGHTNING_API_URL`, `LIGHTNING_API_KEY` (la clé « Invoice/read key » de LNbits, pas l’Admin key), `LIGHTNING_WEBHOOK_SECRET`, `LIGHTNING_EXPIRATION_MINUTES=5`.
- `SATS_SAFETY_MARGIN_PERCENT=0.5`, `XOF_PER_EUR=655.957`, `RATE_API_URL`, `RATE_CACHE_SECONDS=60`.
- `PAYDUNYA_MASTER_KEY`, `PAYDUNYA_PRIVATE_KEY`, `PAYDUNYA_PUBLIC_KEY`, `PAYDUNYA_TOKEN` et `PAYDUNYA_MODE=test`.

Après chaque changement du `.env` sur le serveur, lancez `php artisan config:cache`.

## Mise en ligne sur alwaysdata

J’ai choisi alwaysdata parce que c’est gratuit, sans carte bancaire, et que le site ne s’endort pas : pour un jury, une API qui met du temps à se réveiller ressemblerait à un bug.

1. **Compte et base.** Créez un compte (offre gratuite), puis une base MySQL dans *Bases de données → MySQL*. Activez aussi l’accès **SSH** dans *Utilisateurs*. Avec phpMyAdmin, importez votre base locale si vous voulez garder des données de démonstration.

2. **Réglage du site.** Dans *Web → Sites*, modifiez votre site : type **PHP**, version 8.3 ou 8.4. Pour le répertoire racine, le champ commence déjà par `/home/votre-compte/` : écrivez seulement `samaboutik-api/public/`. Si vous retapez tout le chemin, il sera doublé et le site ne trouvera rien.

3. **Récupérer le code** en SSH :

```bash
ssh samaboutik@ssh-samaboutik.alwaysdata.net
cd /home/samaboutik
git clone https://github.com/Faith-time/samaboutik-api.git samaboutik-api
cd samaboutik-api
composer install --no-dev --optimize-autoloader
```

4. **Préparer le `.env`** : `cp .env.example .env`, puis `nano .env` pour remplir les valeurs ci-dessus (Ctrl+O pour enregistrer, Ctrl+X pour quitter).

5. **Initialiser Laravel** :

```bash
php artisan key:generate
php artisan migrate:status
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
```

Toutes les migrations doivent afficher « Ran ». Ne lancez pas `db:seed` sur une base déjà importée, vous auriez des doublons. Je n’utilise pas `route:cache` : une de mes routes (`/ping`) est écrite avec une fonction, et Laravel ne sait pas la mettre en cache.

6. **Tester** : ouvrez https://samaboutik.alwaysdata.net/api/ping, vous devez voir `{"message":"SamaBoutik API OK"}`. Vérifiez aussi que le serveur peut joindre l’extérieur, car c’est indispensable pour Lightning, PayDunya et le cours du Bitcoin :

```bash
curl -sI https://demo.lnbits.com | head -1
curl -s "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=eur"
curl -sI https://app.paydunya.com | head -1
```

7. **Photos des produits** : copiez le contenu de `storage/app/public` de votre ordinateur vers le même dossier sur le serveur (par exemple avec FileZilla, en SFTP sur `ssh-samaboutik.alwaysdata.net`, port 22).

8. **Webhooks** à déclarer chez les fournisseurs :
   - LNbits : `https://samaboutik.alwaysdata.net/api/webhooks/lightning`
   - PayDunya : `https://samaboutik.alwaysdata.net/api/webhooks/paydunya`

## Mettre à jour la version en ligne

Après un `git push` depuis mon ordinateur :

```bash
ssh samaboutik@ssh-samaboutik.alwaysdata.net
cd /home/samaboutik/samaboutik-api
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Les téléphones qui ont déjà l’application en profitent tout de suite, sans réinstaller. En revanche, il faut garder les routes déjà utilisées par l’APK publié. Pour lire les erreurs : `tail -n 50 storage/logs/laravel.log`.

## Les problèmes que j’ai rencontrés

- **« Access denied for user » au premier `migrate:status`** : les identifiants MySQL du `.env` étaient faux. Il fallait reprendre ceux créés dans alwaysdata.
- **Erreur 500 sur le site** avec le message « Table sessions doesn’t exist » : j’avais `SESSION_DRIVER=database` mais la base importée n’avait pas cette table. Solution : `php artisan make:session-table` puis `php artisan migrate --force`. On peut aussi passer à `SESSION_DRIVER=file`, puisque l’API utilise des jetons et pas des sessions.
- **« Could not open input file: artisan »** : j’étais dans le mauvais dossier. Il faut faire `cd /home/samaboutik/samaboutik-api` avant les commandes `php artisan`.

Mon conseil pour comprendre une erreur 500 : lire d’abord le message principal dans le journal avec `grep -n -B 3 -A 2 "SQLSTATE" storage/logs/laravel.log | tail -n 20`, plutôt que la fin de la longue liste d’appels.

## Sécurité

- Le `.env` n’est jamais sur GitHub, seulement le `.env.example`.
- Les clés ont été régénérées pour la production (`APP_KEY`, secret du webhook, clés LNbits et PayDunya).
- `APP_DEBUG=false` en production, et tout passe en HTTPS.
- Les mots de passe sont hachés, les jetons de connexion aussi.
- Je n’utilise que la clé « Invoice/read key » de LNbits, jamais l’Admin key.

## Ce qui est en test ou simulé

- Le Bitcoin Lightning fonctionne de bout en bout, mais avec un serveur LNbits de démonstration.
- PayDunya est en mode test (clés « sandbox ») : aucun argent réel.
- Les retraits en FCFA sont simulés.
- L’hébergement gratuit (256 Mo de RAM) convient à une démonstration, pas à beaucoup d’utilisateurs en même temps.
- Pas de tâches en arrière-plan : `QUEUE_CONNECTION=sync`.

Pistes pour la suite : un LNbits hébergé par mes soins, PayDunya en production, de vrais retraits mobile money, des notifications et un hébergement plus costaud.

Auteure : [@Faith-time](https://github.com/Faith-time)<div align="center">

# SamaBoutik — API Backend

**API REST Laravel pour la gestion de boutique des petits commerçants, avec encaissement Bitcoin Lightning et mobile money.**

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-base%20de%20donn%C3%A9es-4479A1?logo=mysql&logoColor=white)
![Sanctum](https://img.shields.io/badge/Auth-Laravel%20Sanctum-FF2D20)
![Lightning](https://img.shields.io/badge/Bitcoin-Lightning%20(LNbits)-F7931A?logo=bitcoin&logoColor=white)
![PayDunya](https://img.shields.io/badge/Mobile%20money-PayDunya%20(test)-1E40AF)
![H%C3%A9bergement](https://img.shields.io/badge/H%C3%A9bergement-alwaysdata%20(gratuit)-0B7285)

**API en ligne :** `https://samaboutik.alwaysdata.net/api` · **Test de santé :** `GET /api/ping`

</div>

---

## Sommaire

1. [Présentation](#1-présentation)
2. [Fonctionnalités](#2-fonctionnalités)
3. [Stack technique](#3-stack-technique)
4. [Architecture](#4-architecture)
5. [Modèle de données](#5-modèle-de-données)
6. [Référence de l'API](#6-référence-de-lapi)
7. [Authentification et rôles](#7-authentification-et-rôles)
8. [Intégrations](#8-intégrations)
9. [Configuration (variables d'environnement)](#9-configuration-variables-denvironnement)
10. [Installation en local](#10-installation-en-local)
11. [Déploiement sur alwaysdata, pas à pas](#11-déploiement-sur-alwaysdata-pas-à-pas)
12. [Mettre à jour la production](#12-mettre-à-jour-la-production)
13. [Problèmes rencontrés et solutions](#13-problèmes-rencontrés-et-solutions)
14. [Sécurité](#14-sécurité)
15. [Limites connues et transparence](#15-limites-connues-et-transparence)
16. [Dépôts et ressources liés](#16-dépôts-et-ressources-liés)

---

## 1. Présentation

**SamaBoutik** est une application mobile destinée aux petits commerçants du Sénégal. Elle leur permet de :

- gérer leurs produits et leur stock ;
- enregistrer leurs ventes ;
- encaisser en **espèces**, en **mobile money** (Wave, Orange Money, Free Money via PayDunya) ou en **Bitcoin Lightning** (via LNbits) ;
- suivre leurs ventes, un classement de leurs produits, leurs statistiques et leurs retraits (simulés) ;
- recevoir une analyse de leur activité avec des conseils.

Ce dépôt contient le **backend** : une API REST en Laravel consommée par l'application Flutter (voir [§16](#16-dépôts-et-ressources-liés)).

Le montant d'une vente est exprimé en **FCFA**. Pour un paiement Bitcoin, le backend convertit le montant en **satoshis** au cours du moment, génère une facture Lightning valable **5 minutes**, puis confirme la vente automatiquement quand le paiement est reçu.

---

## 2. Fonctionnalités

| Domaine | Description |
|---|---|
| **Comptes** | Inscription, connexion, déconnexion, profil (`/api/moi`), authentification par jeton Sanctum. |
| **Produits** | Création, lecture, modification, suppression, **photo du produit** (téléversement et suppression). |
| **Stock** | Suivi du stock en unités et en paquets, seuil d'alerte, mouvements de stock, correction manuelle, valeur du stock, produits à réapprovisionner. |
| **Approvisionnements** | Enregistrement des achats de marchandises auprès des fournisseurs (en-tête et lignes). |
| **Ventes** | Création d'une vente multi-produits, détail, annulation, historique avec statuts (payée, en attente, annulée). |
| **Paiements** | Espèces, mobile money (PayDunya), Bitcoin Lightning (LNbits), suivi du statut d'un paiement. |
| **Taux de change** | Cours Bitcoin/FCFA mis en cache. |
| **Tableau de bord** | Ventes du jour, totaux sur 30 jours, ventes Bitcoin, alertes de stock. |
| **Classement** | Classement des produits par période (jour, semaine, mois, année) et par critère (plus vendus, plus rentables, plus de ventes). |
| **Analyse** | Rapports d'analyse avec conseils basés sur les ventes et le stock. |
| **Retraits** | Retraits en FCFA **simulés** (aucun argent réel ne bouge). |
| **Webhooks** | Réception des notifications de paiement Lightning et PayDunya. |

---

## 3. Stack technique

| Couche | Technologie |
|---|---|
| Langage | PHP **8.3+** (exécuté en **8.4** en production) |
| Framework | **Laravel 13** (`laravel/framework ^13.17`) |
| Authentification API | **Laravel Sanctum 4** (jetons personnels) |
| Rôles et permissions | **spatie/laravel-permission ^8.3** |
| Base de données | **MySQL** |
| Paiement Bitcoin | **LNbits** (API REST, réseau Lightning) |
| Paiement mobile money | **PayDunya** (mode test) |
| Cours du Bitcoin | **CoinGecko** (API publique `simple/price`) |
| Tests | PHPUnit 12, Mockery, Laravel Pint (style de code) |
| Hébergement | **alwaysdata** (offre gratuite, sans mise en veille) |
| Versionnement | Git / GitHub |

Dépendances de production (`composer.json`) : `laravel/framework`, `laravel/sanctum`, `laravel/tinker`, `spatie/laravel-permission`.

---

## 4. Architecture

```
                        ┌──────────────────────────────────┐
                        │      Application Flutter         │
                        │        (APK Android)             │
                        └───────────────┬──────────────────┘
                                        │ HTTPS · JSON · Bearer token (Sanctum)
                                        ▼
┌───────────────────────────────────────────────────────────────────────┐
│                   API Laravel — alwaysdata (Apache + PHP 8.4)         │
│  Controllers Api\*  →  validation  →  logique métier  →  Eloquent     │
└───────┬──────────────────┬───────────────────┬────────────────────────┘
        │                  │                   │
        ▼                  ▼                   ▼
   ┌─────────┐      ┌─────────────┐     ┌──────────────┐
   │  MySQL  │      │    LNbits   │     │   PayDunya   │
   │ (alwaysdata)   │ (Lightning) │     │ (mobile money)│
   └─────────┘      └──────┬──────┘     └──────┬───────┘
                           │ webhook           │ IPN
                           └─────────┬─────────┘
                                     ▼
                     POST /api/webhooks/lightning
                     POST /api/webhooks/paydunya
                               (+ CoinGecko pour le cours BTC)
```

**Contrôleurs** (espace de noms `App\Http\Controllers\Api`) :

| Contrôleur | Rôle |
|---|---|
| `AuthController` | Inscription, connexion, déconnexion, profil. |
| `ProduitController` | CRUD des produits, photo du produit. |
| `StockController` | État du stock, correction, classement des produits. |
| `ApprovisionnementController` | Achats de marchandises. |
| `VenteController` | Création, liste, détail et annulation des ventes. |
| `PaiementController` | Paiements espèces, Lightning, mobile money ; statut d'un paiement. |
| `WebhookController` | Réception des notifications Lightning et PayDunya. |
| `TauxChangeController` | Dernier taux de change Bitcoin. |
| `TableauBordController` | Indicateurs du tableau de bord. |
| `RapportIaController` | Rapport d'analyse courant, liste et détail. |
| `RetraitController` | Retraits simulés. |

---

## 5. Modèle de données

Les tables sont créées par les migrations Laravel (`database/migrations`). Tables métier :

| Table | Rôle |
|---|---|
| `utilisateurs` | Comptes des commerçants (connexion par numéro de téléphone et mot de passe). |
| `boutiques` | Boutique rattachée à un utilisateur. |
| `produits` | Catalogue : prix de vente, prix d'achat, unités par paquet, stock, seuil d'alerte, image. |
| `ventes` | En-tête d'une vente : montant, statut, date. |
| `lignes_vente` | Produits et quantités d'une vente. |
| `paiements` | Paiements d'une vente : moyen, montant, statut, facture Lightning, URL de paiement. |
| `evenements_webhook` | Journal des notifications reçues des fournisseurs de paiement. |
| `taux_changes` | Historique des taux Bitcoin/FCFA. |
| `mouvements_stock` | Entrées et sorties de stock (ventes, achats, corrections). |
| `approvisionnements` | Achats de marchandises (fournisseur, dépense en FCFA, date). |
| `lignes_approvisionnement` | Produits et quantités d'un approvisionnement. |
| `retraits` | Retraits simulés. |
| `rapports_ia` | Rapports d'analyse générés. |

Tables techniques : `personal_access_tokens` (Sanctum), `permissions`, `roles`, `model_has_permissions`, `model_has_roles`, `role_has_permissions` (Spatie), `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `migrations`.

**Historique des migrations** (ordre d'exécution) :

```
0001_01_01_000001_create_cache_table
0001_01_01_000002_create_jobs_table
2026_10_30_110819_create_personal_access_tokens_table
2026_10_30_121127_create_utilisateurs_table
2026_10_30_121130_create_boutiques_table
2026_10_30_121132_create_produits_table
2026_10_30_121138_create_ventes_table
2026_10_30_121141_create_ligne_ventes_table
2026_10_30_121142_create_paiements_table
2026_10_30_121144_create_retraits_table
2026_10_30_121148_create_rapport_ias_table
2026_10_30_121154_create_taux_changes_table
2026_10_30_121158_create_evenement_webhooks_table
2026_10_31_144315_create_permission_tables
2026_10_31_152948_ajouter_facture_lightning_et_url_paiement_a_paiements
2026_11_02_100000_gestion_du_stock
2026_11_04_100000_ajouter_image_aux_produits
2026_11_05_204511_create_sessions_table      (générée sur le serveur, voir §13)
```

---

## 6. Référence de l'API

**URL de base :** `https://samaboutik.alwaysdata.net/api`
**Format :** JSON · **Authentification :** en-tête `Authorization: Bearer <jeton>` (voir [§7](#7-authentification-et-rôles)).

### Public

| Méthode | Route | Contrôleur | Description |
|---|---|---|---|
| GET | `/api/ping` | (route directe) | Vérifie que l'API répond : `{"message":"SamaBoutik API OK"}`. |
| POST | `/api/inscription` | `AuthController@inscription` | Crée un compte. |
| POST | `/api/connexion` | `AuthController@connexion` | Connecte et renvoie un jeton. |
| POST | `/api/webhooks/lightning` | `WebhookController@lightning` | Notification de paiement Lightning (LNbits). |
| POST | `/api/webhooks/paydunya` | `WebhookController@paydunya` | Notification de paiement (IPN PayDunya). |

### Compte

| Méthode | Route | Description |
|---|---|---|
| POST | `/api/deconnexion` | Révoque le jeton courant. |
| GET | `/api/moi` | Profil de l'utilisateur connecté. |

### Produits

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/produits` | Liste des produits. |
| POST | `/api/produits` | Crée un produit. |
| GET | `/api/produits/{produit}` | Détail d'un produit. |
| PUT / PATCH | `/api/produits/{produit}` | Modifie un produit. |
| DELETE | `/api/produits/{produit}` | Supprime un produit. |
| POST | `/api/produits/{id}/image` | Téléverse la photo du produit. |
| DELETE | `/api/produits/{id}/image` | Supprime la photo du produit. |

### Stock et approvisionnements

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/stock` | État du stock : valeur, achats 30 jours, produits à réapprovisionner. |
| POST | `/api/produits/{id}/stock/correction` | Corrige manuellement le stock d'un produit. |
| GET | `/api/classement` | Classement des produits (période et critère au choix). |
| GET | `/api/approvisionnements` | Liste des approvisionnements. |
| POST | `/api/approvisionnements` | Enregistre un approvisionnement. |

### Ventes et paiements

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/ventes` | Historique des ventes. |
| POST | `/api/ventes` | Crée une vente. |
| GET | `/api/ventes/{id}` | Détail d'une vente. |
| POST | `/api/ventes/{id}/annuler` | Annule une vente. |
| POST | `/api/ventes/{id}/paiements/especes` | Paiement en espèces. |
| POST | `/api/ventes/{id}/paiements/lightning` | Crée une facture Bitcoin Lightning. |
| POST | `/api/ventes/{id}/paiements/mobile` | Lance un paiement mobile money (PayDunya). |
| GET | `/api/paiements/{id}` | Statut d'un paiement (utilisé pour le suivi en temps réel). |

### Indicateurs, taux, analyse, retraits

| Méthode | Route | Description |
|---|---|---|
| GET | `/api/tableau-de-bord` | Indicateurs de l'accueil. |
| GET | `/api/taux-change` | Dernier taux Bitcoin/FCFA. |
| GET | `/api/rapport-ia` | Rapport d'analyse courant. |
| GET | `/api/rapports-ia` | Liste des rapports. |
| GET | `/api/rapports-ia/{id}` | Détail d'un rapport. |
| GET | `/api/retraits` | Historique des retraits simulés. |
| POST | `/api/retraits` | Crée un retrait simulé. |

Pour lister toutes les routes depuis le serveur : `php artisan route:list`.

**Exemple :**

```bash
curl -i https://samaboutik.alwaysdata.net/api/ping
# HTTP/2 200
# {"message":"SamaBoutik API OK"}
```

---

## 7. Authentification et rôles

- **Sanctum** émet un jeton personnel à la connexion (table `personal_access_tokens`). L'application Flutter le stocke de façon sécurisée et l'envoie dans l'en-tête `Authorization: Bearer`.
- Les jetons sont **hachés** en base par Sanctum.
- La connexion se fait par **numéro de téléphone et mot de passe** (mots de passe hachés, `BCRYPT_ROUNDS=12`).
- **spatie/laravel-permission** fournit les tables de rôles et de permissions (`roles`, `permissions`, `model_has_roles`, …), créées par la migration `create_permission_tables`.

---

## 8. Intégrations

### 8.1 Bitcoin Lightning avec LNbits

**Pourquoi LNbits ?** C'est une couche légère au-dessus d'un nœud Lightning : elle expose une API REST simple pour créer des factures et suivre leur paiement, sans exploiter son propre nœud.

**Clés LNbits utilisées**

| Clé | Usage |
|---|---|
| **Invoice/read key** | Créer des factures entrantes et vérifier leur paiement. **C'est celle à utiliser** (`LIGHTNING_API_KEY`). |
| Admin key | Permet aussi de dépenser les fonds. Non nécessaire ici (retraits simulés). |

**Flux de paiement**

```
1. App  → POST /api/ventes                          crée la vente (montant en FCFA)
2. App  → POST /api/ventes/{id}/paiements/lightning
3. API  → lit le taux BTC/EUR (CoinGecko, mis en cache), convertit FCFA → EUR → satoshis,
          applique une marge de sécurité, crée une facture LNbits (durée 5 minutes)
4. API  → renvoie la facture à l'app, qui affiche un QR code et un compte à rebours
5. Client scanne et paie avec son portefeuille Lightning
6. LNbits → POST /api/webhooks/lightning            (notification de paiement)
7. API  → enregistre l'événement, passe le paiement et la vente à « payée »
8. App  → GET /api/paiements/{id}                   l'écran passe à « Paiement réussi »
```

**Conversion FCFA → satoshis**

- Le FCFA est converti en euros avec la parité fixe `XOF_PER_EUR=655.957`.
- Le cours Bitcoin/EUR vient de CoinGecko (`RATE_API_URL`), mis en cache `RATE_CACHE_SECONDS=60` secondes pour limiter les appels.
- Une marge `SATS_SAFETY_MARGIN_PERCENT=0.5` (%) protège contre les variations pendant les 5 minutes de validité.
- Le montant en satoshis est figé au moment de la création de la facture. Passé `LIGHTNING_EXPIRATION_MINUTES=5` minutes, la facture expire et il faut en créer une nouvelle au cours du moment.

**Webhook.** LNbits appelle `POST /api/webhooks/lightning`. Le point d'entrée est protégé par un secret partagé (`LIGHTNING_WEBHOOK_SECRET`), et chaque notification est journalisée dans `evenements_webhook`. **Ce secret n'existe pas dans LNbits : on le génère soi-même** (`openssl rand -hex 32`).

> ⚠️ `https://demo.lnbits.com` est un **serveur de démonstration public**. Il convient pour un prototype, mais peut être réinitialisé. Pour une vraie mise en production, utiliser un LNbits auto-hébergé ou un fournisseur dédié.

### 8.2 Mobile money avec PayDunya

PayDunya agrège les moyens de paiement mobile (Wave, Orange Money, Free Money).

```
1. App  → POST /api/ventes/{id}/paiements/mobile
2. API  → crée la facture PayDunya et mémorise l'URL de paiement (table paiements)
3. App  → ouvre cette URL dans une WebView (page de paiement PayDunya)
4. Client paie sur la page PayDunya
5. PayDunya → POST /api/webhooks/paydunya           (IPN, notification instantanée)
6. API  → met à jour le paiement et la vente
7. App  → GET /api/paiements/{id}                   suivi du statut
```

**Clés** (`.env`) : `PAYDUNYA_MASTER_KEY`, `PAYDUNYA_PRIVATE_KEY`, `PAYDUNYA_PUBLIC_KEY`, `PAYDUNYA_TOKEN`, `PAYDUNYA_MODE`.
**Mode actuel : `test`**, avec les clés « sandbox » : les paiements sont simulés, aucun argent réel ne circule.

**Passage en production (non effectué).** D'après la documentation PayDunya : tableau de bord → *Intégration API* → *Détails* de l'application → *Modifier la configuration* → activer le mode production ; puis remplacer les clés de test par les clés de production et passer `PAYDUNYA_MODE=live`. La validation du compte peut exiger des documents d'entreprise.

### 8.3 Taux de change

Le cours Bitcoin est récupéré chez CoinGecko, converti en FCFA, mis en cache puis historisé dans `taux_changes`. L'application le lit via `GET /api/taux-change`.

### 8.4 Stock et classement

Chaque vente, approvisionnement ou correction crée un enregistrement dans `mouvements_stock`. Le stock est suivi en **unités** et en **paquets** (champ « unités par paquet »). Un seuil d'alerte déclenche les états « stock bas » et « épuisé », et alimente la liste « à réapprovisionner ». Le classement (`GET /api/classement`) agrège les ventes par produit sur une période, avec ventes, bénéfice, part des ventes et stock restant.

### 8.5 Analyse et rapports

`RapportIaController` expose les rapports d'analyse (table `rapports_ia`) : évolution des ventes, panier moyen, bénéfice, meilleur jour de la semaine, produits stars, produits à racheter, marges faibles.

---

## 9. Configuration (variables d'environnement)

Copier `.env.example` vers `.env`, puis renseigner les valeurs. **Ne jamais committer `.env`** (il est dans `.gitignore`).

| Variable | Rôle | Production |
|---|---|---|
| `APP_NAME` | Nom de l'application | `SamaBoutik` |
| `APP_ENV` | Environnement | `production` |
| `APP_KEY` | Clé de chiffrement | générée par `php artisan key:generate` |
| `APP_DEBUG` | Affichage des erreurs détaillées | `false` |
| `APP_URL` | URL publique (utilisée pour les callbacks) | `https://samaboutik.alwaysdata.net` |
| `APP_LOCALE`, `APP_TIMEZONE` | Langue et fuseau | `fr`, `Africa/Dakar` |
| `DB_CONNECTION` | Pilote | `mysql` |
| `DB_HOST`, `DB_PORT` | Serveur MySQL | `mysql-<compte>.alwaysdata.net`, `3306` |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Base et identifiants | fournis par alwaysdata |
| `SESSION_DRIVER` | Stockage des sessions | `database` (table `sessions` requise) ou `file` |
| `CACHE_STORE` | Cache | `database` |
| `QUEUE_CONNECTION` | File de tâches | `sync` (pas de processus permanent sur l'offre gratuite) |
| `FILESYSTEM_DISK` | Disque de fichiers | `local` |
| `LOG_LEVEL` | Niveau de journalisation | `error` |
| `LIGHTNING_API_URL` | URL de LNbits | `https://demo.lnbits.com` |
| `LIGHTNING_API_KEY` | Invoice/read key LNbits | secret |
| `LIGHTNING_WEBHOOK_SECRET` | Secret partagé du webhook | secret généré |
| `LIGHTNING_EXPIRATION_MINUTES` | Durée de validité d'une facture | `5` |
| `SATS_SAFETY_MARGIN_PERCENT` | Marge de sécurité sur le montant en sats | `0.5` |
| `XOF_PER_EUR` | Parité FCFA/euro | `655.957` |
| `RATE_API_URL` | API du cours Bitcoin | `https://api.coingecko.com/api/v3/simple/price` |
| `RATE_CACHE_SECONDS` | Durée du cache du taux | `60` |
| `PAYDUNYA_MASTER_KEY`, `PAYDUNYA_PRIVATE_KEY`, `PAYDUNYA_PUBLIC_KEY`, `PAYDUNYA_TOKEN` | Clés PayDunya | secrets (clés de test) |
| `PAYDUNYA_MODE` | `test` ou `live` | `test` |

Après toute modification du `.env` en production : `php artisan config:cache`.

---

## 10. Installation en local

**Prérequis :** PHP 8.3+, Composer 2, MySQL, Git (ex. Laragon sous Windows).

```bash
git clone https://github.com/Faith-time/samaboutik-api.git
cd samaboutik-api

composer install
cp .env.example .env
php artisan key:generate
```

Créer une base MySQL (ex. `samaboutik`), renseigner `DB_*` dans `.env` (`DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`), puis :

```bash
php artisan migrate
php artisan storage:link
php artisan serve        # http://localhost:8000
```

Vérifier : `curl http://localhost:8000/api/ping`.

Pour tester les webhooks en local, il faut une adresse publique (tunnel) car LNbits et PayDunya doivent pouvoir joindre l'API. Pour les tests de l'application mobile sur un émulateur Android, l'API locale est joignable via `http://10.0.2.2:8000/api`.

**Tests :**

```bash
composer test
```

---

## 11. Déploiement sur alwaysdata, pas à pas

### 11.1 Pourquoi alwaysdata

Contraintes du projet : **gratuit**, **sans carte bancaire**, **sans mise en veille** (une API qui « dort » donnerait l'impression d'un bug au jury). L'offre gratuite d'alwaysdata fournit : 1 Go de disque, 256 Mo de RAM, ¼ de CPU, sauvegardes sur 3 jours, usage personnel, sans limite de durée, avec accès SSH, MySQL, phpMyAdmin et HTTPS automatique.

Versions constatées sur le serveur le 5 novembre 2026 : **PHP 8.4.27**, **Composer 2.9.8**, **Git 2.45.1**.

### 11.2 Création du compte et de la base

1. Créer un compte alwaysdata (offre Free). Compte utilisé : `samaboutik` → site `https://samaboutik.alwaysdata.net`.
2. *Bases de données → MySQL → Ajouter* : base `samaboutik_db`, utilisateur et mot de passe.
3. *Utilisateurs* : activer l'accès **SSH** et définir un mot de passe.
4. Ouvrir **phpMyAdmin** et **importer** la base MySQL exportée depuis l'environnement local (ainsi les tables et données de démonstration sont présentes ; la table `migrations` est incluse).

### 11.3 Configuration du site

*Web → Sites* → modifier le site `samaboutik.alwaysdata.net` :

- **Type :** PHP (8.3 ou 8.4)
- **Répertoire racine :** `/home/samaboutik/samaboutik-api/public`

> Le champ ne doit contenir **qu'un seul chemin**, celui qui se termine par `/public`.

### 11.4 Connexion SSH et vérifications

```bash
ssh samaboutik@ssh-samaboutik.alwaysdata.net

php -v                       # PHP 8.4.27
composer --version           # Composer 2.9.8
git --version                # git 2.45.1

# Extensions PHP requises par Laravel
php -m | grep -Ei 'bcmath|ctype|curl|fileinfo|gd|mbstring|openssl|pdo|pdo_mysql|tokenizer|xml'

# Le dépôt est accessible depuis le serveur
git ls-remote https://github.com/Faith-time/samaboutik-api.git
```

### 11.5 Récupération du code et installation

```bash
cd /home/samaboutik
git clone https://github.com/Faith-time/samaboutik-api.git samaboutik-api
cd samaboutik-api

composer install --no-dev --optimize-autoloader
```

### 11.6 Fichier `.env` de production

```bash
cp .env.example .env
nano .env
```

Renseigner toutes les variables du [§9](#9-configuration-variables-denvironnement) (URL en `https`, base alwaysdata, clés LNbits et PayDunya, `APP_ENV=production`, `APP_DEBUG=false`, `QUEUE_CONNECTION=sync`). Enregistrer : `Ctrl+O`, Entrée, puis `Ctrl+X`.

### 11.7 Initialisation de Laravel

```bash
php artisan key:generate          # génère un APP_KEY propre à la production
php artisan migrate:status        # toutes les migrations doivent être « Ran »

# Correctif de la table des sessions (voir §13)
php artisan make:session-table
php artisan migrate --force

php artisan optimize:clear
php artisan config:cache
```

> `php artisan route:cache` n'est **pas** utilisé : `routes/api.php` contient une route définie par fonction (`/api/ping`), et Laravel ne peut pas mettre en cache ce type de route.
> Ne **pas** lancer `db:seed` sur une base déjà importée (risque de doublons).

### 11.8 Vérification

```bash
php artisan route:list            # 39 routes attendues

curl -i https://samaboutik.alwaysdata.net/api/ping
# HTTP/2 200
# {"message":"SamaBoutik API OK"}
```

Contrôle du réseau sortant depuis le serveur (indispensable pour Lightning, PayDunya et le cours du Bitcoin) :

```bash
curl -sI https://demo.lnbits.com | head -1
curl -s "https://api.coingecko.com/api/v3/simple/price?ids=bitcoin&vs_currencies=eur"
curl -sI https://app.paydunya.com | head -1
```

### 11.9 Images de produits

Les photos sont enregistrées dans `storage/`. Si les images ne s'affichent pas :

```bash
php artisan storage:link
```

et vérifier que les fichiers présents en local (`storage/app/public`) ont été copiés sur le serveur dans `/home/samaboutik/samaboutik-api/storage/app/public` (SFTP, par exemple avec FileZilla : hôte `sftp://ssh-samaboutik.alwaysdata.net`, port 22).

### 11.10 Webhooks à déclarer

| Fournisseur | URL |
|---|---|
| LNbits | `https://samaboutik.alwaysdata.net/api/webhooks/lightning` |
| PayDunya (IPN) | `https://samaboutik.alwaysdata.net/api/webhooks/paydunya` |

---

## 12. Mettre à jour la production

Après un `git push` depuis le poste de développement :

```bash
ssh samaboutik@ssh-samaboutik.alwaysdata.net
cd /home/samaboutik/samaboutik-api

git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
```

Les applications mobiles déjà installées utilisent immédiatement la nouvelle version de l'API : aucune réinstallation n'est nécessaire côté téléphone pour les changements de backend. Conserver la **compatibilité** des routes déjà utilisées par l'APK publié.

Consulter les erreurs : `tail -n 50 storage/logs/laravel.log`.

---

## 13. Problèmes rencontrés et solutions

| Symptôme | Cause | Solution |
|---|---|---|
| `Connection closed by … port 22` à la première connexion SSH | Première prise de contact avec l'hôte | Relancer la commande `ssh`. |
| `SQLSTATE[HY000] [1045] Access denied for user …` au premier `migrate:status` | Identifiants MySQL incorrects dans `.env` | Corriger `DB_USERNAME` et `DB_PASSWORD` avec ceux créés dans alwaysdata, puis relancer. |
| **Erreur 500** sur le site, journal : `Table 'samaboutik_db.sessions' doesn't exist` (`SQLSTATE[42S02]`) | `SESSION_DRIVER=database` mais la table `sessions` n'existait pas dans la base importée | `php artisan make:session-table` puis `php artisan migrate --force` (migration `2026_11_05_204511_create_sessions_table`), puis `php artisan optimize:clear`. Alternative : `SESSION_DRIVER=file`. |
| `Could not open input file: artisan` | Commande lancée hors du dossier du projet | `cd /home/samaboutik/samaboutik-api` avant les commandes `php artisan`. |

**Méthode de diagnostic utilisée** : lire d'abord le message principal (`grep -n -B 3 -A 2 "SQLSTATE" storage/logs/laravel.log | tail -n 20`) plutôt que la fin de la pile d'appels.

> Pour une installation neuve sur un autre serveur : la migration `sessions` ayant été générée sur le serveur, exécuter `php artisan make:session-table` avant `migrate`, ou utiliser `SESSION_DRIVER=file` (l'API s'appuie sur des jetons Sanctum, pas sur les sessions).

---

## 14. Sécurité

- Aucun secret n'est versionné : `.env` est exclu de Git ; seul `.env.example` est public.
- Les clés ont été **régénérées** pour la production (`APP_KEY`, secret de webhook, clés LNbits et PayDunya).
- `APP_DEBUG=false` en production.
- Trafic exclusivement en **HTTPS** (certificat fourni par alwaysdata).
- Jetons Sanctum hachés ; mots de passe hachés (bcrypt).
- LNbits : utilisation de l'**Invoice/read key** (lecture et création de factures), pas de l'Admin key.
- Les notifications de paiement sont journalisées (`evenements_webhook`) et le webhook Lightning est protégé par un secret partagé.

---

## 15. Limites connues et transparence

| Élément | État |
|---|---|
| Bitcoin Lightning | Fonctionnel de bout en bout via LNbits (**serveur de démonstration**). |
| PayDunya | **Mode test** (clés sandbox) : paiements simulés. |
| Retraits en FCFA | **Simulés** : aucun transfert réel. |
| Hébergement gratuit | 256 Mo de RAM et ¼ de CPU : adapté à une démonstration, pas à une forte charge. |
| Tâches en arrière-plan | `QUEUE_CONNECTION=sync` : pas de worker permanent. |

**Pistes d'évolution :** LNbits auto-hébergé, passage de PayDunya en production, vrais retraits mobile money, notifications push, hébergement dimensionné pour la charge.

---

## 16. Dépôts et ressources liés

- **Application mobile Flutter :** https://github.com/Faith-time/samaboutik-app
- **APK de démonstration :** onglet *Releases* du dépôt Flutter
- **API en ligne :** https://samaboutik.alwaysdata.net/api/ping

**Auteur :** [@Faith-time](https://github.com/Faith-time)

# MESSANGER

Messagerie web minimaliste : un mur de discussion partage, avec comptes,
sessions, rafraichissement des messages sans rechargement de page et une
interface adaptee au mobile.

Stack : PHP 8 (mysqli procedural, sans framework), MySQL / MariaDB, JavaScript
sans dependance. Concu pour tourner sur un stack XAMPP / WAMP.

## Attention aux noms

La **base de donnees** s'appelle `messanger`, la **table** des messages
`messange` (sans « r »). Ce n'est pas une faute de frappe a corriger : les
deux orthographes sont utilisees telles quelles dans tout le code.

## Prerequis

- PHP >= 8.1 (teste sous 8.2)
- MySQL ou MariaDB
- Un serveur web servant le dossier du projet (Apache de XAMPP, ou le serveur
  integre de PHP pour du developpement)

## Installation

### 1. Configuration

Les identifiants de base ne sont pas dans le depot. Copier le modele et
l'adapter :

```sh
cp config.example.php config.php
```

`config.php` (ignore par git) contient les identifiants MySQL et le fuseau
horaire, applique a la fois a PHP et a MySQL :

```php
return [
    'db' => [
        'host' => 'localhost',
        'user' => 'root',
        'pass' => '',            // vide par defaut sous XAMPP ; a definir en production
        'name' => 'messanger',
    ],
    'timezone' => 'Africa/Tunis', // un identifiant de https://www.php.net/manual/fr/timezones.php
];
```

### 2. Base de donnees

Creer la base, importer le schema de depart, puis appliquer les migrations :

```sh
mysql -u root -e "CREATE DATABASE messanger CHARACTER SET utf8mb4"
mysql -u root messanger < db/messange.sql
php migrate.php
```

`migrate.php` applique les migrations de `db/` non encore passees et les note
dans la table `schema_migrations` (voir la section Migrations).

### 3. Lancer

Avec le serveur integre de PHP, depuis la racine du projet :

```sh
php -S localhost:8000
```

Puis ouvrir http://localhost:8000/register.php pour creer un compte. Sous
XAMPP/Apache, placer le projet dans `htdocs/` et ouvrir l'URL correspondante.

## Structure

| Fichier | Role |
|---|---|
| `index.php` | Mur des messages + formulaire d'envoi (protege par session) |
| `register.php` / `login.php` / `logout.php` | Comptes et sessions |
| `process.php` | Reception d'un message poste (Post-Redirect-Get) |
| `messages.php` | Endpoint JSON incrementiel interroge par le client |
| `auth.php` | Bootstrap de session, CSRF, limitation des tentatives, constantes |
| `database.php` | Connexion, fuseau horaire, gestion des exceptions mysqli |
| `config.php` | Identifiants locaux (hors depot) |
| `migrate.php` | Applicateur de migrations (CLI) |
| `js/script.js` | Rafraichissement des messages par interrogation periodique |
| `db/` | Schema de depart et migrations |

## Migrations

```sh
php migrate.php            # applique les migrations en attente
php migrate.php --status   # affiche l'etat sans rien appliquer
php migrate.php --baseline # marque comme appliquees sans executer
```

Le mode `--baseline` sert a adopter une base dont le schema existe deja (par
exemple mise a jour manuellement avant l'ajout de ce suivi) : il enregistre
les migrations comme passees sans les rejouer.

L'applicateur s'arrete a la premiere migration en echec. A noter : en MySQL,
les instructions DDL (`CREATE`, `ALTER`) declenchent un commit implicite, donc
une migration qui echoue en son milieu laisse un schema partiel a corriger a
la main avant de relancer.

## Reglages

Dans `auth.php` :

- `MESSAGE_MAX_LENGTH` — longueur maximale d'un message (2000 caracteres)
- `LOGIN_WINDOW_MINUTES` / `LOGIN_MAX_PER_USER` / `LOGIN_MAX_PER_IP` —
  limitation des connexions (5 par compte, 20 par IP, sur 15 minutes)
- `REGISTER_WINDOW_MINUTES` / `REGISTER_MAX_PER_IP` — limitation des
  inscriptions (5 par IP sur 60 minutes)

## Avant une mise en production

Ce projet fonctionne mais n'est pas pret pour un usage public en l'etat :

- **Identifiants** : creer un utilisateur MySQL dedie avec mot de passe et
  droits limites a la base `messanger`, plutot que `root` sans mot de passe.
- **`config.php` hors racine web** : le placer au-dessus du dossier servi, pour
  qu'une defaillance de la configuration PHP ne puisse pas l'exposer en clair.
- **HTTPS** : le cookie de session ne passe en `Secure` que derriere HTTPS.
- **La limitation par IP se fie a `REMOTE_ADDR`** : derriere un reverse proxy,
  il faudra lire `X-Forwarded-For`, mais seulement s'il vient d'un proxy de
  confiance.

## Limites connues

- L'envoi d'un message recharge la page ; seule la reception est en temps reel.
- `index.php` charge toute la conversation au premier rendu (pas de pagination).
- Pas de recuperation de mot de passe ni d'expiration de session par inactivite.

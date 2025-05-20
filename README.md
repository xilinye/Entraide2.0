# Projet Symfony - Guide d'installation

## Prérequis

- Docker et Docker Compose
- PHP 8.1+
- Composer

## Installation

```bash
# Installer les dépendances PHP
composer install

# Démarrer l'infrastructure Docker (base de données + simulateur mail)
docker compose up -d

# Créer la base de données locale
symfony console doctrine:database:create --if-not-exists

# Exécuter les migrations
symfony console doctrine:migrations:migrate

# Démarrer le serveur Symfony
symfony serve -d

# Nettoyage des utilisateurs anonymes
docker-compose exec php bin/console app:cleanup-anonymous-user

# Promouvoir un administrateur
symfony console app:promote-admin exemple@mail.com

# Préparation de l'environnement de test
docker compose exec php bash -c "APP_ENV=test php bin/console doctrine:database:create"
docker compose exec php bash -c "APP_ENV=test php bin/console doctrine:schema:create"

# Exécuter tous les tests
docker compose exec php bash -c "APP_ENV=test php ./bin/phpunit -c phpunit.xml.dist"

# Exécuter un test spécifique
docker compose exec php bash -c "APP_ENV=test php bin/phpunit tests/Entity/BlogPostTest.php"
```

# Crée un fichier .env.local avec ce contenu

DATABASE_URL="mysql://entraide_user:mysql_password@database:3306/entraide?serverVersion=8.0"
MAILER_DSN=smtp://mailer:1025
APP_TIMEZONE='Europe/Paris'

MERCURE_URL=http://mercure/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure

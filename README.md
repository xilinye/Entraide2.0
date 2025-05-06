composer install permet d'installer les dépendances
<br>commande pour démarrer le site : symfony serve -d
<br>démarer la base de donnée et simulateur : docker compose up -d
<br>commande pour la base crée sur le local : symfony console doctrine:database:create --if-not-exists
<br>commande pour mise à jour : symfony console doctrine:migrations:migrate
<br>crée base de donné test : docker compose exec php bin/console doctrine:database:create --env=test
<br>mise à jour base de donnée de test : docker compose exec php bin/console doctrine:migrations:migrate --env=test
<br>lancer les test : docker compose exec php ./bin/phpunit -c phpunit.xml.dist
<br>lancer un test : docker compose exec php bin/phpunit tests/Entity/BlogPostTest.php
<br>Donner le droit à une premier personne : symfony console app:promote-admin exemple@mail.com
<br>url du site : https://127.0.0.1:8000/
<br> url du simulateur : http://localhost:8025/
<br> url de la base de donnée : http://localhost:8080/
<br>Contenu dans .env.local :
<br>APP_ENV=dev
APP_DEBUG=1

DATABASE_URL="mysql://entraide_user:mysql_password@127.0.0.1:3306/entraide?serverVersion=8.0"

MAILER_DSN=smtp://localhost:1025

APP_TIMEZONE='Europe/Paris'

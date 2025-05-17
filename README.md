composer install permet d'installer les dépendances
<br>
<br>commande pour démarrer le site : symfony serve -d
<br>
<br>démarer la base de donnée et simulateur : docker compose up -d
<br>
<br>commande pour la base crée sur le local : symfony console doctrine:database:create --if-not-exists
<br>
<br>commande pour mise à jour : symfony console doctrine:migrations:migrate
<br>
<br>crée la base de test : docker compose exec php bash -c "APP_ENV=test php bin/console doctrine:database:create"
<br>
<br>docker compose exec php bash -c "APP_ENV=test php bin/console doctrine:schema:create"
<br>
<br>lancer les test : docker compose exec php bash -c "APP_ENV=test php ./bin/phpunit -c phpunit.xml.dist"
<br>
<br>lancer un test : docker compose exec php bash -c "APP_ENV=test php bin/phpunit tests/Entity/BlogPostTest.php"
<br>
<br>Donner le droit à une premier personne : symfony console app:promote-admin exemple@mail.com
<br>
<br>url du site : https://127.0.0.1:8000/
<br>
<br> url du simulateur : http://localhost:8025/
<br>
<br> url de la base de donnée : http://localhost:8080/
<br>
<br>Contenu dans .env.local :
<br>APP_ENV=dev
APP_DEBUG=1

DATABASE_URL="mysql://entraide_user:mysql_password@127.0.0.1:3306/entraide?serverVersion=8.0"

MAILER_DSN=smtp://localhost:1025

APP_TIMEZONE='Europe/Paris'

composer install permet d'installer les dépendances
<br>commande pour démarrer le site : symfony serve -d
<br>démarer la base de donnée et simulateur : docker compose up -d
<br>commande pour la base crée sur le local : symfony console doctrine:database:create --if-not-exits
<br>commande pour mise à jour : symfony console doctrine:migrations:migrate
<br>Donner le droit à une premier personne : symfony console app:promote-admin exemple@mail.com
<br>url du site : https://127.0.0.1:8000/
<br> url du simulateur : http://localhost:8025/
<br> url de la base de donnée : http://localhost:8080/

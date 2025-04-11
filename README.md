composer install permet d'installer les dépendances
<br>commande pour démarrer le site : symfony serve -d
<br>commande pour démarrer la base de donnée : selon la base de donnée local utilisé
<br>commande pour la base crée sur le local : symfony console doctrine:database:create --if-not-exits
<br>commande pour mise à jour : symfony console doctrine:migrations:migrate
<br>commande pour démarrer la simulation d'email : sudo docker run -d -p 8025:8025 -p 1025:1025 mailhog/mailhog
<br>Donner le droit à une premier personne : symfony console app:promote-admin exemple@mail.com

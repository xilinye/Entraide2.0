commande pour démarrer le site : symfony serve -d
####commande pour démarrer la base de donnée : selon la base de donnée local utilisé
####commande pour la base crée sur le local : symfony console doctrine database:create --if-not-exits
####commande pour mise à jour : symfony console doctrine migrations:migrate
####commande pour démarrer la simulation d'email : sudo docker run -d -p 8025:8025 -p 1025:1025 mailhog/mailhog

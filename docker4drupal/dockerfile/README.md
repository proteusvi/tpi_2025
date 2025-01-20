# Dockerfiles pour PHP et NODE
## Introduction
Anciennement, nous utilisions un script (init.sh ou new_ini.sh) qui configurait
php, composer, node et plus spécifiquement yarn avec le repository de l'état 
ainsi que l'environnement état. Il installait égualement composer et yarn.
Il fallait absolument lancer le script depuis un des deux conteneurs php ou node
pour pouvoir soit, utiliser composeur, sortir par les proxies ou encore utiliser
yarn.

Aujourd'hui, nous avons déplacé les actions de ce script dans deux Dockerfiles,
- docker/dockerfile/node/Dockerfile
- docker/dockerfile/php/Dockerfile

## docker/dockerfile/node/Dockerfile
Ce répertoire contient un fichier en plus, ge-app.b64 qui est le certificat 
autosigné de l'état.

## docker/dockerfile/php/Dockerfile
Ce répertoir contient deux fichiers :
* composer-setup.php nécessaire à l'installation de composeur dans le conteneur.
* ge-app.b64 le certificat autosigné de l'état.


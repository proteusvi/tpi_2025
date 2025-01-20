# Les scripts
| Nom du script          | Description                                                     |
|------------------------|-----------------------------------------------------------------|
| goProject              | Outil de sélection du projet en ligne de commande               |
| .aliases               | Fichier d'aliases avec les commande de base docker simplifiées. |
| dbx_bash_tools_library | Librairie bash avec des fonctions utiles aux aliases.           |
| sync2opensource.sh     | Script de synchronisation du code vers celui opensource.        |

## goProject
### Impondérables
Il faut soit être sous unix like (Linux ou cygwin) pour utiliser ce script.
 
### Description
* Ce script est écrit en bash et permet de naviger jusqu'a son projet, il charge la configuration de 
l'environnement et les alias nécessaires à notre projet dans docker/scripts.
* La configuration est la suivante :
~~~bash
.
├── ci
├── docker
│   ├── docker-compose.yml
│   └── scripts/
│       ├── .aliases
│       ├── dbx_bash_tools_library
│       ├── goProject
│       └── README.md
├── documents
├── htdocs
└── README.md
~~~
#### Dans le code, en début de fichier il y a deux tableaux :
* Le premier représente les alias que vous voulez utiliser lors de l'appelle. Il sera concidérer comme
la clé du prochain tableau que je vous décris maintenant.
~~~bash
declare -a indexes=( \
    "drupal" \
)
~~~
* Le second tableau contient l'alias défini dans le premier tableau et le path du projet associé.
~~~bash
# Paths associés au projets déployés sur son poste
declare -A projets=( \
        ['drupal']="${HOME}/workspace/mon_drupal_9" \
)
~~~
* C'est dans ces tableau que vous devez faire vos modifications.

## Auteurs
***
| Avatar                                             | Identité    | e-mail adress              |
|----------------------------------------------------|-------------|----------------------------|
| ![Didier Beux](../../images/beux.didier.32x32.png) | Didier Beux | DBX didier.beux@etat.ge.ch |

#    .--.
#   |o_o |
#   |:_/ |
#  //   \ \
# (|     | )
#/'\_   _/`\
#\___)=(___/
#+-------------------------------------------+
#| Node avec setting des proxys de la maison |
#| et du certificat self-signed.             |
#+-------------------------------------------+
FROM wodby/node:latest
# Variables.
ARG certificat_autosign='/etc/ssl/certs/edg.crt'
# Variables  d'environnement.
ENV NODE_EXTRA_CA_CERTS=${certificat_autosign}
# Ajout du certificat dans le container.
COPY 'ge-app.b64' ${certificat_autosign}
# Configuration de yarn.
RUN yarn config set registry "https://registry.devops.etat-ge.ch/ctinexus/repository/npmjs"

# Dans le cadre d'une migration d'un projet de NPM vers YARN
# 1- décommentez cette ligne pour lancer la génération automatique du fichier yarn.lock
# 2- Vérifiez que  la procédure ne lève aucune erreur et que le fichier yarn.lock est bien créé
# 3- Supprimez le fichier package-lock.json
#    yarn import

# Installation de Gulp avec Yarn.
RUN yarn cache clean --all
RUN yarn global add gulp-cli
RUN yarn install


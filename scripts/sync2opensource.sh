#!/bin/bash
#    .--.
#   |o_o |
#   |:_/ |
#  //   \ \
# (|     | )
#/'\_   _/`\
#\___)=(___/
#+--------------------------------------------------------------------------+
#| Script de synchronisation du code source                                 |
#| de l'applicaiton 10677 NGEO vers noms-geographiques                      |
#| sur https://github.com/republique-et-canton-de-geneve/noms-geographiques |
#+--------------------------------------------------------------------------+

target="$1"

function print_help() {
   echo "Help"
   echo "----"
   echo " $0 <target_directory>"
   echo
   echo " target_directory : Directory where you want to syncronize the code."
   echo
}

function validate() {
  is_valid=0
  if [[ ${target} == "" ]]; then
    is_valid=1
  fi
  return ${is_valid}
}

function synchronizeDir() {
  rsync -av \
    --exclude '/ci' \
    --exclude '/config/saml/dev' \
    --exclude '/config/settings.local.php' \
    --exclude '/config/settings.local.dev.example.php' \
    --exclude '/docker/certs' \
    --exclude '/docker/mariadb' \
    --exclude '/docker/cacert.pem' \
    --exclude '/docker/ge-app.b64' \
    --exclude '/docker/init.sh' \
    --exclude '/docker/new_init.sh' \
    --exclude '/docker/migration.sh' \
    --exclude '/docker/scripts/sync2opensource.sh' \
    --exclude '/htdocs/core' \
    --exclude '/htdocs/modules/contrib' \
    --exclude '/htdocs/themes/contrib' \
    --exclude '/.git' \
    --exclude '/.idea' \
    --exclude '/media/*' \
    --exclude '/scripts' \
    --exclude '/vendor' \
    --exclude '/README.md' \
    --exclude '*.swp' \
    --exclude '*~' \
    ../. ${target}
}

function update_gitignore() {
  echo '' >>"${target}/.gitignore"
  echo '# For open-sources to ignore.' >>"${target}/.gitignore"
  echo '/docker/ge-app.b64' >>"${target}/.gitignore"
  echo '/docker/init.sh' >>"${target}/.gitignore"
  echo '/vendor/' >>"${target}/.gitignore"
  echo '/htdocs/core/' >>"${target}/.gitignore"
  echo '/htdocs/modules/contrib/' >>"${target}/.gitignore"
  echo '/htdocs/themes/contrib/' >>"${target}/.gitignore"
}

if validate; then
  synchronizeDir
  update_gitignore
else
  echo "Error"
  print_help
fi

#    .--.
#   |o_o |
#   |:_/ |
#  //   \ \
# (|     | )
#/'\_   _/`\
#\___)=(___/
#+-------------------------------------------+
#| Php avec setting des proxys de la maison  |
#| et du certificat self-signed.             |
#| Installation de composer et nise à jour   |
#| la configuration de php.                  |
#+-------------------------------------------+
FROM wodby/drupal-php:8.3-dev-4.65.5
# Variables
ARG certificat_autosign='/etc/ssl/certs/edg.crt'
ARG ssl_dir='/etc/ssl/certs'
# Copie du certificat self-signed.
COPY 'ge-app.b64' ${certificat_autosign}
# Configuration de composer
RUN composer config --global cafile ${certificat_autosign}
RUN composer config --global capath ${ssl_dir}
# Configuration de php.
RUN echo '' >>/usr/local/etc/php/conf.d/dbx-php.ini
RUN echo 'curl.cainfo="'${certificat_autosign}'"' >>/usr/local/etc/php/conf.d/dbx-php.ini
RUN echo 'openssl.cafile="'${certificat_autosign}'"' >>/usr/local/etc/php/conf.d/dbx-php.ini
RUN echo 'openssl.capath="'${ssl_dir}'"' >>/usr/local/etc/php/conf.d/dbx-php.ini

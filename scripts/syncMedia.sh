#    .--.
#   |o_o |
#   |:_/ |
#  //   \ \
# (|     | )
#/'\_   _/`\
#\___)=(___/
#+-------------------------------------------+
#| Synchronize images and pdf files from     |
#| server media to local media.              |
#| @author DBX <didier.beux@etat.ge.ch>      |
#| @since mer 17 mai 2023 10:26:33           |
#+-------------------------------------------+
server="$1"
user="$2"


function printHelp() {
   echo " syncMedia server user"
   echo " server : The server name ex. [server.domainname]"
   echo " user   : The user name ex. [username]@server.domainname"
}

function validate(){
   if [ "${server}" == "" ] || [ "${user}" == "" ]; then
      echo "Warning!"
      echo "One or more parameter is missing."
      printHelp 
      return 1
   fi
   return 0
}

function synchronize() {
   rsync -av\
              --exclude 'media'\
              --exclude 'site_pilote'\
              --exclude 'd7'\
	      --exclude 'site_pilote'\
	      --exclude 'php'\
              --exclude 'js'\
              --exclude 'css'\
              --exclude '*.zip'\
              --exclude '*.doc'\
              --exclude '*.docx'\
              --exclude '*.odt'\
              --exclude '*.xls'\
              --exclude '*.xlsx'\
              --exclude '*.xlsm'\
              --exclude '*.php'\
              --exclude '*.vtt'\
              --exclude '*.css'\
              --exclude '*.pptx'\
              --exclude '*.ppt'\
              --exclude '*.pdf'\
              --exclude '*.access'\
              --delete ${user}@${server}:~/media/ ../media/
}

# main
if validate; then
   synchronize
fi

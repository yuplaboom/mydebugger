#!/bin/bash
# Un crontab tourne pour exécuter ce fichier
# */1 9-18 * * * /Users/amelie/www/lesgrappes/lesgrapento/mydebugger/start_server.sh
# Vérifie si un processus écoute sur le port 9090
PID=$(/usr/sbin/lsof -t -i :9090)

# Si un processus existe, le tuer
if [ -n "$PID" ]; then
  kill -9 $PID
fi

SCRIPTPATH="$( cd "$(dirname "$0")" ; pwd -P )"

# Démarrer le serveur Python
cd "$SCRIPTPATH"
source .env
nohup $PYTHON_PATH cors_server.py >> server.log 2>&1 &

# Rendre le script executable
# chmod +x /Users/amelie/www/lesgrappes/lesgrapento/mydebugger/start_server.sh
# clean des mails
# echo 'd *' | mail -N
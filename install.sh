#!/bin/bash
# Fonction pour installer pip si manquant
install_pip() {
    echo "pip not found. Attempting to install pip..."

    # Vérifier le système d'exploitation
    if command -v apt-get &> /dev/null; then
        # Système basé sur Debian/Ubuntu
        sudo apt-get update
        sudo apt-get install -y python3-pip
    elif command -v yum &> /dev/null; then
        # Système basé sur RHEL/CentOS/Fedora
        sudo yum install -y python3-pip
    elif command -v brew &> /dev/null; then
        # macOS avec Homebrew
        brew install python3
    else
        # Système non reconnu
        echo "Unsupported OS. Please install pip manually."
        exit 1
    fi
}

# Vérifier si python3 est installé
if ! command -v python3 &> /dev/null; then
    echo "Python3 not found. Please install Python3."
    exit 1
fi

# Vérifier si pip est installé
if ! command -v pip3 &> /dev/null; then
    install_pip
fi

# Mettre à jour pip et installer dotenv
python3 -m pip install --upgrade pip
python3 -m pip install python-dotenv

echo "Installation Python terminée."

# Copier .env.dist vers .env si .env n'existe pas
if [ ! -f .env ]; then
    if [ -f .env.dist ]; then
        cp .env.dist .env
        echo ".env file has been created from .env.dist."
    else
        echo ".env.dist file not found. Please create it or ensure it exists."
        exit 1
    fi
else
    echo ".env file already exists."
fi

## récupérer le chemin du script
SCRIPTPATH="$( cd "$(dirname "$0")" ; pwd -P )"

# Ajouter les tâches cron
if ! crontab -l | grep -q "clean_mail.sh"; then
    (crontab -l 2>/dev/null; echo "*/10 9-18 * * * $SCRIPTPATH/clean_mail.sh") | crontab -
    echo "Cron jobs clean mails added."
else
    echo "Cron jobs clean mails already exists."
fi

if ! crontab -l | grep -q "start_server.sh"; then
    (crontab -l 2>/dev/null; echo "*/1 9-18 * * * $SCRIPTPATH/start_server.sh") | crontab -
    echo "Cron jobs start server added."
else
    echo "Cron jobs start server already exists."
fi


# Ajouter les domaines dans le fichier hosts
if ! grep -q "www.logs.localdev" /etc/hosts; then
    echo "127.0.0.1       www.logs.localdev" | sudo tee -a /etc/hosts
    echo "Domain added to /etc/hosts."
else
    echo "Domain already exists in /etc/hosts."
fi

#  Créer server.log
: > server.log || touch server.log


# Lancer le server
$SCRIPTPATH/start_server.sh
echo "Server started."
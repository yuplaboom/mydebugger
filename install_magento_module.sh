#!/bin/bash
source .env

ln -s $(pwd)/Magento/Debugger "$MAGENTO_FOLDER/app/code/local/Debugger"
ln -s $(pwd)/Magento/Debugger_Logs.xml "$MAGENTO_FOLDER/app/etc/modules/"

# ajoute dans le fichier ~/.gitignore de l'utilisateur
echo "app/code/local/Debugger" >> ~/.gitignore
echo "app/etc/modules/Debugger_Logs.xml" >> ~/.gitignore
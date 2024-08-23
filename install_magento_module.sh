#!/bin/bash
source .env


# Copier les fichiers au lieu de créer des liens symboliques
cp -r $(pwd)/Magento/Debugger "$MAGENTO_FOLDER/app/code/local/Debugger"
cp $(pwd)/Magento/Debugger_Logs.xml "$MAGENTO_FOLDER/app/etc/modules/"

# ajoute dans le fichier ~/.gitignore de l'utilisateur si elles n'existent pas
grep -qxF 'app/code/local/Debugger' ~/.gitignore || echo 'app/code/local/Debugger' >> ~/.gitignore
grep -qxF 'app/etc/modules/Debugger_Logs.xml' ~/.gitignore || echo 'app/etc/modules/Debugger_Logs.xml' >> ~/.gitignore
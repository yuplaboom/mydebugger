#!/bin/bash
source .env

CURRENT_FOLDER=$(pwd)
# copier le Root/Configs/.gitignore dans le projet Root (utiliser .env variable ROOT_FOLDER/Configs/.gitignore)
cp $(pwd)/Root/Configs/.gitignore "$ROOT_FOLDER/Configs/.gitignore"
# Ajouter une ligne "Configs/.gitignore" dans .git/info/exclude du projet ROOT (ROOT_FOLDER) si pas existante
grep -qxF 'Configs/.gitignore' "$ROOT_FOLDER/.git/info/exclude" || echo 'Configs/.gitignore' >> "$ROOT_FOLDER/.git/info/exclude"
cd $ROOT_FOLDER && git rm --cached "$ROOT_FOLDER/Configs/.gitignore"
cd $CURRENT_FOLDER
# Copier DevTools dans ROOT_FOLDER/Modules
cp -r $(pwd)/Root/DevTools "$ROOT_FOLDER/Modules"
# Copier Root/Vendor dans ROOT_FOLDER/Vendor
cp $(pwd)/Root/Vendor/lesgrappes/rootlib/Phalcon/Application/Loader.php $ROOT_FOLDER/Vendor/lesgrappes/rootlib/Library/Root/Phalcon/Application/Loader.php
# Details
Ce projet permet de voir les logs de l'application en temps réel. Il utilise un serveur python pour lire les logs et les afficher sur une page web.

# Usage
## 1/ Install
```bash
cp .env.dist .env
# Edit file with your configuration
chmod +x install.sh
chmod +x install_magento_module.sh
chmod +x install_root_module.sh
sh install_magento_module.sh
sh install_root_module.sh
# Launch some root page and edit .env with good ROOT_LOG_CUSTOM_KEY
sh install.sh
```

## 2/ Voir les logs
Aller à l'url pour voir les logs : http://www.logs.localdev:9090/log
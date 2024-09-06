import ENV from './env.js';
import Magento from './magento.js';
import Root from './root.js';
import CONFIG from './config.js';

window.getEnv = function(key) {
    return ENV[key];
}

document.addEventListener('DOMContentLoaded', function() {
    new Magento(CONFIG.magento, document.getElementById('magento-logs'));
    new Root(CONFIG.root, document.getElementById('root-logs'));
})
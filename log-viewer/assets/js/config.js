const CONFIG = {
    "magento" : {
        "avoidUrls": [
            "https://www.lesgrappes.localdev/notification/index/hasNew"
        ],
        "clearLogUrl": "http://www.logs.localdev:9090/clear-magento-log",
        "logUrl": "http://www.logs.localdev:9090/magento.log"
    },
    "root": {
        "avoidUrls": [],
        "clearLogUrl": "http://www.logs.localdev:9090/clear-root-log",
        "logUrl": "http://www.logs.localdev:9090/root.log"
    }
};

export default CONFIG;
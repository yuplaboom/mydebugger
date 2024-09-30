import Log from "./log.js";

class Magento extends Log {
    keys = [];
    constructor(config, container) {
        super(config, container);
    }

    fetchLog() {
        // check si collapse ouvert , si oui, on ne fetch pas
        const collapse = this.container.querySelector('.collapse.show');
        if (collapse) {
            return;
        }
        const baseLog = {
            'id': '',
            'timestamp': null,
            'errors': [],
            'warnings': [],
            'notices': [],
            'formattedTimestamp': null,
            'statusCode': '',
            'apiStatusCode': '',
            'url': '',
            'formattedUrl': '',
            'controller': '',
            'userId': '',
            'userEmail': '',
            'adminId': '',
            'adminEmail': '',
            'formattedController': '',
            'templatesString': [],
            'getParams': '',
            'postParams': '',
            'responseData': '',
            'requestMethod': '',
        };
        fetch(this.config.logUrl)
            .then(response => response.text())
            .then(data => {
                if (this.currentLogKey === data.length) {
                    return;
                }
                this.currentLogKey = data.length;
                const groupedByTimestamp = [];
                const lines = data.split('\n');
                let currentLog = JSON.parse(JSON.stringify(baseLog));

                for (let i = 0; i < lines.length; i++) {
                    let line = lines[i];
                    const timestampMatch = line.match(/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})/);
                    let formattedTimestamp = '';
                    if (timestampMatch) {
                        if (currentLog.timestamp !== null) {
                            currentLog.id = md5(currentLog.timestamp + currentLog.url + currentLog.controller);
                            groupedByTimestamp.push(currentLog);
                            currentLog = JSON.parse(JSON.stringify(baseLog));
                        }
                        const timestamp = new Date(timestampMatch[0]);

                        const datePart = timestamp.toISOString().split('T')[0];
                        const timePart = timestamp.toTimeString().split(' ')[0];
                        formattedTimestamp = `${datePart} - ${timePart}`;
                        currentLog.formattedTimestamp = formattedTimestamp;
                        currentLog.timestamp = timestamp;
                        continue;
                    }

                    const statusCode = line.match(/HTTP Response Status:\s*(\d+)/);
                    if (statusCode) {
                        currentLog.statusCode = this.formatStatusCode(statusCode[1]);
                        continue;
                    }
                    const urlMatch = line.match(/URL:\s*([\w_:\/\/\.\-]+)/);
                    if (urlMatch) {
                        currentLog.url = urlMatch[1];
                        continue;
                    }
                    const processTime = line.match(/Process Time:\s*(.*)/);
                    if (processTime) {
                        currentLog.processTime = processTime[1];
                        continue;
                    }
                    const controllerMatch = line.match(/Controller:\s*([\w_]+::[\w_]+)/);
                    if (controllerMatch) {
                        currentLog.controller = controllerMatch[1];
                        continue;
                    }
                    const userIDMatch = line.match(/Customer ID:\s*(.*)/);
                    if (userIDMatch) {
                        currentLog.userId = userIDMatch[1];
                        continue;
                    }
                    const requestMethodMatch = line.match(/Request Method:\s*(.*)/);
                    if (requestMethodMatch) {
                        currentLog.requestMethod = this.formatRequestMethod(requestMethodMatch[1]);
                        continue;
                    }
                    const userEmailMatch = line.match(/Customer Email:\s*(.*)/);
                    if (userEmailMatch) {
                        currentLog.userEmail = userEmailMatch[1];
                        continue;
                    }

                    const getParamsMatch= line.match(/GET Params:\s*(.*)/);
                    if (getParamsMatch) {
                        const jsonString = getParamsMatch[1];
                        const jsonData = JSON.parse(jsonString);
                        const formattedJson = this.formatJsonToHtml(jsonData);
                        currentLog.getParams = formattedJson;
                        continue;
                    }
                    const postParamsMatch= line.match(/POST Params:\s*(.*)/);
                    if (postParamsMatch) {
                        const jsonString = postParamsMatch[1];
                        const jsonData = JSON.parse(jsonString);

                        const formattedJson = this.formatJsonToHtml(jsonData);
                        currentLog.postParams = formattedJson;
                        continue;
                    }

                    const responseData = line.match(/Response Data:\s*(.*)/);
                    if (responseData) {
                        const jsonString = responseData[1];
                        const jsonData = JSON.parse(jsonString);
                        if (jsonData.status) {
                            currentLog.apiStatusCode = this.formatStatusCode(jsonData.status);
                        }

                        let result = this.replaceHTMLInJSON(jsonData);
                        const formattedJson = this.formatJsonToHtml(result);
                        currentLog.responseData = formattedJson;
                        continue;
                    }

                    const errorMatch = line.match(/Error:\s*(.*)/);
                    if (errorMatch) {
                        if (currentLog.errors.includes(errorMatch[1])) {
                            continue;
                        }
                        currentLog.errors.push(errorMatch[1]);
                        continue;
                    }
                    const noticeMatch = line.match(/Notice:\s*(.*)/);
                    if (noticeMatch) {
                        if (currentLog.notices.includes(noticeMatch[1])) {
                            continue;
                        }
                        currentLog.notices.push(noticeMatch[1]);
                        continue;
                    }
                    const warningMatch = line.match(/Warning:\s*(.*)/);
                    if (warningMatch) {
                        if (currentLog.warnings.includes(warningMatch[1])) {
                            continue;
                        }
                        currentLog.warnings.push(warningMatch[1]);
                        continue;
                    }


                    currentLog.templatesString += "\n";
                    currentLog.templatesString += line
                        .replace(/Templates:\s*/, '<span class="h2">Templates:</span>\n')
                        .replace(/(frontend\/[\w\/\.]+\.phtml)/g, `<a class="path toCopy" href="#" data-path="$1">$1</a>`)
                        .replace(/(adminhtml\/[\w\/\.]+\.phtml)/g, `<a class="path toCopy" href="#" data-path="$1">$1</a>`)
                        .replace(/(frontend\/[\w\/\.]+\.php)/g, `<a class="path toCopy" href="#" data-path="$1">$1</a>`)
                        .replace(/(adminhtml\/[\w\/\.]+\.php)/g, `<a class="path toCopy" href="#" data-path="$1">$1</a>`)
                        .replace(/- Block\s*([\w_]+)/, (match, blockName) => {
                            return `Block: <a class="block toCopy" href="#" data-path="${blockName}">${blockName}</a>`;
                        });
                }
                currentLog.id = md5(currentLog.timestamp + currentLog.url + currentLog.controller);
                groupedByTimestamp.push(currentLog);
                function createEl(string) {
                    let template = document.createElement('template');
                    template.innerHTML = string;
                    return template.content.firstChild;
                }
                for (let i = 0; i < groupedByTimestamp.length; i++) {
                    const log = groupedByTimestamp[i];
                    let key = log.id;
                    if (this.keys[key]) {
                        continue;
                    }
                    this.keys[key] = true;
                    if (this.config.avoidUrls.includes(log.url)) {
                        continue;
                    }
                    let newElement = document.createElement('div');

                    let strProcessTime = "";
                    if (log.processTime) {
                        let strProcess = log.processTime + 'ms';
                        let strClass = "text-info";
                        if (log.processTime > 5000) {
                            strClass = "text-danger";
                            strProcess = log.processTime/1000 + 's';
                        } else if (log.processTime > 2000) {
                            strClass = "text-warning";
                            strProcess = log.processTime/1000 + 's';
                        }
                        strProcessTime = ' - <span class="'+strClass+'">'+strProcess+'</span>';
                    }
                    newElement.append(createEl(`<div><a class="logLine" data-bs-toggle="collapse" href="#panel-${i}">${log.formattedTimestamp}${strProcessTime} - ${log.url} - ${log.requestMethod} - ${log.statusCode}${log.apiStatusCode ? ' - '+log.apiStatusCode : ''}</a></div>`));
                    let collapse = createEl(`<div class="collapse" id="panel-${i}"></div>`);
                    collapse.append(createEl(`<div class="logLine"><span class="h2">Controller:</span> <a class="path toCopy controller" href="#" data-path="${log.controller}">${log.controller}</a></div>`));
                    if (log.errors.length) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2 text-danger">Errors:</span></div>`));
                    }
                    log.errors.forEach(error => {
                        collapse.append(createEl(`<div class="logLine text-danger">${error}</div>`));
                    });
                    if (log.warnings.length) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2 text-warning">Warnings:</span></div>`));
                    }
                    log.warnings.forEach(warning => {
                        collapse.append(createEl(`<div class="logLine text-warning">${warning}</div>`));
                    });
                    collapse.append(createEl(`<div class="logLine"><span class="h2">Url:</span> <a class="path toCopy" href="#" data-path="${log.url}">${log.url}</a></div>`));
                    if (log.getParams.length > 0) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2">GET Params:</span><br>${log.getParams}</div>`));
                    }
                    if (log.postParams.length > 0) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2">POST Params:</span><br>${log.postParams}</div>`));
                    }
                    collapse.append(createEl(`<div class="logLine"><span class="h2">User ID:</span> ${log.userId}</div>`));
                    collapse.append(createEl(`<div class="logLine"><span class="h2">User Email:</span> ${log.userEmail}</div>`));
                    if (log.notices.length) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2 text-info">Notices:</span></div>`));
                    }
                    log.notices.forEach(notice => {
                        collapse.append(createEl(`<div class="logLine text-info">${notice}</div>`));
                    });
                    if (log.templatesString.length > 0) {
                        collapse.append(createEl(`<div class="logLine">${log.templatesString}</div>`));
                    }
                    if (log.responseData.length > 0) {
                        collapse.append(createEl(`<div class="logLine"><span class="h2">Response Data:</span><br>${log.responseData}</div>`, true));
                    }
                    newElement.append(collapse);
                    this.container.querySelector('.logContent').prepend(newElement);
                }
                if (Object.keys(this.keys).length) {
                    this.container.querySelector('.loading')?.remove();
                    this.container.querySelector('.noLog')?.remove();
                } else {
                    this.container.querySelector('.logContent').innerHTML = '<span class="noLog">No log found</span';
                }
            })
            .catch(error => console.error('Failed to fetch log file:', error));
    }
    replaceHTMLInJSON(obj) {
        const isHTMLString = (str) => {
            const regex = /<([a-z][\w-]*)(?:[^>]*?)>(.*?)<\/\1>|<([a-z][\w-]*)\s*\/?>/i;
            return typeof str === 'string' && regex.test(str);
        };

        const traverseAndReplace = (data) => {
            if (Array.isArray(data)) {
                // Si c'est un tableau, on parcourt chaque élément
                return data.map(traverseAndReplace);
            } else if (typeof data === 'object' && data !== null) {
                // Si c'est un objet, on parcourt chaque clé
                for (let key in data) {
                    if (data.hasOwnProperty(key)) {
                        data[key] = traverseAndReplace(data[key]);
                    }
                }
                return data;
            } else if (isHTMLString(data)) {
                // Si c'est une chaîne HTML, on la remplace par "some HTML content"
                return "some HTML content";
            } else {
                // Sinon, on retourne la donnée inchangée
                return data;
            }
        };

        return traverseAndReplace(obj);
    }
}

export default Magento;


import Log from "./log.js";

class Root extends Log {
    constructor(config, container) {
        super(config, container);
    }

    fetchLog() {
        // check si collapse ouvert , si oui, on ne fetch pas
        const collapse = this.container.querySelector('.collapse.show');
        if (collapse) {
            return;
        }
        fetch(this.config.logUrl)
            .then(response => response.text())
            .then(data => {
                const groupedByTimestamp = [];
                const lines = data.split('\n');
                const baseLog = {
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
                try {
                    let formattedLog = '';
                    // inverse l'ordre des lignes
                    lines.reverse();
                    for (let i = 0; i < lines.length; i++) {
                        let line = lines[i];
                        if (line === '') {
                            continue;
                        }
                        // Regex pour extraire la date et le JSON stringifié
                        const regex = /\[Debug\] (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+\d{2}:\d{2}\.\d+) \| lgroot_front2 \| WEB \| (\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}) \| (.*)/;
                        const match = line.match(regex);
                        if (match) {
                            const date = match[1];
                            const timestampMatch = date.match(/(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2})/);

                            const timestamp = new Date(timestampMatch[0]);

                            const datePart = timestamp.toISOString().split('T')[0];
                            const timePart = timestamp.toTimeString().split(' ')[0];
                            const formattedTimestamp = `${datePart} - ${timePart}`;
                            const ip = match[2];
                            const jsonString = match[3];
                            const log = JSON.parse(JSON.parse(jsonString));
                            if (this.config.avoidUrls.includes(log.request.url)) {
                                continue;
                            }

                            formattedLog += `<div><a class="logLine" data-bs-toggle="collapse" href="#root-panel-${i}">${formattedTimestamp} - ${log.request.url.split('?')[0]} - ${this.formatRequestMethod(log.request.method)} - ${this.formatStatusCode(log.response.status)} ${log.apiStatusCode ? '- '+this.formatStatusCode(log.apiStatusCode) : ''}</a></div>`;
                            formattedLog += `<div class="collapse" id="root-panel-${i}">`;
                            formattedLog += `<div class="logLine"><span class="h2">Controller:</span> <a class="path toCopy controller" href="#" data-path="${log.controllerClass}::${log.actionName}">${log.controllerClass}::${log.actionName}</a></div>`;

                            formattedLog += `<div class="logLine"><span class="h2">Url:</span> <a class="path toCopy" href="#" data-path="${log.request.url}">${log.request.url}</a></div>`;
                            if (log.params && Object.keys(log.params).length) {
                                formattedLog += `<div class="logLine"><span class="h2">Params :</span><br>${this.formatJsonToHtml(log.params)}</div>`;
                            }
                            delete (log.request.GET['_url']);
                            if (log.request.GET && Object.keys(log.request.GET).length) {
                                formattedLog += `<div class="logLine"><span class="h2 text-success">GET Params:</span><br>${this.formatJsonToHtml(log.request.GET)}</div>`;
                            }
                            console.log(log);
                            if (log.request.POST && Object.keys(log.request.POST).length) {
                                formattedLog += `<div class="logLine"><span class="h2 text-warning">POST Params:</span><br>${this.formatJsonToHtml(log.request.POST)}</div>`;
                            }
                            if (log.request.body) {
                                formattedLog += `<div class="logLine"><span class="h2 text-warning">Request Data:</span><br>${this.formatJsonToHtml(JSON.parse(log.request.body))}</div>`;
                            }
                            if (log.errors && Object.keys(log.errors).length) {
                                const errors = log.errors;
                                if (errors['error']) {
                                    formattedLog += `<div class="logLine"><span class="h2 text-danger">Errors:</span></div>`;
                                    errors['error'].forEach(error => {
                                        formattedLog += `<div class="logLine text-danger">${error.file} - line ${error.line} : ${error.message}</div>`;
                                    });
                                }
                                if (errors['warning']) {
                                    formattedLog += `<div class="logLine"><span class="h2 text-warning">Warnings:</span></div>`;
                                    errors['warning'].forEach(error => {
                                        formattedLog += `<div class="logLine text-warning">${error.file} - line ${error.line} : ${error.message}</div>`;
                                    });
                                }

                                // if (errors['info']) {
                                //     formattedLog += `<div class="logLine"><span class="h2 text-info">Infos:</span></div>`;
                                //     errors['info'].forEach(error => {
                                //         formattedLog += `<div class="logLine text-info">${error.file} - line ${error.line} : ${error.message}</div>`;
                                //     });
                                //
                                // }
                            }
                            formattedLog += `<div class="logLine"><span class="h2">User: </span><br>`
                            formattedLog += `\t<b>id:</b> ${log.user?.id ?? null}<br>`;
                            formattedLog += `\t<b>email:</b> ${log.user?.email ?? null}<br>`;
                            formattedLog += `\t<b>Type:</b> ${log.user?.userType ?? null }<br>`;
                            formattedLog += `\t<b>supplierCustomerGroup:</b> ${log.user?.supplierCustomerGroup ?? null}<br>`;
                            formattedLog += `\t<b>secureToken:</b> ${log.user?.token ?? null}<br>`;
                            formattedLog += `\t<b>acl</b> ${log.user?.acl ?? null}<br>`;
                            formattedLog += `</div><br>`;
                            if (log.response.data) {
                                formattedLog += `<div class="logLine"><span class="h2">Response Data:</span><br>${this.formatJsonToHtml(JSON.parse(log.response.data))}</div>`;
                            }
                            formattedLog += `</div>`;
                            this.container.querySelector('.logContent').innerHTML = formattedLog;

                        } else {
                            throw new Error('Log format is invalid');
                        }
                    }
                    if (formattedLog === '') {
                        this.container.querySelector('.logContent').innerHTML = 'No log found';
                    }
                } catch (error) {
                    console.error('Error formatting log:', error);
                    this.container.querySelector('.logContent').innerHTML = `<div class="logLine text-danger">Error formatting log: ${error.message}</div>`;
                }
            })
            .catch(error => console.error('Failed to fetch log file:', error));
    }
}

export default Root;


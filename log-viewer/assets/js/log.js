class Log {
    container;
    config;
    constructor(config, container) {
        this.config = config;
        this.container = container;
        this.container.querySelector('.clearButton').addEventListener('click', this.clearLog.bind(this));
        this.container.querySelector('.logContent').addEventListener('click', (event) => {
            if (event.target && event.target.matches('.toCopy')) {
                event.preventDefault();
                const path = event.target.getAttribute('data-path');
                if (!navigator.clipboard) {
                    let textArea = document.createElement("textarea");
                    textArea.value = path;
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();
                    textArea.style.position = "absolute";
                    textArea.style.left = "-999999px";
                    document.execCommand('copy');
                    this.showNotification('copied to clipboard: ' + path);
                } else {
                    navigator.clipboard.writeText(path)
                        .then(() => this.showNotification('copied to clipboard: ' + path))
                        .catch(err => console.error('Failed to copy controller:', err));
                }
            }
        });
        this.container.querySelector('.log-expand').addEventListener('click', (event) => {
            event.preventDefault();
            document.querySelector('.log-types').classList.add('active-expanded');
            document.querySelectorAll('.log-types__item').forEach(item => {
                item.classList.remove('expanded');
                if (item !== this.container) {
                    return;
                }
                item.classList.add('expanded');
                item.querySelector('.log-contract').classList.remove('d-none');
                item.querySelector('.log-expand').classList.add('d-none');
            })
        });
        this.container.querySelector('.log-contract').addEventListener('click', (event) => {
            event.preventDefault();
            document.querySelector('.log-types').classList.remove('active-expanded');
            document.querySelectorAll('.log-types__item').forEach(item => {
                item.classList.remove('expanded');
                item.querySelector('.log-contract').classList.add('d-none');
                item.querySelector('.log-expand').classList.remove('d-none');
            })
        });
        this.fetchLog();
        let interval = setInterval(this.fetchLog.bind(this), 5000);
    }

    formatRequestMethod(method) {
        if (method === 'GET') {
            return '<span class="text-success">GET</span>';
        }
        return `<span class="text-warning">${method}</span>`;
    }
    formatJsonToHtml(data) {
        return JSON.stringify(data, null, 2).replace(/(?:\\[rn])+/g, '<br>');
    }
    fetchLog() {
        fetch(this.config.logUrl)
            .then(response => response.text())
            .then(log => {
                this.container.querySelector('.logContent').innerHTML = log;
            })
            .catch(error => console.error('Failed to fetch log file:', error));
    }
    showNotification(message) {
        const notification = this.container.querySelector('.notification');
        notification.textContent = message;
        notification.style.display = 'block';
        setTimeout(() => {
            notification.style.display = 'none';
        }, 3000); // Hide after 3 seconds
    }

    clearLog() {
        fetch(this.config.clearLogUrl)
            .then(response => response.text())
            .then(message => {
                setTimeout(() => {
                    this.showNotification('file logs cleared');
                } );
            })
            .catch(error => console.error('Failed to clear log file:', error));
    }

    formatStatusCode(statusCode) {
        statusCode = statusCode.toString();
        if (statusCode.startsWith('2')) {
            return `<span class="text-success">${statusCode}</span>`;
        } else if (statusCode.startsWith('3')) {
            return `<span class="text-info">${statusCode}</span>`;
        } else if (statusCode.startsWith('4')) {
            return `<span class="text-danger">${statusCode}</span>`;
        } else if (statusCode.startsWith('5')) {
            return `<span class="text-danger">${statusCode}</span>`;
        }
        return statusCode;
    }
}

export default Log;
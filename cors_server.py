from http.server import BaseHTTPRequestHandler, HTTPServer
import os
import mimetypes
from datetime import datetime
import logging
import json
from dotenv import load_dotenv

load_dotenv()
MAGENTO_LOG_FILE_PATH = os.getenv('MAGENTO_LOG_FILE_PATH')
ROOT_LOG_DIRECTORIES = os.getenv('ROOT_LOG_DIRECTORIES')
ROOT_LOG_FORMAT_START = os.getenv('ROOT_LOG_FORMAT_START')
ROOT_LOG_CUSTOM_KEY = os.getenv('ROOT_LOG_CUSTOM_KEY')

current_file_path = os.path.abspath(__file__)
current_folder_path = os.path.dirname(current_file_path)
logging.basicConfig(filename=os.path.join(current_folder_path, 'server.log'),
                    level=logging.DEBUG,
                    format='%(asctime)s %(levelname)s: %(message)s',
                    datefmt='%Y-%m-%d %H:%M:%S')

class RequestHandler(BaseHTTPRequestHandler):
    def send_cors_headers(self):
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'GET')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')

    def do_GET(self):
        if self.path == '/magento.log':
            self.handle_magento_log_request()
            return
        if self.path == '/root.log':
            self.handle_root_log_request()
            return
        elif self.path == '/clear-magento-log':
            self.handle_clear_magento_log_request()
            return
        elif self.path == '/clear-root-log':
            self.handle_clear_root_log_request()
            return
        elif self.path == '/log':
            self.handle_log_view()
            return
        elif self.path == '/assets/js/env.js':
            self.handle_env_js_request()
            return
        else:
#             logging.info(f"Method: {self.command}")
#             logging.info(f"Path: {self.path}")
#             logging.info(f"Headers:\n{self.headers}")
            self.serve_static_file(self.path)
            return

    def handle_log_view(self):
        try:
            with open('log-viewer/index.html', 'r') as file:
                html_content = file.read()
            self.send_response(200)
            self.send_header('Content-type', 'text/html')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(html_content.encode())
        except IOError:
            self.send_response(500)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(b'Internal Server Error: Could not read log.html')

    def handle_magento_log_request(self):
        try:
            with open(MAGENTO_LOG_FILE_PATH, 'r') as file:
                content = file.read()

            self.send_response(200)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(content.encode())
        except Exception as e:
            self.send_response(500)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(f'Error: {e}'.encode())

    def handle_root_log_request(self):
        log_files = get_root_log_files(ROOT_LOG_DIRECTORIES)

        try:
            combined_logs = ""
            for log_file in log_files:
                 with open(log_file, 'r') as file:
                    combined_logs += file.read()
                    combined_logs += "\n"
            self.send_response(200)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(combined_logs.encode())
        except Exception as e:
            self.send_response(500)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(f'Error: {e}'.encode())

    def handle_clear_magento_log_request(self):
        open(MAGENTO_LOG_FILE_PATH, 'w').close()
        self.send_response(200)
        self.send_header('Content-type', 'text/plain')
        self.send_cors_headers()
        self.end_headers()
        self.wfile.write(b'Log cleared')

    def handle_clear_root_log_request(self):
        log_files = get_root_log_files(ROOT_LOG_DIRECTORIES)
        for log_file in log_files:
            open(log_file, 'w').close()

        self.send_response(200)
        self.send_header('Content-type', 'text/plain')
        self.send_cors_headers()
        self.end_headers()
        self.wfile.write(b'Log cleared')

    def handle_env_js_request(self):
        try:
            # Liste des variables à exposer dans le fichier JS
            exposed = ['ROOT_LOG_CUSTOM_KEY']
            exposedVarArr = {key: os.getenv(key) for key in exposed}

            # Générer le contenu JS
            js_content = f"const ENV = {json.dumps(exposedVarArr)};"
            js_content += "\n"
            js_content += "export default ENV;"
            # Envoyer la réponse HTTP
            self.send_response(200)
            self.send_header('Content-type', 'application/javascript')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(js_content.encode())
        except Exception as e:
            self.send_response(500)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(f'Error: {e}'.encode())
            logging.error(f'Error: {e}')

    def serve_static_file(self, path):
        try:
            base_path = 'log-viewer'
            # Remove the leading slash to get the relative path
            relative_path = path.lstrip('/')
            # Construct the full path to the requested file
            file_path = os.path.join(base_path, relative_path)
            # Open the requested file
            with open(file_path, 'rb') as file:
               content = file.read()
            # Guess the content type based on the file extension
            content_type, _ = mimetypes.guess_type(file_path)
            if content_type is None:
               content_type = 'application/octet-stream'

            self.send_response(200)
            self.send_header('Content-type', content_type)
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(content)
        except IOError:
            self.send_response(404)
            self.send_header('Content-type', 'text/plain')
            self.send_cors_headers()
            self.end_headers()
            self.wfile.write(b'Not Found')

def get_root_log_files(directory):
    files = [f for f in os.listdir(directory) if os.path.isfile(os.path.join(directory, f))]
    log_files = [f for f in files if f.startswith(ROOT_LOG_FORMAT_START) and f.endswith(".log")]
    log_files.sort(key=lambda x: datetime.strptime(x.split('.')[1], '%Y-%m-%d'))
    files = []
    for log_file in log_files:
        files.append(os.path.join(directory, log_file))

    return files


def run(server_class=HTTPServer, handler_class=RequestHandler, port=9090):
    server_address = ('', port)
    httpd = server_class(server_address, handler_class)
    logging.debug(f'Starting httpd server on port {port}...')
    httpd.serve_forever()


if __name__ == '__main__':
    run(port=9090)

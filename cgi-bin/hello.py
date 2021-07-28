import os,json, platform

html = '''
    <html>
        <head></head>
        <body> Hi, This is python. </body>
    </html>
'''



print('HTTP/1.1 200 OK\r')
print('Content-Type: text/html\r')
print('Server: Python/%s\r' % platform.python_version())
print('\r')
print(html + '\r')
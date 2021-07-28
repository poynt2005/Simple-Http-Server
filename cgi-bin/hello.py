import os,json

html = '''
    <html>
        <head></head>
        <body> Hi, This is python. </body>
    </html>
'''



print('HTTP/1.1 200 OK\r')
print('Content-Type: text/html\r')
print('\r')
print(html + '\r')
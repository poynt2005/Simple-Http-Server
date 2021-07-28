import os,json,platform

html = ''
if os.environ['REQUEST_METHOD'] == 'GET':
    html = '''
        <html>
            <head>
                <title> Python Post CGI </title>
                <style>
                    input { display: block; }
                </style>
            </head>
            <body>
                <div> Python Post CGI </div>
                <form action="/cgi-bin/post.py" method="POST">
                    <input type="text" name="username" placeholder="username">
                    <input type="text" name="myname" placeholder="myname">
                    <input type="submit">
                </form>
            </body>
        </html>
    '''
elif os.environ['REQUEST_METHOD'] == 'POST':
    import urllib.parse
    body = urllib.parse.unquote(os.environ['BODY'])
    
    bodyDict = {}
    
    for i in body.split('&'):
        t = i.split('=')
        bodyDict[t[0]] = t[1]

    html = '''
        <html>
            <head>
                <title> Python Post CGI </title>
            </head>
            <body>
                <div> Python Post CGI </div>
                <div> Username: %s </div>
                <div> YourName: %s </div>
            </body>
        </html>
    ''' % (bodyDict['username'], bodyDict['myname'])
else:
    html = '''
        <html>
            <head>
                <title> Python Post CGI </title>
            </head>
            <body>
                <div> Python Post CGI </div>
                <div> The request method is not a vaild method </div>
            </body>
        </html>
    '''


print('HTTP/1.1 200 OK\r')
print('Content-Type: text/html\r')
print('Server: Python/%s\r' % platform.python_version())
print('\r')
print(html + '\r')
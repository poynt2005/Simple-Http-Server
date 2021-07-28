$html = @"
	<html>
        <head></head>
        <body> Hi, This is PowerShell </body>
    </html>
"@

Write-Host "HTTP/1.1 200 OK`r"
Write-Host "Content-Type: text/html`r"
Write-Host "`r"
Write-Host "$html`r"
$html = ""

Add-Type -AssemblyName System.Web

if($Env:REQUEST_METHOD -eq "GET"){

$html = @"
	<html>
		<head>
			<title> Powershell Post CGI </title>
			<style>
				input { display: block; }
			</style>
		</head>
		<body>
			<div> Powershell Post CGI </div>
			<form action="/cgi-bin/post.ps1" method="POST">
				<input type="text" name="username" placeholder="username">
				<input type="text" name="myname" placeholder="myname">
				<input type="submit">
			</form>
		</body>
	</html>
"@
	

}
elseif($Env:REQUEST_METHOD -eq "POST"){

$urldecode = [System.Web.HttpUtility]::UrlDecode($Env:BODY)
$bodyDict = @{}

Foreach($str in $urldecode.Split("&")){
	$t = $str.Split("=")
	$bodyDict[$t[0]] = $t[1]
}


$html = @"
	<html>
		<head>
			<title> Powershell Post CGI </title>
		</head>
		<body>
			<div> Powershell Post CGI </div>
			<div> Username: $($bodyDict.Get_Item("username")) </div>
			<div> YourName: $($bodyDict.Get_Item("myname")) </div>
		</body>
	</html>
"@


}
else {

$html = @"
	<html>
		<head>
			<title> Powershell Post CGI </title>
		</head>
		<body>
			<div> Powershell Post CGI </div>
			<div> The request method is not a vaild method </div>
		</body>
	</html>
"@

	
}


Write-Host "HTTP/1.1 200 OK`r"
Write-Host "Content-Type: text/html`r"
Write-Host "Server: PowerShell/$($host.Version.Major).$($host.Version.Minor).$($host.Version.Build).$($host.Version.Revision)`r"
Write-Host "`r"
Write-Host "$html`r"
<?php

class SocketServer {
    private $addr;
    private $port;


    private $socket;
    private $clients;

    public function __construct($address, $port){
        $this->addr = $address;
        $this->port = $port;

        $this->socket = socket_create(AF_INET,SOCK_STREAM,SOL_TCP);

        socket_bind($this->socket, $this->addr, $this->port);
        socket_listen($this->socket, SOMAXCONN);
        socket_set_nonblock($this->socket);

        set_error_handler(function($errno, $errstr, $errfile, $errline){
            if(preg_match("/unable to read from socket/", $errstr)){
                throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
            }
        }, E_WARNING);

        $this->clients = new \SplObjectStorage();
    }

    private function loop(){
        while(true){
            $client = socket_accept($this->socket);

            if($client){
                socket_set_nonblock($client);
                $this->clients->attach($client);
            }

            foreach($this->clients as $client){
                $buff = null;

                try {
                    $buff = socket_read($client, 4096);
                }
                catch(ErrorException $e){
                    $this->clients->detach($client);
                    socket_close($client);
                    continue;
                }

                if(strlen($buff)){
                    $variables = [];
                    $otherVars = [];
                    $buffSplit = explode("\r\n", $buff);

                    for($i=0; $i<count($buffSplit); $i++){
                        if($i==0){
                            $httpMethodStringSplit = explode(" ", $buffSplit[$i]);

                            $variables["REQUEST_METHOD"] = trim($httpMethodStringSplit[0]);
                            $variables["REQUEST_URI"] = trim($httpMethodStringSplit[1]);
                            $queryStr = explode("?", trim($httpMethodStringSplit[1]));

                            $variables["QUERY_STRING"] = isset($queryStr[1]) ? $queryStr[1] : "";
                            $variables["SERVER_PROTOCOL"] = trim($httpMethodStringSplit[2]);
                        }
                        else if($i == count($buffSplit) - 1){
                            $otherVars["BODY"] = trim($buffSplit[$i]);
                        }
                        else {
                            $headerStrSplit = explode(":", $buffSplit[$i]);
                            $headerName = strtolower(trim($headerStrSplit[0]));  

                            if($headerName == "accept"){
                                $variables["HTTP_ACCEPT"] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "accept-charset"){
                                $variables['HTTP_ACCEPT_CHARSET'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "accept-encoding"){
                                $variables['HTTP_ACCEPT_ENCODING'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "accept-language"){
                                $variables['HTTP_ACCEPT_LANGUAGE'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "connection"){
                                $variables['HTTP_CONNECTION'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "host"){
                                $variables['HTTP_HOST'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "referer"){
                                $variables['HTTP_REFERER'] = trim($headerStrSplit[1]);
                            }
                            else if($headerName == "user-agent"){
                                $variables['HTTP_USER_AGENT'] = trim($headerStrSplit[1]);
                            }
                            else if(strlen($headerName) != 0){
                                $otherVars[strtoupper($headerName)] = trim($headerStrSplit[1]);
                            }
                        }
                    }

                    $remoteAddr;
                    $remotePort;
                    socket_getpeername($client, $remoteAddr, $remotePort);
                    $variables["REMOTE_ADDR"] = $remoteAddr;
                    $variables["REMOTE_PORT"] = $remotePort;
                    $variables["SERVER_PORT"] = $this->port;
                    $variables["SERVER_ADDR"] = $this->addr;
					
					

                    $cgi_param = null;

                    if(file_exists(getcwd().DIRECTORY_SEPARATOR.'cgi.json')){
                        $fp = fopen(getcwd().DIRECTORY_SEPARATOR.'cgi.json', "r");
                        $txt = '';
                        while(!feof($fp)){
                            $txt .= fread($fp, 1024);
                        }
                        fclose($fp);
                        
                        $cgi_param = json_decode($txt, true);
                    }

                    if($cgi_param === null){
                        $cgi_param = [
                            "cgi_path" => "cgi-bin",
                            "cmd_map" => []
                        ];
                    }

                    $requestPathArr = explode("/", $variables["REQUEST_URI"]);
                    $cgiFileName;
                    if(strlen($requestPathArr[count($requestPathArr)-1]) == 0){
                        $cgiFileName = $requestPathArr[count($requestPathArr)-2];
                    }
                    else {
                        $cgiFileName = $requestPathArr[count($requestPathArr)-1];
                    }

                    $comparedPath = [];
                    $isCgiRequest = false;

                    for($i=1; $i<count($requestPathArr); $i++){
                        if($requestPathArr[$i] != $cgiFileName){
                            $comparedPath[] = $requestPathArr[$i];
                        }
                        else {
                            break;
                        }
                    }

                    if(join("/", $comparedPath) == $cgi_param["cgi_path"]){
                        $isCgiRequest = true;
                    }

                    if($isCgiRequest){
                        $cgiFile = explode("?", $cgiFileName)[0];
                        $cgiFileType = explode(".", $cgiFile)[1];
                        $cgiFilePath = getcwd().DIRECTORY_SEPARATOR.join(DIRECTORY_SEPARATOR, explode("/", $cgi_param["cgi_path"])).DIRECTORY_SEPARATOR.$cgiFile;
                        if(!file_exists($cgiFilePath) || is_dir($cgiFilePath)){
                            socket_write($client, join("\r\n", [
                                $variables["SERVER_PROTOCOL"].' '.'404 Not Found',
                                "Content-Type: text/plain",
                                "",
                                "404 CGI Program Not Found"
                            ]));

                            socket_close($client);
                            $this->clients->detach($client);
                            continue;
                        }

                        foreach($variables as $k => $v){
                            putenv("{$k}={$v}");
                        }
                        foreach($otherVars as $k => $v){
                            putenv("{$k}={$v}");
                        }

                        if(isset($cgi_param["cmd_map"][$cgiFileType])){
                            $rst = shell_exec(str_replace("%file%", "\"{$cgiFilePath}\"", $cgi_param["cmd_map"][$cgiFileType]));
                            socket_write($client, $rst);
                            socket_close($client);
                            $this->clients->detach($client);
                            continue;
                        }
                        else {
                            $rst = shell_exec($cgiFilePath);
                            socket_write($client, $rst);
                            socket_close($client);
                            $this->clients->detach($client);
                            continue;
                        }
                    }
                    else {
                        $filePath = getcwd().DIRECTORY_SEPARATOR.join(DIRECTORY_SEPARATOR, array_filter(explode("/", $variables["REQUEST_URI"]), fn($el) => strlen($el) != 0));

                        if(file_exists($filePath) && !is_dir($filePath)){
                            $fp = fopen($filePath, "r");

                            $contentType = mime_content_type($filePath);
                            
                            $txt = '';
                            while(!feof($fp)){
                                $txt .= fread($fp, 1024);
                            }
                            fclose($fp);

                            $contentLength = strlen($txt);

                            socket_write($client, join("\r\n", [
                                $variables["SERVER_PROTOCOL"].' '.'200 OK',
                                "Content-Type: {$contentType}",
                                "Server: PHP",
                                "Content-Length: {$contentLength}",
                                "",
                                $txt
                            ]));
                            socket_close($client);
                            $this->clients->detach($client);
                            continue;
                        }
                        else {
                            socket_write($client, join("\r\n", [
                                $variables["SERVER_PROTOCOL"].' '.'404 Not Found',
                                "Content-Type: text/plain",
                                "",
                                "404 Not Found"
                            ]));

                            socket_close($client);
                            $this->clients->detach($client);
                            continue;
                        }
                    }
                }
            }
        }
    }

    public function run($callback){
        if(is_callable($callback)){
            $callback();
        }

        $this->loop();
    }
}

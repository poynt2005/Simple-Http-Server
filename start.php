<?php

require_once "Server/SocketServer.php";

$s = new SocketServer("0.0.0.0", 9889);
$s->run(function(){
    echo "Server is running at 0.0.0.0 on port 9889 \n";
});
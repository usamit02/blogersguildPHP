<?php

require_once __DIR__.'/sys/dbinit.php';
$ip = $_SERVER['REMOTE_ADDR'];
$host = gethostbyaddr($ip);
$uid = $_GET['uid'];
$sql = "SELECT id,na,discription,parent,price,folder,mid,
IF(ISNULL(price),null,IF((price < 1 or start_day < now()),1,0)) AS allow,
IF(ISNULL(t12bookmark.uid),0,1) AS bookmark 
FROM t01room LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' 
LEFT JOIN t12bookmark ON t01room.id = t12bookmark.rid AND t12bookmark.uid='$uid' ORDER BY id;";
$rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
header('Access-Control-Allow-Origin: *');
echo json_encode($rooms);

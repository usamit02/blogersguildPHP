<?php
require_once (__DIR__.'/sys/dbinit.php');
$ip=$_SERVER['REMOTE_ADDR'];
$host=gethostbyaddr($ip);
$uid=$_GET['uid'];
$sql="SELECT id,na,discription,parent,price,folder,mid,
IF(price is null,null,IF((price < 1 or start_day < now()),1,0)) AS allow 
FROM t01room LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' ORDER BY id;";
$rooms=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
header("Access-Control-Allow-Origin: *");
echo json_encode($rooms);
?>
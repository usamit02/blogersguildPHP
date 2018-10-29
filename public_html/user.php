<?php
require_once (__DIR__.'/sys/dbinit.php');
$ip=$_SERVER['REMOTE_ADDR'];
$host=gethostbyaddr($ip);
$parent=$_GET['parent'];
$sql="SELECT id,na,discription,parent,price,mid FROM t01room WHERE parent=$parent ORDER BY id;";
$users=$db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
header("Access-Control-Allow-Origin: *");
echo json_encode($db->query($sql)->fetchAll(PDO::FETCH_ASSOC));
?>
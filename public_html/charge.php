<?php
require_once (__DIR__.'/../sys/dbinit.php');
$ip=$_SERVER['REMOTE_ADDR'];
$host=gethostbyaddr($ip);
$id=$_POST['id'];
$r=$db->query("SELECT na FROM t14member WHERE id='$id';")->fetch();
if($r){
  $json="{name:".$r['name']."}";
}else{
  $json="{name:'該当なし'";
}
if($a==$b){
    $f=0;
}else{
      $z=0;
}
header('Content-type: application/json');
echo json_encode($json);
?>
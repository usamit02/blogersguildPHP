<?php
require_once (__DIR__.'/sys/dbinit.php');
$uid=$_GET['uid'];
$rid=intval($_GET['rid']);
$bookmark=$_GET['bookmark'];
if($bookmark==="1"){
  $ps=$db->prepare("DELETE FROM t12bookmark WHERE uid=? AND rid=?;");
  if ($ps->execute(array($uid,$rid))) {
    $json['msg']="ok";
  }else{
    $json['msg']="データベースエラーによりブックマークの削除に失敗しました。";
  }
}else{
  $ps=$db->prepare("INSERT INTO t12bookmark (uid,rid) VALUES (?,?);");
  if ($ps->execute(array($uid,$rid))) {
    $json['msg']="ok";
  }else{
    $json['msg']="データベースエラーによりブックマークの追加に失敗しました。";
  }
}
header("Access-Control-Allow-Origin: *");
echo json_encode($json);
?>
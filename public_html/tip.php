<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = htmlspecialchars($_GET['uid']);
$rid = htmlspecialchars($_GET['rid']);
$tiper = htmlspecialchars($_GET['tiper']);
$room = htmlspecialchars($_GET['room']);
$chat = json_decode($_GET['chat'], true);
$upd=date('Y-m-d H:i:s',strtotime($chat['upd']));
$ps=$db->prepare("INSERT INTO t36tip (uid,rid,upd,tip_uid,tip_day) VALUES (?,?,?,?,?);");
$ps1=$ps->execute(array($chat['uid'],$rid,$upd,$uid,date("Y-m-d H:i:s"))) && $ps->rowCount()===1;
if($ps1){
  $parent = $rid;
  do {//部屋にスタッフがいなければ上層部屋のスタッフを探す
      $staffs = $db->query("SELECT uid,parent,mail FROM t03staff 
      JOIN t01room  ON t03staff.rid=t01room.id JOIN t02user ON t03staff.uid=t02user.id 
      WHERE t03staff.rid=$parent AND t03staff.auth>=200 AND mail IS NOT NULL;")->fetchAll(PDO::FETCH_ASSOC);
      if (count($staffs)) {
          break;
      } else {
          $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchcolumn();
      }
  } while ($parent);
  $url="$hpadress/home/room/$rid/".strtotime($upd);
  foreach($staffs AS $staff){
     mb_send_mail($staff['mail'],"ギルドシステム ".$room." での投稿に通報",
     "投稿者:".$chat['na']." 投稿日時:".$upd."通報者:".$tiper."\r\n内容:".$chat['txt']."\r\nlink:<a href='$url' target='_blank'>$url</a>");
  }
  $res['msg']="ok";
}else{
  $res['msg']="データベースエラーにより通報に失敗しました。";
}
echo json_encode($res);

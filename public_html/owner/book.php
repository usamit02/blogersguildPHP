<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (!isset($referer) || !isset($_GET['uid'])) {
    return;
}
$uid = htmlspecialchars($_GET['uid']);
require_once __DIR__.'/../sys/dbinit.php';
$res = $db->query("SELECT book.room,book.rqd,book.upd,book.amount FROM 
(SELECT uid,null AS room,rqd,upd,-amount AS amount FROM t58trance 
UNION SELECT uid,na,null,t56roomdiv.upd,amount FROM t56roomdiv JOIN t01room ON t56roomdiv.rid=t01room.id) book 
WHERE book.uid='$uid' ORDER BY book.rqd DESC;")->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res);

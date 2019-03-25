<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = htmlspecialchars($_GET['uid']);
$res['msg'] = '';
if (isset($_GET['notify']) && isset($_GET['unblocks'])) {//設定保存、ブロック解除
    $notify = json_decode($_GET['notify'], true);
    $unblocks = json_decode($_GET['unblocks'], true);
    $uid = htmlspecialchars($_GET['uid']);
    $direct = $notify['direct'] ? 1 : 0;
    $mention = $notify['mention'] ? 1 : 0;
    $ps = $db->prepare('UPDATE t02user SET mail=?,direct=?,mention=? WHERE id=?;');
    if (!$ps->execute(array($notify['mail'], $direct, $mention, $uid))) {
        $res['msg'] .= "ユーザー設定の保存に失敗しました。\r\n";
    }
    $error = 0;
    foreach ($unblocks as $unblock) {
        $ps = $db->prepare('DELETE FROM t15block WHERE uid=? AND mid=?;');
        $error += !$ps->execute(array($uid, $unblock)) || $ps->rowCount() !== 1;
    }
    if ($error) {
        $res['msg'] .= $error."件のブロック解除に失敗しました。\r\n";
    }
} elseif (isset($_GET['rids'])) {//新着メッセージ判定素材
    $rids = json_decode(htmlspecialchars($_GET['rids']));
    $where = 'rid IN (';
    foreach ($rids as $i => $rid) {
        $where .= "$rid,";
    }
    $where = substr($where, 0, strlen($where) - 1).')';
    $res = $db->query("SELECT rid AS id,csd,upd FROM t14roomcursor LEFT JOIN t01room on t14roomcursor.rid=t01room.id 
    WHERE uid='$uid' AND $where;")->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
} else {//通知設定取得
    $res['notify'] = $db->query("SELECT mail,direct,mention FROM t02user WHERE id='$uid';")->fetch(PDO::FETCH_ASSOC);
    $blocks = $db->query("SELECT id,na,avatar FROM t02user JOIN t15block ON t02user.id=t15block.mid WHERE uid='$uid';")->fetchAll(PDO::FETCH_ASSOC);
    $res['blocks'] = $blocks;
}
echo json_encode($res);

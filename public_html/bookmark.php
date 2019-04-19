<?php
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid =  htmlspecialchars($_GET['uid']);
$rid = intval(htmlspecialchars($_GET['rid']));
$bookmark =  htmlspecialchars($_GET['bookmark']);
if ($bookmark === '1') {
    $ps = $db->prepare('DELETE FROM t17bookmark WHERE uid=? AND rid=?;');
    if ($ps->execute(array($uid, $rid))) {
        $json['msg'] = 'ok';
    } else {
        $json['msg'] = 'データベースエラーによりブックマークの削除に失敗しました。';
    }
} else {
    $ps = $db->prepare('INSERT INTO t17bookmark (uid,rid) VALUES (?,?);');
    if ($ps->execute(array($uid, $rid))) {
        $json['msg'] = 'ok';
    } else {
        $json['msg'] = 'データベースエラーによりブックマークの追加に失敗しました。';
    }
}
echo json_encode($json);

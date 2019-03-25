<?php

mb_language('Japanese');
mb_internal_encoding('UTF-8');
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = htmlspecialchars($_POST['uid']);
$rid = htmlspecialchars($_POST['rid']);
$upd = isset($_POST['upd']) ? htmlspecialchars($_POST['upd']) : null;
$mid = isset($_POST['mid']) ? htmlspecialchars($_POST['mid']) : '';
$na = isset($_POST['na']) ? htmlspecialchars($_POST['na']) : '';
$txt = isset($_POST['txt']) ? htmlspecialchars($_POST['txt']) : '';
$res['msg'] = '';
$txt .= "\r\nhttps://localhost:8100/home/room/".$rid;
if ($rid < 1000000000 || !$mid) {
    $ps = $db->prepare('UPDATE t01room SET upd=? WHERE id=?;');
    if (!$ps->execute(array($upd, $rid)) && $ps->rowCount() !== 1) {
        $res['msg'] .= "チャット更新記録に失敗しました。\r\n";
    }
    $rs = $db->query("SELECT mail,uid FROM t16notify JOIN t02user ON t16notify.uid=t02user.id WHERE rid=$rid;");
    while ($r = $rs->fetch()) {
        if ($r['mail']) {
            mb_send_mail($r['mail'], $na.'の書き込み'.$subject, $txt);
        }
    }
} else {
    $block = $db->query("SELECT mid FROM t15block WHERE uid='$mid' AND mid='$uid';")->fetchColumn();
    if (!$block) {
        if ($rid < 1000000000) {
            $subject = 'メンション';
            $where = 'mention';
        } else {
            $subject = 'ダイレクト';
            $where = 'direct';
        }
        $mail = $db->query("SELECT mail FROM t02user WHERE id='$mid' AND $where=1;")->fetchColumn();
        if ($mail) {
            mb_send_mail($mail, $na.'から'.$subject, $txt);
        }
    }
}
echo json_encode($res);

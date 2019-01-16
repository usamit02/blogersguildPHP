<?php

require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['uid']) && isset($_GET['na']) && isset($_GET['avatar'])) {
    $id = htmlspecialchars($_GET['uid']);
    $na = htmlspecialchars($_GET['na']);
    $avatar = htmlspecialchars($_GET['avatar']);
    $ps = $db->prepare('INSERT INTO t02user (id,na,avatar,upd) VALUES (:id,:na,:avatar,:upd) ON DUPLICATE KEY 
    UPDATE na=VALUES(na),avatar=VALUES(avatar),rev=VALUES(upd);');
    $exe = $ps->execute(array('id' => $id, 'na' => $na, 'avatar' => $avatar, 'upd' => date('Y-m-d H:i:s')));
    if ($exe && $ps->rowCount()) {
        $res['msg'] = 'ok';
    } else {
        $res['msg'] = 'データベースエラーによりログイン情報の保存に失敗しました。';
    }
    $user = $db->query("SELECT * FROM t02user WHERE id='$id';")->fetchAll(PDO::FETCH_ASSOC);
    $res += $user[0];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
    $host = gethostbyaddr($ip);
    $parent = $_GET['parent'];
    $sql = "SELECT id,na,discription,parent,price,mid FROM t01room WHERE parent=$parent ORDER BY id;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

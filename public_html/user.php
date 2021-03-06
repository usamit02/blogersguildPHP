<?php

require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['uid']) && isset($_GET['na']) && isset($_GET['avatar'])) {
    $id = htmlspecialchars($_GET['uid']);
    $na = htmlspecialchars($_GET['na']);
    $avatar = htmlspecialchars($_GET['avatar']);
    $error = 0;
    $user = $db->query("SELECT * FROM t02user WHERE id='$id';")->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        if($user['black']){
            $res['msg']="アカウントは停止されています。";
        }else if ($user['na'] === $na && $user['avatar'] === $avatar) {
            $ps = $db->prepare('UPDATE t02user SET rev=:rev WHERE id=:id;');
            $error += !$ps->execute(array('id' => $id, 'rev' => date('Y-m-d H:i:s'))) || $ps->rowCount() !== 1;
        } else {
            $ps = $db->prepare('UPDATE t02user SET na=:na,avatar=:avatar,rev=:rev WHERE id=:id;');
            $error += !$ps->execute(array('id' => $id, 'na' => $na, 'avatar' => $avatar, 'rev' => date('Y-m-d H:i:s'))) || $ps->rowCount() !== 1;
            $user['na'] = $na;
            $user['avatar'] = $avatar;
        }
    } else {
        $no = $db->query('SELECT MAX(no)+1 FROM t02user;')->fetchcolumn();
        $ps = $db->prepare('INSERT INTO t02user (id,no,na,avatar,upd,p) VALUES (:id,:no,:na,:avatar,:upd,:p);');
        $user = array('id' => $id, 'no' => $no, 'na' => $na, 'avatar' => $avatar, 'upd' => date('Y-m-d H:i:s'), 'p' => 0);
        $error += !$ps->execute($user) || $ps->rowCount() !== 1;
    }
    if(!isset($res['msg'])){
        if ($error) {
            $res['msg'] = 'データベースエラーによりログイン情報の保存に失敗しました。';
        } else {
            $res['user'] = $user;
            $res['msg'] = 'ok';
        }
    }
} elseif (isset($_GET['no'])) {
    $no = htmlspecialchars($_GET['no']);
    $user = $db->query("SELECT * FROM t02user WHERE no=$no;")->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $res = $user;
        $uid = $user['id'];
        $res['staffs'] = $db->query("SELECT auth,class,t01room.na AS room,t03staff.rid AS rid,t03staff.upd AS upd 
        FROM t03staff JOIN t01room ON t03staff.rid=t01room.id JOIN mt03auth ON t03staff.auth=mt03auth.id 
        WHERE t03staff.uid='$uid' ORDER BY t03staff.rid;")->fetchAll(PDO::FETCH_ASSOC);
        $res['members'] = $db->query("SELECT active AS auth,class,t01room.na AS room,
        t11roompay.rid AS rid, t11roompay.created AS upd FROM t11roompay 
        JOIN t01room ON t11roompay.rid=t01room.id JOIN mt03auth ON active=mt03auth.id 
        WHERE t11roompay.uid='$uid' ORDER BY t11roompay.rid;")->fetchAll(PDO::FETCH_ASSOC);
        $res['links'] = $db->query("SELECT idx,media,na,url FROM t32link WHERE uid='$uid' ORDER BY idx;")->fetchAll(PDO::FETCH_ASSOC);
        $res['msg']='ok';
    } else {
        $res['msg'] = 'データベースエラーによりユーザー読み込みに失敗しました。';
    }
} elseif (isset($_GET['link'])) {
    $uid = htmlspecialchars($_GET['link']);
    $res = $db->query("SELECT idx,media,na,url FROM t32link WHERE uid='$uid' ORDER BY idx;")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
    $host = gethostbyaddr($ip);
    $parent = $_GET['parent'];
    $sql = "SELECT id,na,discription,parent,price,mid FROM t01room WHERE parent=$parent ORDER BY id;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

/* $ps = $db->prepare('INSERT INTO t02user (id,na,avatar,upd) VALUES (:id,:na,:avatar,:upd) ON DUPLICATE KEY
    UPDATE na=VALUES(na),avatar=VALUES(avatar),rev=VALUES(upd);');
    $exe = $ps->execute(array('id' => $id, 'na' => $na, 'avatar' => $avatar, 'upd' => date('Y-m-d H:i:s'))); */

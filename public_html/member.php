<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['search'])) {//検索ボックスからのサーチ
    $x = htmlspecialchars($_GET['search']);
    $sql = "SELECT id,na,avatar,upd,rev,no FROM t02user WHERE na like '%$x%' LIMIT 50;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}else if(isset($_GET['find'])){
    $rid = intval(htmlspecialchars($_GET['find']));
    $uid = htmlspecialchars($_GET['uid']);
    
    
}elseif (isset($_GET['rid'])) {//詳細表示
    $rid = intval(htmlspecialchars($_GET['rid']));
    $uid = isset($_GET['uid']) ? htmlspecialchars($_GET['uid']) : '';
    $authIn = ''; //roomとそのparents
    $payrid = ''; //直近のpayRoom
    do {
        $authIn .= $rid.',';
        $rs = $db->query("SELECT parent,plan FROM t01room WHERE id=$rid;");
        if ($r = $rs->fetch()) {
            if ($r['plan'] && !$payrid) {
                $payrid = $rid;
            }
            $rid = $r['parent'];
        } else {
            unset($rid);
        }
    } while (isset($rid));
    $authIn = substr($authIn, 0, strlen($authIn) - 1);
    $and = $uid ? " AND uid='$uid'" : '';
    $sql = $uid ? "SELECT id,na,avatar,upd,rev,p,no FROM t02user WHERE id='$uid'" :
    "SELECT DISTINCT t02user.id AS id,t02user.na AS na,avatar,t02user.upd AS upd,t02user.rev AS rev,p,no 
    FROM t02user INNER JOIN t03staff ON t02user.id=t03staff.uid WHERE rid IN($authIn)";
    if ($payrid) {
        $sql .= $uid ? '' :
        " UNION SELECT t02user.id,na,avatar,t02user.upd,rev,p,no FROM t02user INNER JOIN t11roompay 
        ON t02user.id=t11roompay.uid WHERE rid =$payrid AND active=1;";
        $payRooms = $db->query("SELECT uid FROM t11roompay WHERE rid=$payrid"."$and;")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $payRooms = [];
    }
    $staffRooms = $db->query("SELECT uid,rid,auth,idx FROM t03staff WHERE rid IN($authIn)$and ORDER BY auth DESC,idx;")->fetchAll(PDO::FETCH_ASSOC);
    $res = [];
    $rs = $db->query($sql);
    while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
        $staffs = array_values(array_filter($staffRooms, function ($room) use ($r) {return $room['uid'] === $r['id']; }));
        $pays = array_filter($payRooms, function ($room) use ($r) {return $room['uid'] === $r['id']; });
        $r['staffs'] = $staffs;
        $r['auth'] = count($staffs) ? $staffs[0]['auth'] : 0;
        $r['payrid'] = count($pays) ? $payrid : 0;
        $res[] = $r;
    }
} elseif (isset($_GET['uid']) && isset($_GET['mid'])) {//メンバーからの通知をブロックしているかどうか
    $uid = htmlspecialchars($_GET['uid']);
    $mid = htmlspecialchars($_GET['mid']);
    if (isset($_GET['block'])) {
        $block = htmlspecialchars($_GET['block']);
        $sql = $block ? 'DELETE FROM t15block WHERE uid=? AND mid=?;' : 'INSERT INTO t15block (uid,mid) VALUES (?,?);';
        $ps = $db->prepare($sql);
        if ($ps->execute(array($uid, $mid)) && $ps->rowCount() === 1) {
            $res['block'] = $block ? 0 : 1;
        } else {
            $res['msg'] = 'データベースエラー';
        }
    } else {
        $block = $db->query("SELECT mid FROM t15block WHERE uid='$uid' AND mid='$mid';")->fetchColumn();
        $res['block'] = $block ? 1 : 0;
    }
} else {
    $res['error'] = '不正なアクセスです。';
}
echo json_encode($res);
/* $sql = "SELECT id,na,avatar,auth,0 AS payroomid,rid AS authroomid,idx FROM t02user
            INNER JOIN t03staff ON t02user.id=t03staff.uid$and WHERE rid IN($authIn)";
    if ($payWhere) {
        $sql .= " UNION SELECT id,na,avatar,1,rid,0,9999 FROM t02user
        INNER JOIN t11roompay ON t02user.id=t11roompay.uid$and
        WHERE rid =$payWhere AND start_day > now() ORDER BY auth DESC,idx LIMIT 200;";
    }
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);*/

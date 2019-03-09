<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['search'])) {
    $x = htmlspecialchars($_GET['search']);
    $sql = "SELECT id,na,avatar FROM t02user WHERE na like '%$x%' LIMIT 50;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($_GET['rid'])) {
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
    $sql = "SELECT DISTINCT id,na,avatar FROM t02user INNER JOIN t03staff ON t02user.id=t03staff.uid$and 
    WHERE rid IN($authIn)";
    if ($payrid) {
        $sql .= " UNION SELECT id,na,avatar FROM t02user INNER JOIN t11roompay ON t02user.id=t11roompay.uid$and 
    WHERE rid =$payrid AND start_day < now()";
        $payRooms = $db->query("SELECT uid FROM t11roompay WHERE rid=$payrid"."$and;")->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $payRooms = [];
    }
    $rs = $db->query($sql);
    $rrs = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $staffRooms = $db->query("SELECT uid,rid,auth,idx FROM t03staff WHERE rid IN($authIn)$and ORDER BY auth DESC,idx;")->fetchAll(PDO::FETCH_ASSOC);
    $res = [];
    while ($r = $rs->fetch(PDO::FETCH_ASSOC)) {
        $staffs = array_values(array_filter($staffRooms, function ($room) use ($r) {return $room['uid'] === $r['id']; }));
        $pays = array_filter($payRooms, function ($room) use ($r) {return $room['uid'] === $r['id']; });
        $r['staffs'] = $staffs;
        $r['auth'] = count($staffs) ? $staffs[0]['auth'] : 0;
        $r['payrid'] = count($pays) ? $payrid : 0;
        $res[] = $r;
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

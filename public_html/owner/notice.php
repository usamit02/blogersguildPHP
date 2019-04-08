<?php

header('Access-Control-Allow-Origin: *');
if (!isset($_POST['uid']) && !isset($_POST['rooms'])) {
    return;
}
$uid = htmlspecialchars($_POST['uid']);
$rooms = json_decode($_POST['rooms'], true);
$end = isset($_POST['day']) ? htmlspecialchars(date($_POST['day'])) : date('Y-m-d H:i:s');
$start = date('Y-m-d', strtotime('-1 month', strtotime($end)));
$inPayRooms = '';
$inRooms = '';
foreach ($rooms as $key => $room) {
    $inRooms .= $room['id'].',';
    if ($room['plan']) {
        $inPayRooms .= $room['id'].',';
    }
}
if (!strlen($inRooms)) {
    return;
}
$res = [];
$img = '<img style="vertical-align: middle;width: 25px;height: 25px;border-radius: 50%;" src="';
$inRooms = substr($inRooms, 0, strlen($inRooms) - 1);
require_once __DIR__.'/../sys/dbinit.php';
if (strlen($inPayRooms)) {
    $inPayRooms = substr($inPayRooms, 0, strlen($inPayRooms) - 1);
    $nextDay = date('Y-m-d', strtotime('+1day'));
    $rs = $db->query("SELECT t02user.avatar AS avatar,t02user.na AS na,start_day,t01room.na AS room,auth_days 
    FROM t11roompay JOIN t01room ON t11roompay.rid=t01room.id JOIN t02user ON t11roompay.uid=t02user.id 
    JOIN t13plan ON t01room.id=t13plan.rid AND t01room.plan=t13plan.id
    WHERE start_day>='$start' AND start_day<='$end' AND active=0 AND t11roompay.rid IN ($inPayRooms);");
    while ($r = $rs->fetch()) {
        $auth_day = date('Y-m-d', strtotime($r['start_day'].' +'.$r['auth_days'].'days'));
        $re['day'] = $r['start_day'];
        $re['room'] = $r['room'];
        if ($auth_day === date('Y-m-d')) {
            $msg = '<span style="color:red;">本日中</span>';
        } elseif ($auth_day === $nextDay) {
            $msg = '<span style="color:orange;">明日まで</span>';
        } else {
            $msg = date('n月j日', strtotime($auth_day)).'まで';
        }
        $re['msg'] = $img.$r['avatar'].'">'.$r['na'].'さんから加入申し込み、'.$msg.'に審査を。';
        $res[] = $re;
    }
    $sql = "SELECT t02user1.avatar AS avatar1,t02user1.na AS na1,start_day,t02user2.avatar AS avatar2,
    t02user2.na AS na2,t01room.na AS room FROM t11roompay JOIN t01room ON t11roompay.rid=t01room.id 
    LEFT JOIN t02user AS t02user1 ON t11roompay.uid=t02user1.id LEFT JOIN t02user AS t02user2 ON 
    t11roompay.ok_uid=t02user2.id 
    WHERE start_day>='$start' AND start_day<='$end' AND active=1 AND t11roompay.rid IN ($inPayRooms);";
    $rs = $db->query($sql);
    while ($r = $rs->fetch()) {
        $re['day'] = $r['start_day'];
        $re['room'] = $r['room'];
        $re['msg'] = $img.$r['avatar1'].'">'.$r['na1'].'さんの入会が';
        if (isset($r['na2'])) {
            $re['msg'] .= $img.$r['avatar2'].'">'.$r['na2'].'さんにOK。';
        } else {
            $re['msg'] .= '審査期間経過により自動OK。';
        }
        $res[] = $re;
    }
    $sql = "SELECT t02user1.avatar AS avatar1,t02user1.na AS na1,end_day,t02user2.avatar AS avatar2,
    t02user2.na AS na2,t01room.na AS room FROM t51roompaid JOIN t01room ON t51roompaid.rid=t01room.id 
    LEFT JOIN t02user AS t02user1 ON t51roompaid.uid=t02user1.id 
    LEFT JOIN t02user AS t02user2 ON t51roompaid.ban_uid=t02user2.id 
    WHERE end_day>='$start' AND end_day<='$end' AND t51roompaid.rid IN ($inPayRooms);";
    $rs = $db->query($sql);
    while ($r = $rs->fetch()) {
        $re['day'] = $r['end_day'];
        $re['room'] = $r['room'];
        $re['msg'] = $img.$r['avatar1'].'">'.$r['na1'].'さんが';
        if (isset($r['na2'])) {
            $re['msg'] .= $img.$r['avatar2'].'">'.$r['na2'].'さんにBAN!';
        } else {
            $re['msg'] .= '退会。';
        }
        $res[] = $re;
    }
    $rs = $db->query("SELECT t01room.na AS room,t56roomdiv.upd AS upd,amount,t02user.na AS na1,
    t02user.avatar AS avatar1,member.na AS na2,member.avatar AS avatar2 FROM t56roomdiv 
    JOIN t01room ON t56roomdiv.rid=t01room.id 
    LEFT JOIN t02user ON t56roomdiv.uid=t02user.id LEFT JOIN t02user AS member ON t56roomdiv.mid=member.id
    WHERE t56roomdiv.upd >='$start' AND t56roomdiv.upd <='$end' AND t56roomdiv.rid IN ($inPayRooms);");
    while ($r = $rs->fetch()) {
        $re['day'] = $r['upd'];
        $re['room'] = $r['room'];
        $re['msg'] = $img.$r['avatar2'].'">'.$r['na2'].'の会費を'.$img.$r['avatar1'].'">'.$r['na1'].'に'.
        $r['amount'].'円配当';
        $res[] = $re;
    }
}
if (count($res)) {
    foreach ($res as $key => $val) {
        $sort[$key] = $val['day'];
    }
    array_multisort($sort, SORT_DESC, $res);
} else {
    $res[] = array('day' => $end, 'room' => '', 'msg' => '特になし');
}
echo json_encode($res);

/*
    $rs = $db->query("SELECT t01room.na AS room,t55roombill.upd AS upd,amount,t02user.na AS na,avatar FROM t55roombill
    JOIN t01room ON t55roombill.rid=t01room.id JOIN t02user ON t55roombill.uid=t02user.id
    WHERE t55roombill.upd >='$start' AND t55roombill.upd <='$end' AND t55roombill.rid IN ($inPayRooms);");
    while ($r = $rs->fetch()) {
        $re['day'] = $r['upd'];
        $re['room'] = $r['room'];
        $re['msg'] = $img.$r['avatar'].'">'.$r['na'].'から'.$r['amount'].'円を会費として自動引落';
        $res[] = $re;
    }


*/

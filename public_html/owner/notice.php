<?php

header('Access-Control-Allow-Origin: *');
if (!isset($_POST['uid']) && !isset($_POST['rooms'])) {
    return;
}
$uid = htmlspecialchars($_POST['uid']);
$rooms = json_decode($_POST['rooms'], true);
$end = isset($_POST['day']) ? htmlspecialchars(date($_POST['day'])) : date('Y-m-d');
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
    $rs = $db->query("SELECT t02user.avatar AS avatar,t02user.na AS na,sub_day,t11roompay.start_day AS start_day,
t01room.na AS room FROM t11roompay JOIN t01room ON t11roompay.rid=t01room.id LEFT JOIN t02user ON 
t11roompay.uid=t02user.id WHERE t11roompay.sub_day>='$start' AND t11roompay.sub_day<='$end' 
AND t11roompay.start_day > '$end' AND t11roompay.rid IN ($inPayRooms);");
    while ($r = $rs->fetch()) {
        $re['day'] = $r['sub_day'];
        $re['room'] = $r['room'];
        $start_day = date('Y-m-d', strtotime($r['start_day']));
        if ($start_day === date('Y-m-d')) {
            $msg = '<span style="color:red;">本日中</span>';
        } elseif ($start_day === $nextDay) {
            $msg = '<span style="color:orange;">明日まで</span>';
        } else {
            $msg = date('m月d日', strtotime($r['start_day'])).'まで';
        }
        $re['msg'] = $img.$r['avatar'].'">'.$r['na'].'さんから加入申し込み、'.$msg.'に審査を。';
        $res[] = $re;
    }
    $sql = "SELECT t02user1.avatar AS avatar1,t02user1.na AS na1,sub_day,t11roompay.start_day AS start_day,
t02user2.avatar AS avatar2,t02user2.na AS na2,t01room.na AS room FROM t11roompay JOIN t01room ON t11roompay.rid=t01room.id LEFT JOIN t02user 
AS t02user1 ON t11roompay.uid=t02user1.id LEFT JOIN t02user AS t02user2 ON t11roompay.ok_uid=t02user2.id WHERE 
t11roompay.start_day>='$start' AND t11roompay.start_day<='$end' AND t11roompay.rid IN ($inPayRooms);";
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
    $sql = "SELECT t02user1.avatar AS avatar1,t02user1.na AS na1,t51roompaid.end_day AS end_day,
t02user2.avatar AS avatar2,t02user2.na AS na2,t01room.na AS room FROM t51roompaid JOIN t01room ON 
t51roompaid.rid=t01room.id LEFT JOIN t02user 
AS t02user1 ON t51roompaid.uid=t02user1.id LEFT JOIN t02user AS t02user2 ON t51roompaid.ban_uid=t02user2.id WHERE 
t51roompaid.end_day>='$start' AND t51roompaid.end_day<='$end' AND t51roompaid.rid IN ($inPayRooms);";
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

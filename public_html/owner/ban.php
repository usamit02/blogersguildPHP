<?php

function setAuth($parentKey)
{
    global $rooms;
    $parent = $rooms[$parentKey];
    $childs = array_filter($rooms, function ($room) use ($parent) {return $room['parent'] === $parent['id']; });
    foreach ($childs as $key => $child) {
        if (!isset($child['auth'])) {
            $rooms[$key]['auth'] = $parent['auth'];
            setAuth($key);
        }
    }
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['uid']) && isset($_GET['ban'])) {
    $uid = htmlspecialchars($_GET['uid']);
    $ban = htmlspecialchars($_GET['ban']);
    $sql = "SELECT t01room.id AS id,t01room.na AS na,parent,auth,plan FROM t01room 
    LEFT JOIN t03staff ON t01room.id = t03staff.rid AND t03staff.uid='$ban'";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $authRooms = array_filter($rooms, function ($room) {return $room['auth'] >= 100; });
    foreach ($authRooms as $key => $room) {
        setAuth($key);
    }
    $rs = $db->query("SELECT rid,auth,class FROM t03staff LEFT JOIN mt03auth ON t03staff.auth=mt03auth.id WHERE uid='$uid';");
    $staffRooms = [];
    $bossRooms = [];
    while ($r = $rs->fetch()) {
        $room = array_values(array_filter($rooms, function ($room) use ($r) {return $room['id'] === $r['rid']; }));
        $staffRooms = array_merge($staffRooms, $room);
        if (count($room) && $r['auth'] >= $room[0]['auth']) {
            $room[0]['class'] = $r['class'];
            $bossRooms = array_merge($bossRooms, $room);
        }
    }
    $rs = $db->query("SELECT rid FROM t11roompay WHERE uid='$uid';");
    $payRooms = [];
    while ($r = $rs->fetch()) {
        $room = array_values(array_filter($rooms, function ($room) use ($r) {return $room['id'] === $r['rid']; }));
        if (count($room) && $room[0]['auth'] < 200) {
            $payRooms = array_merge($payRooms, $room);
        }
    }
    $res['msg']="ok";
    $res['staffrooms'] = $staffRooms; //役員になってる部屋全て
    $res['bossrooms'] = $bossRooms; //banする人の人事権が及ばない役員になっている部屋
    $res['payrooms'] = $payRooms; //banする人の人事権が及ばない会員になっている部屋
} else {
    $res['msg']="ng";
    $res['error'] = '不正なアクセスです。';
}
echo json_encode($res);

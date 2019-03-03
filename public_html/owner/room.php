<?php

function addRooms($parent, $rooms)
{
    $res = [];
    $childs = array_filter($rooms, function ($room) use ($parent) {return $room['parent'] === $parent; });
    $res = array_merge($res, $childs);
    foreach ($childs as $child) {
        $res = array_merge($res, addRooms($child['id'], $rooms));
    }

    return $res;
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['sql'])) {
    $error = 0;
    $sql = explode("\n", $_GET['sql']);
    $db->beginTransaction();
    foreach ($sql as $s) {
        $ps = $db->prepare($s);
        $error += (($ps->execute()) && $ps->rowCount() === 1) ? 0 : 1;
    }
    if ($error) {
        $db->rollBack();
        $res['msg'] = 'error';
    } else {
        $db->commit();
        $res['msg'] = 'ok';
    }
} elseif (isset($_GET['parent'])) {
    $res['maxId'] = 0;
    $maxId = $db->query('SELECT MAX(id) FROM t01room;')->fetchcolumn();
    if ($maxId) {
        ++$maxId;
        $parent = (int) $_GET['parent'];
        $ps = $db->prepare('INSERT INTO t01room SET id=?,na=?,parent=?;');
        if ($ps->execute(array($maxId, '新しい部屋', $parent)) && $ps->rowCount() === 1) {
            $res['maxId'] = $maxId;
        }
    }
} elseif (isset($_GET['uid'])) {
    $uid = htmlspecialchars($_GET['uid']);
    $sql = "SELECT t01room.id AS id,na,discription,parent,folder,t01room.idx AS idx,chat,story,auth,plan,prorate,amount,billing_day,
    trial_days,auth_days FROM t01room LEFT JOIN t03staff ON t01room.id = t03staff.rid AND t03staff.uid='$uid' 
    LEFT JOIN t13plan ON t01room.plan = t13plan.id ORDER BY t01room.idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $authRooms = array_filter($rooms, function ($room) {return $room['auth'] >= 100 || $room['id'] === 0; });
    $res = [];
    foreach ($authRooms as $i => $room) {
        $root = true;
        $parent = $room['parent'];
        while (isset($parent)) {
            $key = array_search($parent, array_column($rooms, 'id'));
            if ($key !== false) {
                $id = $rooms[$key]['id'];
                if (count(array_filter($authRooms, function ($r) use ($id) {return $r['id'] === $id; }))) {
                    $root = false;
                }
                $parent = $rooms[$key]['parent'];
            } else {
                $parent = null;
            }
        }
        if ($root) {
            $res[][0] = $room;
        }
    }
    foreach ($res as $i => $room) {
        $res[$i] = array_merge($res[$i], addRooms($room[0]['id'], $rooms));
    }
}
echo json_encode($res);

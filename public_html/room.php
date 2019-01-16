<?php

function addRooms($rooms, $parent)
{
    global $folders;
    $res = [];
    if ($parent['allow']) {
        $childs = array_filter($rooms, function ($room) use ($parent) {return $room['parent'] === $parent['id']; });
        //$folders += count($childs) ? $parent : [];
        if (count($childs)) {
            $folders[] = $parent;
        }
        $res += $childs;
        //$res = array_merge($res, $childs);
        foreach ($childs as $child) {
            //$res = array_merge($res, addRooms($child, $rooms));
            $res += addRooms($rooms, $child);
        }
    }

    return $res;
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['plan'])) {
    $plan = htmlspecialchars($_GET['plan']);
    $res = $db->query("SELECT amount FROM t13plan WHERE id=$plan;")->fetchAll(PDO::FETCH_ASSOC);
} else {
    $uid = htmlspecialchars($_GET['uid']);
    $sql = "SELECT id,na,discription,parent,plan,0 AS folder,chat,story,
    IF((!plan or start_day < now()),1,0) AS allow,IF(ISNULL(t12bookmark.uid),0,1) AS bookmark 
    FROM t01room LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' 
    LEFT JOIN t12bookmark ON t01room.id = t12bookmark.rid AND t12bookmark.uid='$uid' ORDER BY idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
//$res = array_filter($rooms, function ($room) {return $room['id'] === 1; });
//$res = array_merge($res, addRooms($res[1], $rooms));
$res[] = $rooms[1];
$folders = [];
$res += addRooms($rooms, $rooms[1]);
foreach ($folders as $folder) {
}
echo json_encode($res);

//IF(ISNULL(price),null,IF((price < 1 or start_day < now()),1,0)) AS allow,

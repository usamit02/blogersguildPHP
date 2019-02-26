<?php

function addRooms($rooms, $parent)
{
    global $response;
    static $res = [];
    if ($rooms[$parent]['allow']) {
        $id = $rooms[$parent]['id'];
        $childs = array_filter($rooms, function ($room) use ($id) {return $room['parent'] === $id; });
        if (count($childs) && $parent !== 1) {
            $res[$parent]['folder'] = 1;
        }
        $res += $childs;
        foreach ($childs as $key => $child) {
            addRooms($rooms, $key);
        }
    }
    $response = $res;
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['plan'])) {
    $plan = htmlspecialchars($_GET['plan']);
    $res = $db->query("SELECT amount FROM t13plan WHERE id=$plan;")->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($_GET['csd'])) {
    $uid = htmlspecialchars($_GET['uid']);
    $rid = htmlspecialchars($_GET['rid']);
    $csd = htmlspecialchars($_GET['csd']);
    $ps = $db->prepare('INSERT INTO t14roomcursor (uid,rid,csd) VALUES (:uid,:rid,:csd) ON DUPLICATE KEY 
    UPDATE csd=VALUES(csd);');
    $ps->execute(array('uid' => $uid, 'rid' => $rid, 'csd' => $csd));
} else {
    $uid = htmlspecialchars($_GET['uid']);
    $sql = "SELECT id,na,discription,parent,plan,0 AS folder,chat,story,csd,t03staff.auth AS auth,
    IF((!plan or start_day < now()),1,0) AS allow,IF(ISNULL(t12bookmark.uid),0,1) AS bookmark FROM t01room 
    LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' 
    LEFT JOIN t03staff ON t01room.id = t03staff.rid AND t03staff.uid='$uid' 
    LEFT JOIN t12bookmark ON t01room.id = t12bookmark.rid AND t12bookmark.uid='$uid' 
    LEFT JOIN t14roomcursor ON t01room.id = t14roomcursor.rid AND t14roomcursor.uid='$uid' ORDER BY t01room.idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    addRooms($rooms, 1);
    $rooms[1]['folder'] = 1;
    $res[] = $rooms[1];
    $response = array_merge($response, $res);
    echo json_encode($response);
}

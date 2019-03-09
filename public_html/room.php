<?php

function setRooms($parent)
{
    global $rooms;
    $childs = array_filter($rooms, function ($room) use ($parent) {
        return $room['parent'] === $parent;
    });
    if (count($childs)) {
        $rooms[$parent]['folder'] = 1;
        if ($rooms[$parent]['lock'] < 1 || $rooms[$parent]['auth']) {
            foreach ($childs as $key => $child) {
                if (!isset($child['auth']) || $child['auth'] < $rooms[$parent]['auth']) {
                    $rooms[$key]['auth'] = $rooms[$parent]['auth'];
                }
                if (!isset($child['plan']) || !$child['plan']) {
                    $rooms[$key]['applyplan'] = $rooms[$parent]['plan'];
                }
                setRooms($key);
            }
        } else {
            $rooms = array_diff_key($rooms, $childs);
        }
    }
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
if (isset($_GET['csd'])) {
    $uid = htmlspecialchars($_GET['uid']);
    $rid = htmlspecialchars($_GET['rid']);
    $csd = htmlspecialchars($_GET['csd']);
    $ps = $db->prepare('INSERT INTO t14roomcursor (uid,rid,csd) VALUES (:uid,:rid,:csd) ON DUPLICATE KEY 
    UPDATE csd=VALUES(csd);');
    $ps->execute(array('uid' => $uid, 'rid' => $rid, 'csd' => $csd));
    $res = 'ok';
} else {
    $uid = htmlspecialchars($_GET['uid']);
    $sql = "SELECT id AS id0,id,na,discription,parent,plan,0 AS folder,chat,story,csd,t03staff.auth AS auth,
    IF(plan,IF(ISNULL(start_day),10,IF(start_day<now(),0,1)),0) AS `lock`,IF(ISNULL(t12bookmark.uid),0,1) AS bookmark FROM t01room 
    LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' 
    LEFT JOIN t03staff ON t01room.id = t03staff.rid AND t03staff.uid='$uid' 
    LEFT JOIN t12bookmark ON t01room.id = t12bookmark.rid AND t12bookmark.uid='$uid' 
    LEFT JOIN t14roomcursor ON t01room.id = t14roomcursor.rid AND t14roomcursor.uid='$uid' ORDER BY t01room.idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    setRooms(1);
    $res = array_values($rooms);
}
echo json_encode($res);
/*IF((!plan or start_day < now()),1,0) AS allow,*/
/*setFolder($rooms, 1);
    $rooms[1]['folder'] = 1;
    $res[] = $rooms[1];
    $rooms = array_merge($folderRooms, $res);
    $authRooms = array_filter($rooms, function ($room) {return $room['auth'] >= 100; });
    foreach ($authRooms as $key => $room) {
        setAuth($key);
    }
    $planRooms = array_filter($rooms, function ($room) {
        return $room['plan'];
    });
    foreach ($planRooms as $key => $room) {
        setPlan($key);
        $rooms[$key]['applyplan'] = $room['plan'];
    }
    function setFolder($rooms, $parent)//子を持っていれば親のfolderを1に設定、allow=1またはstaff以外のroomを削除
{
    global $folderRooms;
    static $res = [];
    if ($rooms[$parent]['allow'] || $rooms[$parent]['auth']) {
        $id = $rooms[$parent]['id'];
        $childs = array_filter($rooms, function ($room) use ($id) {
            return $room['parent'] === $id;
        });
        if (count($childs) && $parent !== 1) {
            $res[$parent]['folder'] = 1;
        }
        foreach ($childs as $key => $child) {
            if (!isset($child['auth']) || $child['auth'] < $rooms[$parent]['auth']) {
                $child['auth'] = $rooms[$parent]['auth'];
            }
            setFolder($rooms, $key);
        }
        $res += $childs;
    }
    $folderRooms = $res;
}

function setAuth($parentKey)
{
    global $rooms;
    $parent = $rooms[$parentKey];
    $childs = array_filter($rooms, function ($room) use ($parent) {
        return $room['parent'] === $parent['id'];
    });
    foreach ($childs as $key => $child) {
        if (!isset($child['auth'])) {
            $rooms[$key]['auth'] = $parent['auth'];
            setAuth($key);
        }
    }
}
function setPlan($parentKey)
{
    global $rooms;
    $parent = $rooms[$parentKey];
    $childs = array_filter($rooms, function ($room) use ($parent) {
        return $room['parent'] === $parent['id'];
    });
    foreach ($childs as $key => $child) {
        if (!isset($child['plan']) || !$child['plan']) {
            $rooms[$key]['applyplan'] = $parent['plan'];
            setPlan($key);
        }
    }
}


















    */

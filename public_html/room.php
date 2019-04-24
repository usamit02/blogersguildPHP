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
function numMember($parent){
    global $fullrooms;
     $childs = array_values(array_filter($fullrooms, function ($room) use ($parent) {
        return $room['parent'] === $parent;
    }));
    if (count($childs)) {
        $member=0;
        $staff=0;
        for($i=0;$i<count($childs);$i++) {           
            numMember($childs[$i]['id']);
            $member+=$fullrooms[$childs[$i]['id']]['member'];
            $staff+=$fullrooms[$childs[$i]['id']]['staff'];
        }
        $fullrooms[$parent]['member'] +=$member;
        $fullrooms[$parent]['staff'] +=$staff;
    }
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = isset($_GET['uid']) ? htmlspecialchars($_GET['uid']) : '';
$rid = isset($_GET['rid']) ? htmlspecialchars($_GET['rid']) : '';
if (isset($_GET['csd'])) {//既読カーソルを記録
    $csd = htmlspecialchars($_GET['csd']);
    $ps = $db->prepare('INSERT INTO t14roomcursor (uid,rid,csd) VALUES (:uid,:rid,:csd) ON DUPLICATE KEY 
    UPDATE csd=VALUES(csd);');
    $error = $ps->execute(array('uid' => $uid, 'rid' => $rid, 'csd' => $csd));
} elseif (isset($_GET['rids'])) {//新着メッセージ判定素材
    $rids = json_decode(htmlspecialchars($_GET['rids']));
    $where = 'rid IN (';
    foreach ($rids as $i => $rid) {
        $where .= "$rid,";
    }
    $where = substr($where, 0, strlen($where) - 1).')';
    $res['cursors'] = $db->query("SELECT rid AS id,csd,upd FROM t14roomcursor LEFT JOIN t01room on t14roomcursor.rid=t01room.id 
    WHERE uid='$uid' AND $where;")->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
} else {//部屋データ取得
    $sql = "SELECT t01room.id AS id0,t01room.id AS id,t01room.na AS na,discription,parent,t01room.plan AS plan,
    0 AS folder,chat,story,csd,t03staff.auth AS auth,t13plan.amount AS amount,t02user.no AS no,shut,
    IF(t01room.plan,IF(ISNULL(active),10,NOT(active)),0) AS `lock`,0 AS member,0 AS staff,
    IF(ISNULL(t17bookmark.uid),0,1) AS bookmark,t02user.na AS `owner`,img,avatar FROM t01room 
    LEFT JOIN t11roompay ON t01room.id=t11roompay.rid AND t11roompay.uid='$uid' 
    LEFT JOIN t03staff ON t01room.id=t03staff.rid AND t03staff.uid='$uid' 
    LEFT JOIN t03staff AS staff ON t01room.id=staff.rid AND staff.auth=9000 AND staff.idx=0 
    LEFT JOIN t02user ON staff.uid=t02user.id 
    LEFT JOIN t17bookmark ON t01room.id=t17bookmark.rid AND t17bookmark.uid='$uid' 
    LEFT JOIN t14roomcursor ON t01room.id=t14roomcursor.rid AND t14roomcursor.uid='$uid' 
    LEFT JOIN t13plan ON t01room.id=t13plan.rid AND t01room.plan=t13plan.id WHERE shut<100 ORDER BY t01room.idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    setRooms(1); 
    $rooms=array_values($rooms);
    $fullrooms=$db->query("SELECT t01room.id AS id0,t01room.id AS id,na,parent,IFNULL(member.num,0) AS member,
    IFNULL(staff.num,0) AS staff FROM t01room 
    LEFT JOIN (SELECT rid,COUNT(rid) AS num FROM t11roompay GROUP BY rid) AS member ON t01room.id=member.rid 
    LEFT JOIN (SELECT rid,COUNT(rid) AS num FROM t03staff GROUP BY rid) AS staff ON t01room.id=staff.rid;"
    )->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    numMember(1);   
    for($i=0;$i<count($rooms);$i++){
        $rooms[$i]['member']=$fullrooms[$rooms[$i]['id']]['member'];
        $rooms[$i]['staff']=$fullrooms[$rooms[$i]['id']]['staff'];
    }
    if(count($rooms)&&count($fullrooms)){
        $res['all'] =$rooms;
        $res['full']=array_values($fullrooms);
    }else{
        $res['msg']="データベースエラーにより部屋の読込に失敗しました。";
    }   
}
$res['msg']=isset($res['msg'])?$res['msg']:"ok";
echo json_encode($res);

//SELECT rid,count(rid) AS num FROM t11roompay GROUP BY rid;
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
 else if(isset($_GET['mid'])){//検索したメンバーがいる部屋を探す
    $mid = htmlspecialchars($_GET['mid']);
    $sql = "SELECT t01room.id AS id0,t01room.id AS id,t01room.na AS na,parent,t03staff.auth AS auth,
    IF(t01room.plan,IF(ISNULL(active),10,NOT(active)),0) AS `lock`,0 AS folder,t01room.plan AS plan FROM t01room 
    LEFT JOIN t11roompay ON t01room.id=t11roompay.rid AND t11roompay.uid='$uid' 
    LEFT JOIN t03staff ON t01room.id=t03staff.rid AND t03staff.uid='$uid' WHERE shut<100;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    $allrooms=$rooms;
    setRooms(1);
    if(isset($rooms[$rid])){
        $res=$rid;
    }else{
        $parent=$allrooms[$rid]['parent'];
        do{
            $targets=array_filter($rooms,function($room) use ($parent){
                return $room['id']===$parent;
            });
            if(count($targets)){
                $res=$target;
                break;
            }else{
                $parentRooms=array_filter($allrooms,function($room) use ($parent){
                    return $room['id']===$parent;
                });           
                $parent=$parentRooms[0]['parent'];
            }

        }while($parent);
    }
}

function numMember($parent){
    global $fullrooms;
     $childs = array_filter($fullrooms, function ($room) use ($parent) {
        return $room['parent'] === $parent;
    });
    if (count($childs)) {
        $member=0;
        foreach ($childs as $key => $child) {           
            numMember($key);
            $member+=$child['member'];
        }
        $fullrooms[$parent]['member'] +=$member;
    }
}















    */

<?php

function addRooms($parent, $rooms)
{
    $json = '';
    $res = array_filter($rooms, function ($r) use ($parent) {return $r['parent'] === $parent; });
    if (count($res)) {
        $json = '"children":[';
        foreach ($res as $r) {
            $json .= '{"id":'.$r['id'].',"na":"'.$r['na'].'","discription":"'.$r['discription'].'",
                "price":'.$r['price'].',"parent":'.$r['parent'].',"num":'.$r['num'].',"typ":'.$r['typ'];
            $j = addRooms($r['id'], $rooms);
            $json .= $j ? ','.$j : '';
            $json .= '},';
        }
        $json = mb_substr($json, 0, -1).']';
    }

    return $json;
}
require_once __DIR__.'/../sys/dbinit.php';
$uid = 'AMavP9Icrfe7GbbMt0YCXWFWIY42'; //$_GET['uid'];
$sql = "SELECT id,na,discription,parent,price,num,typ,
IF(ISNULL(price),null,IF((price < 1 or start_day < now()),1,0)) AS allow
FROM t01room LEFT JOIN t11roompay ON t01room.id = t11roompay.rid AND t11roompay.uid='$uid' ORDER BY num;";
$rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$json = '';
$json .= addRooms($rooms[0]['id'], array_filter($rooms, function ($r) {return $r['id'] !== '0'; }));
header('Access-Control-Allow-Origin: *');
echo mb_substr($json, 11);
//'[{"id":1,"name":"a1","children":[{"id":2,name:"child1"},{id:3,name:"child2"}]},{id:4,name:"a2",children:[{id:5,name:"child2.1"},{id:6,name:"child2.2",children:[{id:7,name:"subsub"}]}]}]';

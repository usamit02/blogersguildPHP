<?php

function makeWhere($parent)
{
    global $rooms,$where;
    $childs = array_filter($rooms, function ($room) use ($parent) {
        return $room['parent'] === $parent;
    });
    foreach ($childs as $key => $child) {
        $where .= "$key,";
        makeWhere($key);
    }
}
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['search'])) {
    $x = htmlspecialchars($_GET['search']);
    $sql = "SELECT id,na,avatar FROM t02user WHERE na like '%$x%' LIMIT 50;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($_GET['room'])) {
    $room = intval(htmlspecialchars($_GET['room']));
    $authIn = '';
    $payIn = '';
    do {
        $authIn .= $room.',';
        $rs = $db->query("SELECT parent,plan FROM t01room WHERE id=$room;");
        if ($r = $rs->fetch()) {
            if ($r['plan'] && !$payIn) {
                $payIn = $room;
            }
            $room = $r['parent'];
        } else {
            unset($room);
        }
    } while (isset($room));
    $authIn = substr($authIn, 0, strlen($authIn) - 1);
    $sql = "SELECT id,na,avatar,auth,rid as room,idx,rate FROM t02user INNER JOIN t03staff ON t02user.id=t03staff.uid 
    WHERE rid IN($authIn)";
    if ($payIn) {
        $sql .= " UNION SELECT id,na,avatar,active,rid,0,0 FROM t02user INNER JOIN t11roompay ON t02user.id=t11roompay.uid 
    WHERE rid =$payIn ORDER BY auth DESC,idx;";
    }
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} elseif (isset($_GET['rid'])) {//指定された部屋以下に会員（審査中含む）がいるかどうか
    $rid = intval(htmlspecialchars($_GET['rid']));
    $rooms = $db->query('SELECT id,parent FROM t01room;')->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    $where = "$rid,";
    makeWhere($rid);
    $where = substr($where, 0, strlen($where) - 1);
    $hasmember = $db->query("SELECT rid FROM t11roompay WHERE rid IN($where);")->fetch();
    $res['hasmember'] = $hasmember ? true : false;
} else {
    $res['error'] = '不正なアクセスです。';
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

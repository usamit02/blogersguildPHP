<?php

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
    $sql = "SELECT id,na,avatar,auth,rid as room,idx FROM t02user INNER JOIN t71roomauth ON t02user.id=t71roomauth.uid 
WHERE rid IN($authIn)";
    if ($payIn) {
        $sql .= " UNION SELECT id,na,avatar,1,rid,0 FROM t02user INNER JOIN t11roompay ON t02user.id=t11roompay.uid 
WHERE rid =$payIn ORDER BY auth DESC,idx;";
    }
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} else {
    $res['error'] = '不正なアクセスです。';
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

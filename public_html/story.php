<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = htmlspecialchars($_GET['uid']);
$rid = htmlspecialchars($_GET['rid']);
$storys = $db->query("SELECT id,txt,media,idx,pay FROM t21story WHERE rid=$rid ORDER BY idx;")->fetchAll(PDO::FETCH_ASSOC);
$pays = $db->query("SELECT sid FROM t12storypay WHERE uid='$uid' AND rid=$rid;")->fetchAll(PDO::FETCH_ASSOC);
$res['main'] = [];
$prev = 0;
$paid = [];
foreach ($storys as $story) {
    if ($prev === 0 || $prev !== $story['pay']) {
        if ($story['pay'] > 49) {
            $prev = $story['pay'];
            $paid = array_filter($pays, function ($pay) use ($story) {return $pay['sid'] === $story['id']; });
            if (count($paid)) {//支払済
                $story['pay'] = 0;
            } else {//有料制限
                $story['txt'] = '';
                $story['media'] = '';
            }
        } else {//制限なし
            $prev = 0;
            $paid = [];
        }
        $res['main'][] = $story;
    } elseif (count($paid)) {
        $story['pay'] = 0;
        $res['main'][] = $story;
    }
}
$res['upd'] = $db->query("SELECT MAX(upd) as upd,MAX(rev) as rev FROM t21story WHERE rid=$rid;")->fetch();
echo json_encode($res);

/*
$sql = "SELECT id,txt,media,idx,pay FROM t21story WHERE rid=$rid AND pay=0
UNION SELECT 0,'','',MIN(idx) AS idx,pay FROM t21story GROUP BY rid,id,pay HAVING rid=$rid AND pay>0
UNION SELECT id,txt,media,idx,0 FROM t21story INNER JOIN t12storypay ON t21story.rid=t12storypay.rid AND
t21story.id=t12storypay.sid AND t12storypay.uid='$uid' WHERE t21story.rid=$rid AND pay>0 ORDER BY 4;";


*/

<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/sys/dbinit.php';
$uid = htmlspecialchars($_GET['uid']);
$rid = htmlspecialchars($_GET['rid']);
$sql = "SELECT id,txt,media,idx,pay FROM t21story WHERE rid=$rid AND pay=0 
UNION SELECT 0,'','',MIN(idx) AS idx,pay FROM t21story GROUP BY rid,id,pay HAVING rid=$rid AND pay >0 
UNION SELECT id,txt,media,idx,0 FROM t21story INNER JOIN t52storypaid ON t21story.rid=t52storypaid.rid AND 
t21story.id=t52storypaid.sid AND t52storypaid.uid='$uid' WHERE t21story.rid=$rid AND pay > 0 ORDER BY 4;";
$res['main'] = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
$res['upd'] = $db->query("SELECT MAX(upd) as upd,MAX(rev) as rev FROM t21story WHERE rid=$rid;")->fetch();
echo json_encode($res);

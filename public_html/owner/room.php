<?php

require_once __DIR__.'/../sys/dbinit.php';
$uid = 'AMavP9Icrfe7GbbMt0YCXWFWIY42'; //$_GET['uid'];
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
} else {
    $sql = "SELECT t01room.id AS id,na,discription,parent,folder,t01room.idx AS idx,chat,contents,auth,plan,prorate,amount,billing_day,
    trial_days,auth_days FROM t01room LEFT JOIN t71roomauth ON t01room.id = t71roomauth.rid AND t71roomauth.uid='$uid' 
    LEFT JOIN t13plan ON t01room.plan = t13plan.id ORDER BY t01room.idx;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

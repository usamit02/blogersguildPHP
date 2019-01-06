<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_POST['sql'])) {
    $error = 0;
    $sql = explode(";\r\n", $_POST['sql']);
    $db->beginTransaction();
    foreach ($sql as $s) {
        $ps = $db->prepare($s);
        $e = $ps->execute();
        $c = $ps->rowCount();
        //$error += (($ps->execute()) && $ps->rowCount() === 1) ? 0 : 1;
        $error += ($e && $c === 1) ? 0 : 1;
    }
    if ($error) {
        $db->rollBack();
        $res['msg'] = 'error';
    } else {
        $db->commit();
        $res['msg'] = 'ok';
    }
} elseif (isset($_GET['rid'])) {
    $rid = htmlspecialchars($_GET['rid']);
    $sql = "SELECT id,txt,media,pay FROM t21story WHERE rid=$rid ORDER BY idx;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
echo json_encode($res);

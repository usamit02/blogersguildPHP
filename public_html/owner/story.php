<?php

require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['rid'])) {
    $rid = htmlspecialchars($_GET['rid']);
    $sql = "SELECT id,txt,media FROM t21story WHERE rid=$rid ORDER BY idx;";
    $res = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

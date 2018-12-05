<?php

$f = fopen(__DIR__.'/../../private_ini/mysql.ini', 'r');
//$f = fopen(__DIR__.'/../../../private_ini/mysql_online.ini', 'r');
$dsn = 'mysql:host='.trim(fgets($f)).';charset=utf8';
$user = trim(fgets($f));
$password = trim(fgets($f));
$dbname = trim(fgets($f));
$dsn = $dsn.';dbname='.$dbname;
fclose($f);
$db = new PDO($dsn, $user, $password);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);

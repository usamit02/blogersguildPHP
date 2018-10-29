<?php
$f = fopen(__DIR__.'/../../private_ini/pay.ini','r');
$clientID = trim(fgets($f));
$clientSecret = trim(fgets($f));
$payID=trim(fgets($f));
$paySecret=trim(fgets($f));
fclose($f);
?>
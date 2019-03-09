<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';

echo json_encode($res);

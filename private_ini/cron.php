<?php

function getHolder($holder)
{
}
$f = fopen(__DIR__.'/mysql.ini', 'r');
$dsn = 'mysql:host='.trim(fgets($f)).';charset=utf8';
$user = trim(fgets($f));
$password = trim(fgets($f));
$dbname = trim(fgets($f));
$dsn = $dsn.';dbname='.$dbname;
fclose($f);
$db = new PDO($dsn, $user, $password);
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, 0);
$D = intval(date('d', strtotime('-3 day')));
if ($D === 1) {
    $yesterday = intval(date('d', strtotime('-4 day')));
    if ($yesterday === 31) {
        $days = '1';
    } elseif ($yesterday === 30) {
        $days = '1,31';
    } elseif ($yesterday === 29) {
        $days = '1,30,31';
    } elseif ($yesterday === 28) {
        $days = '1,29,30,31';
    } else {
        echo "internal error! unknown today.\n";

        return;
    }
} else {
    $days = $D;
}
$error = 0;
$rs = $db->query("SELECT rid,uid,amount FROM q11roompay WHERE bill_day IN($days);");
$db->beginTransaction();
while ($r = $rs->fetch()) {
    $ps = $db->prepare('INSERT INTO t55roombill (rid,uid,upd,amount) VALUES (?,?,?,?);');
    $error += $ps->execute(array($r['rid'], $r['uid'], date('Y-m-d'), $r['amount'])) && $ps->rowCount() === 1 ? 0 : 1;
    if (isset($bill[$r['rid']])) {
        $bill[$r['rid']] += $r['amount'];
    } else {
        $bill[$r['rid']] = $r['amount'];
    }
}
if ($error) {
    echo "database error! cant insert t55roombill.\n";
    $db->rollBack();

    return;
}
$db->commit();
echo"t55roombill ok\n";
if (!isset($bill)) {
    return;
}
$rooms = '';
foreach ($bill as $room => $amount) {
    $rooms .= "$room,";
}
$rooms = substr($rooms, 0, strlen($rooms) - 1);
$staffs = $db->query("SELECT rid,uid,rate,auth FROM t03staff WHERE rid IN($rooms);")->fetchAll(PDO::FETCH_ASSOC);
foreach ($bill as $room => $income) {
    $error = 0;
    $staff = array_filter($staffs, function ($s) use ($room) {return $s['rid'] === $room; });
    if (!count($staff)) {//部屋に役員がいないときは上層の部屋の役員を探す
        $parent = $room;
        do {
            $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchColumn();
            $staff = $db->query("SELECT rid,uid,rate,auth FROM t03staff WHERE rid=$parent;")->fetchAll(PDO::FETCH_ASSOC);
        } while ($parent && !count($staff));
        if (!count($staff)) {
            echo"internal error! No staff in all parents of room no.$room";

            return;
        }
    }
    $holder = array_filter($staff, function ($s) {return $s['rate'] > 0; });
    if (!count($holder)) {//レート設定されていない
        $highauth = 100;
        foreach ($staff as $s) {
            if ($s['auth'] > $highauth) {
                $highauth = $s['auth'];
            }
        }//部屋の最高職位で頭割り
        $holder = array_filter($staff, function ($s) use ($highauth) {return $s['auth'] === $highauth; });
    }
    $num = count($holder);
    if ($num) {
        $sum = array_sum(array_column($holder, 'rate'));
        $db->beginTransaction();
        foreach ($holder as $h) {
            $rate = $sum ? $h['rate'] / $sum : 1 / $num;
            $amount = floor($income * $rate);
            $ps = $db->prepare('INSERT INTO t56roomdiv (rid,uid,upd,amount) VALUES (?,?,?,?);');
            $error += $ps->execute(array($room, $h['uid'], date('Y-m-d'), $amount)) && $ps->rowCount() === 1 ? 0 : 1;
            $ps = $db->prepare('UPDATE t02user SET p=p+? WHERE id=?');
            $error += $ps->execute(array($amount, $h['uid'])) && $ps->rowCount() === 1 ? 0 : 1;
        }
        if ($error) {
            echo "database error! cant insert t56roomdiv on room no.$room\n";
            $db->rollBack();

            return;
        }
        $db->commit();
        echo"t56roomdiv on room no.$room ok\n";
    }
}

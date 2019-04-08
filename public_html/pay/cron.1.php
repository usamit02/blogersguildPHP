<?php

function addMonth($date, $add_month)
{
    $year = date('Y', strtotime($date));
    $month = date('n', strtotime($date));
    $day = date('j', strtotime($date));
    if ($month + $add_month > 12) {// 年を跨ぐ場合
        ++$year;
        $month = $month + $add_month - 12;
    } else {
        $month = $month + $add_month;
    }
    if (checkdate($month, $day, $year)) {// 算出結果の日付を返す
        return date('Y-m-d', strtotime(sprintf('%04d-%02d-%02d', $year, $month, $day)));
    } else { // 2月31日などになった場合、月末の日付を返す
        return date('Y-m-d', strtotime(sprintf('%04d-%02d-01 -1 day', $year, ($month + 1))));
    }
}
function dayDiff($from, $to)
{
    return (strtotime(date('Y-m-d', strtotime($to) - strtotime($from))) - strtotime('1970-01-01')) / 86400;
}
function staff($rid, $db)
{
    global $allStaffs;
    $r = $db->query("SELECT uid,auth,rate,parent FROM t03staff JOIN t01room ON t03staff.rid=t01room.id 
   WHERE t03staff.rid=$rid;")->fetchAll(PDO::FETCH_ASSOC);
    if (count($r)) {
        $allStaffs = $r;
    } else {
        if ($r['parent']) {
            staff($r['parent'], $db);
        }
    }
}
require_once __DIR__.'/../sys/dbinit.php';
require_once __DIR__.'/payjp/init.php';
require_once __DIR__.'/payinit.php';
try {
    Payjp\Payjp::setApiKey($paySecret);
} catch (Exception $e) {
    die('pay.jpの初期化に失敗しました。');
}
$upd = $db->query('SELECT MAX(upd) AS upd FROM t56roomdiv;')->fetchcolumn();
if ($upd) {
    $startDay = date('Y-m-d', strtotime($upd.' +1 days'));
} else {
    $startDay = addMonth(date('Y-m-d'), -1); //1か月前
}
$diff = dayDiff($startDay, date('Y-m-d'));
for ($i = 0; $i < $diff; ++$i) {
    $xDay = date('Y-m-d', strtotime($startDay." +$i days"));
    $year = date('Y', strtotime($xDay));
    $month = date('n', strtotime($xDay));
    $day = date('j', strtotime($xDay));
    if (checkdate($month, $day + 1, $year)) {//今月の次の日があるか
        $period = "period='$day'";
    } else {
        $period = "period<='$day'"; //29,30,31日がない月の月末払い分まとめて処理
    }
    $rs = $db->query("SELECT uid,t11roompay.rid AS rid,amount FROM t11roompay JOIN t13plan 
    ON t11roompay.rid=t13plan.rid AND t11roompay.plan=t13plan.id WHERE $period AND paused=0;");
    $error = 0;
    $db->beginTransaction();
    while ($r = $rs->fetch()) {
        $allStaffs = [];
        staff($r['rid'], $db);
        if (count($allStaffs)) {
            $staffs = array_values(array_filter($allStaffs, function ($staff) {
                return $staff['rate'] > 0;
            }));
            if (count($staffs)) {//レート設定有の人数で分配
                $sumRate = 0;
                foreach ($staffs as $staff) {
                    $sumRate += $staff['rate'];
                }
                for ($j = 0; $j < count($staffs); ++$j) {
                    $staffs[$j]['div'] = $staffs[$j]['rate'] / $sumRate;
                }
            } else {//最高職位の人数で頭割り
                $auth = $allStaffs[0]['auth'];
                for ($j = 1; $j < count($allStaffs); ++$j) {
                    if ($auth < $allStaffs[$j]['auth']) {
                        $auth = $allStaffs[$j]['auth'];
                    }
                }
                $staffs = array_values(array_filter($allStaffs, function ($staff) use ($auth) {
                    return $staff['auth'] === $auth;
                }));
                for ($j = 0; $j < count($staffs); ++$j) {
                    $staffs[$j]['div'] = 1 / count($staffs);
                }
            }

            foreach ($staffs as $staff) {
                $ps = $db->prepare('INSERT INTO t56roomdiv (rid,uid,mid,amount,billing_day,upd) VALUES (?,?,?,?,?,?);');
                $error += !$ps->execute(array($r['rid'], $staff['uid'], $r['uid'],
                floor($r['amount'] * $staff['div']), $xDay, date('Y-m-d'), )) && $ps->rowCount() !== 1;
            }
            if ($error) {
                $db->rollBack();
                break;
            }
        }
    }
    if (!$error) {
        $db->commit();
    }
}

//echo date('d', strtotime('2019-03-1 -1 month'));

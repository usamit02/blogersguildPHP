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
function processend($msg)
{
    global $db;
    $mail = $db->query('SELECT mail FROM t02user WHERE no=1;')->fetchcolumn();
    mail($mail, 'cron of guild system', $msg);
    die($msg);
}
require_once __DIR__.'/../sys/dbinit.php';
require_once __DIR__.'/payjp/init.php';
require_once __DIR__.'/payinit.php';
try {
    Payjp\Payjp::setApiKey($paySecret);
} catch (Exception $e) {
    processend('fail to initialize pay.jp');
}
$today = date('Y-m-d');
$upd = $db->query('SELECT MAX(upd) AS upd FROM t11roompay;')->fetchcolumn();
if ($upd) {
    $xday = date('Y-m-d', strtotime($upd));
} else {
    $xday = addMonth($today, -1); //1か月前
}//課金周期が過ぎた定額課金をpayjpと同期
$rs = $db->query("SELECT payjp_id FROM t11roompay WHERE end_day>='$xday' AND end_day <'$today';");
$db->beginTransaction();
while ($r = $rs->fetch()) {
    try {
        $sub = Payjp\Subscription::retrieve($r['payjp_id']);
    } catch (Exception $e) {
        $db->rollback();
        processend($e->getMessage());
    }
    $active = $sub['status'] === 'active' ? 1 : 0;
    $ps = $db->prepare('UPDATE t11roompay SET upd=?,start_day=?,end_day=?,active=? WHERE payjp_id=?;');
    if (!$ps->execute(array(date('Y-m-d H:i:s'), date('Y-m-d', $sub['current_period_start']),
    date('Y-m-d', $sub['current_period_end']), $active, $r['payjp_id'], )) || $ps->rowCount() !== 1) {
        $db->rollBack();
        processend('mysql update error');
    }
}
$db->commit();
$upd = $db->query('SELECT MAX(upd) AS upd FROM t56roomdiv;')->fetchcolumn();
if ($upd) {
    $xday = date('Y-m-d', strtotime($upd));
} else {
    $xday = addMonth($today, -1); //1か月前
}
while (strtotime($xday) < strtotime($today)) {//start_dayに課金した定額課金を分配する
    $rs = $db->query("SELECT uid,t11roompay.rid AS rid,start_day,end_day,amount,prorate FROM t11roompay JOIN t13plan 
    ON t11roompay.rid=t13plan.rid AND t11roompay.plan=t13plan.id WHERE start_day='$xday' AND active=1;");
    $db->beginTransaction();
    while ($r = $rs->fetch()) {
        $parent = $r['rid'];
        do {//部屋にスタッフがいなければ上層部屋のスタッフを探す
            $allStaffs = $db->query("SELECT uid,auth,rate,parent FROM t03staff JOIN t01room 
            ON t03staff.rid=t01room.id WHERE t03staff.rid=$parent;")->fetchAll(PDO::FETCH_ASSOC);
            if (count($allStaffs)) {
                break;
            } else {
                $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchcolumn();
            }
        } while ($parent);
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
            if ($r['prorate'] && strtotime($r['end_day']) < strtotime(addMonth($r['start_day'], 1))) {//日割り計算
                $monthday = dayDiff($r['start_day'], addMonth($r['start_day'], 1));
                $countday = dayDiff($r['start_day'], $r['end_day']);
                $amount = floor($r['amount'] * $countday / $monthday);
            } else {
                $amount = $r['amount'];
            }
            foreach ($staffs as $staff) {
                $p = floor($amount * $staff['div']);
                $ps = $db->prepare('INSERT INTO t56roomdiv (rid,uid,mid,amount,billing_day,upd) VALUES (?,?,?,?,?,?);');
                $ps1 = $ps->execute(array($r['rid'], $staff['uid'], $r['uid'], $p, $xday, date('Y-m-d H:i:s'))) && $ps->rowCount() === 1;
                $ps = $db->prepare('UPDATE t02user SET p=p+? WHERE id=?;');
                $ps2 = $ps->execute(array($p, $staff['uid'])) && $ps->rowCount() === 1;
                if (!($ps1 && $ps2)) {
                    $db->rollBack();
                    processend("division amount on $xday due to mysql insert or update error");
                }
            }
        }
    }
    $db->commit();
    $xday = date('Y-m-d', strtotime("$xday +1 day"));
}
processend("sync with payjp and division amount process on $today was successful");
//echo date('d', strtotime('2019-03-1 -1 month'));

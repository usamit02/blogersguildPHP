<?php

header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';
$error = 0;
if (isset($_GET['sql'])) {
    $sqls = explode(";\n", $_GET['sql']);
    $db->beginTransaction();
    foreach ($sqls as $sql) {
        $ps = $db->prepare($sql);
        $error += (($ps->execute()) && $ps->rowCount()) ? 0 : 1;
    }
    if ($error) {
        $db->rollBack();
        $res['msg'] = 'error';
    } else {
        $db->commit();
        $res['msg'] = 'ok';
    }
} elseif (isset($_GET['roomForm'])) {
    $rid = htmlspecialchars($_GET['rid']);
    $na = htmlspecialchars($_GET['na']);
    $data = json_decode($_GET['roomForm'], true);
    $roomSet = '';
    $planWhere = '';
    $planKey = 'id,';
    $planVal = '';
    foreach ($data['room'] as $key => $val) {
        if ($key === 'plan' && !$val) {
            $roomSet .= 'plan=0,';
        } elseif ($key === 'discription') {
            $roomSet .= "$key='$val',";
        } else {
            $roomSet .= "$key=$val,";
        }
    }
    foreach ($data['plan'] as $key => $val) {
        if (!isset($val)) {
            $val = 0;
        }
        $planWhere .= "$key=$val AND ";
        $planKey .= "$key,";
        $planVal .= "$val,";
    }
    if ($planWhere) {
        $sql = "SELECT id FROM t13plan WHERE rid=$rid AND ".substr($planWhere, 0, strlen($planWhere) - 5);
        $planId = $db->query($sql)->fetchcolumn();
        if ($planId) {
            $planKey = '';
            $planVal = '';
        } else {
            $planId = $db->query("SELECT MAX(id)+1 AS maxId FROM t13plan WHERE rid=$rid;")->fetchcolumn();
            $planId = is_null($planId) ? 1 : $planId;
            $error += $planId ? 0 : 1;
            $planVal = "$planId,$planVal";
        }
        $roomSet .= "plan=$planId,";
    }
    $db->beginTransaction();
    if ($roomSet && !$error) {
        $ps = $db->prepare('UPDATE t01room SET '.substr($roomSet, 0, strlen($roomSet) - 1)." WHERE id=$rid;");
        $error += (($ps->execute()) && $ps->rowCount() === 1) ? 0 : 1;
    }
    if ($planVal && !$error) {
        $ps = $db->prepare('INSERT INTO t13plan (rid,'.substr($planKey, 0, strlen($planKey) - 1).") VALUES ($rid,"
        .substr($planVal, 0, strlen($planVal) - 1).');');
        $error += (($ps->execute()) && $ps->rowCount() === 1) ? 0 : 1;
        if (!$error) {
            require_once __DIR__.'/../pay/payjp/init.php';
            require_once __DIR__.'/../pay/payinit.php';
            try {
                Payjp\Payjp::setApiKey($paySecret);
            } catch (Exception $e) {
                $res['msg'] = 'pay.jpの初期化に失敗しました。';
            }
            if (!isset($res)) {
                $plan = array('id' => "blg$rid"."_$planId", 'amount' => $data['plan']['amount'],
                'currency' => 'jpy', 'interval' => 'month', 'name' => $na, );
                if ($data['plan']['billing_day']) {
                    $plan += array('billing_day' => $data['plan']['billing_day']);
                }
                if ($data['plan']['trial_days'] || $data['plan']['auth_days']) {
                    $trial_days = isset($data['plan']['trial_days']) ? $data['plan']['trial_days'] : 0;
                    $trial_days += isset($data['plan']['auth_days']) ? $data['plan']['auth_days'] : 0;
                    $plan += array('trial_days' => $trial_days);
                }
                try {
                    $result = Payjp\Plan::create($plan);
                    if (isset($result['error'])) {
                        $res['msg'] = "payjpの定期課金プラン作成に失敗しました。\r\n".$result['error']['message'];
                    } elseif (isset($result['id'])) {
                        $ps = $db->prepare('UPDATE t13plan SET payjp_id=? WHERE rid=? AND id=?;');
                        $ps->execute(array($result['id'], $rid, $planId));
                    }
                } catch (Exception $e) {
                    $res['msg'] = $e->getMessage();
                }
            }
        }
    }
    if ($error) {
        $db->rollBack();
        $res['msg'] = 'データーベースエラー';
    } elseif (isset($res)) {
        $db->rollBack();
    } else {
        $db->commit();
        $res['msg'] = 'ok';
        if ($planVal) {//現在課金のないプランを掃除、失敗しても続行。課題：退会などでstatus=canceledの定額課金だけが残っていると失敗する。
            $rs = $db->query("SELECT t13plan.id AS id,t13plan.payjp_id AS payjp_id FROM t13plan 
            LEFT JOIN t11roompay ON t13plan.rid=t11roompay.rid AND t13plan.id=t11roompay.plan 
            WHERE t13plan.rid=$rid AND t13plan.id<>$planId AND t11roompay.uid IS NULL;");
            while ($r = $rs->fetch()) {
                try {
                    $del = Payjp\Plan::retrieve($r['payjp_id']);
                    $del->delete();
                    if (isset($del['id'])) {
                        $ps = $db->prepare('DELETE FROM t13plan WHERE rid=? AND id=? AND payjp_id=?;');
                        $ps->execute(array($rid, $r['id'], $del['id']));
                    }
                } catch (Exception $e) {
                }
            }
        }
    }
}
echo json_encode($res);
/*

        $res = array_filter($roomKeys, function ($roomKey) use ($key) {return $key === $roomKey; });
        if (count($res)) {
            $sql += "UPDATE t01room SET ";
            foreach ($res as $r) {
                $val .= "$key=$data,";
            }
        }
    }



*/

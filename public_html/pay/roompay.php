<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer) && isset($_GET['uid']) && isset($_GET['rid']) && isset($_GET['ok']) || isset($_GET['ban'])) {
    require_once __DIR__.'/payjp/init.php';
    require_once __DIR__.'/payinit.php';
    try {
        Payjp\Payjp::setApiKey($paySecret);
    } catch (Exception $e) {
        $json['error'] = 'pay.jpの初期化に失敗しました。';
    }
    if (!isset($json)) {
        require_once __DIR__.'/../sys/dbinit.php';
        $url = parse_url($referer);
        $rid = htmlspecialchars($_GET['rid']);
        $uid = htmlspecialchars($_GET['uid']);
        $ban_uid = isset($_GET['ban']) ? htmlspecialchars($_GET['ban']) : false;
        $payjp_id = $db->query("SELECT payjp_id FROM t11roompay WHERE uid='$uid' AND rid=$rid;")->fetchcolumn();
        $error = 0;
        if (isset($_GET['ok']) && isset($payjp_id) && $payjp_id) {
            $ok_uid = htmlspecialchars($_GET['ok']);
            $trial = $db->query("SELECT trial_days FROM t01room JOIN t13plan ON t01room.id=t13plan.rid AND 
            t01room.plan=t13plan.id WHERE t01room.id=$rid;")->fetchcolumn();
            $trial_end = $trial ? strtotime(date('Y-m-d H:i:s', strtotime("+$trial day"))) : 'now';
            try {
                $sub = Payjp\Subscription::retrieve($payjp_id);
                $sub->trial_end = $trial_end;
                $sub->save();
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
            if (!isset($json) && isset($sub['id']) && isset($sub['status'])) {
                if ($sub['status'] === 'paused') {//引き落とし不能
                    $ban_uid = $uid;
                } else {
                    $ps = $db->prepare('UPDATE t11roompay SET start_day=?,end_day=?,ok_uid=?,active=? WHERE payjp_id=?;');
                    $start_day = date('Y-m-d', $sub['current_period_start']);
                    $end_day = date('Y-m-d', $sub['current_period_end']);
                    $active = $sub['status'] === 'active' || $sub['status'] === 'trial' && $trial ? 1 : 0;
                    if ($ps->execute(array($start_day, $end_day, $ok_uid, $active, $sub['id'])) && $ps->rowCount() === 1) {
                        $json['msg'] = 'ok';
                    } else {
                        $json['error'] = "定額課金の変更に成功しましたが、データーベース変更に失敗しました。\r\nC-Lifeまでお問合せください。";
                    }
                }
            } else {
                $json['error'] = '定額課金の変更に失敗しました。';
            }
        }
        if ($ban_uid && isset($payjp_id) && $payjp_id) {
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
            $error += !$ps->execute(array($uid, $rid, $payjp_id, $ban_uid, date('Y-m-d H:i:s'))) && $ps->rowCount() !== 1;
            $ps = $db->prepare("DELETE FROM t11roompay WHERE uid='$uid' AND rid=$rid AND payjp_id='$payjp_id';");
            $error += !$ps->execute() && $ps->rowCount() !== 1;
            if (!$error) {
                try {
                    $sub = Payjp\Subscription::retrieve($payjp_id);
                    $sub->delete();
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($sub['id'])) {
                    $db->commit();
                    $json['msg'] = 'ok';
                } else {
                    $db->rollBack();
                    $json['error'] = '定額課金の削除に失敗しました。';
                }
            } else {
                $db->rollBack();
                $json['error'] = 'データーベースエラーのため削除できません。';
            }
        } else {
            $json['error'] = 'パラメーターが不正です。';
        }
    } else {
        $json['error'] = 'トークンがセットされていない';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
echo json_encode($json);
/*
$ok_uid = htmlspecialchars($_GET['ok']);
            $trial = $db->query("SELECT trial_days FROM t01room JOIN t13plan ON t01room.id=t13plan.rid AND t01room.plan=t13plan.id WHERE t01room.id=$rid;")->fetchcolumn();
            $trial_end = $trial ? strtotime(date('Y-m-d H:i:s', strtotime("+$trial day"))) : 'now';
            $db->beginTransaction();
            $ps = $db->prepare('UPDATE t11roompay SET start_day=?,ok_uid=? WHERE payjp_id=?;');
            $error += !$ps->execute(array(date('Y-m-d H:i:s'), $ok_uid, $payjp_id)) && $ps->rowCount() !== 1;
            if (!$error) {
                try {
                    $sub = Payjp\Subscription::retrieve($payjp_id);
                    $sub->trial_end = $trial_end;
                    $sub->save();
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($sub['id'])) {
                    $db->commit();
                    $json['msg'] = 'ok';
                } else {
                    $db->rollBack();
                    $json['error'] = '定額課金の変更に失敗しました。';
                }
            } else {
                $db->rollBack();
                $json['error'] = 'データーベースエラーのためOKできません。';
            }
*/

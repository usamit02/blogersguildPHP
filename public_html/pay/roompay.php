<?php

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
        $payjp_id = $db->query("SELECT payjp_id FROM t11roompay WHERE uid='$uid' AND rid='$rid';")->fetchcolumn();
        $error = 0;
        if (isset($_GET['ok']) && isset($payjp_id) && $payjp_id) {
            $ok_uid = htmlspecialchars($_GET['ok']);
            $trial = $db->query("SELECT trial_days FROM t01room JOIN t13plan ON t01room.plan=t13plan.id WHERE t01room.id=$rid;")->fetchcolumn();
            $trial_end = $trial ? strtotime(date('Y-m-d H:i:s', strtotime("+$trial day"))) : 'now';
            $db->beginTransaction();
            $ps = $db->prepare('UPDATE t11roompay SET start_day=?,ok_uid=? WHERE payjp_id=?;');
            $error += !$ps->execute(array(date('Y-m-d H:i:s'), $ok_uid, $payjp_id)) && $ps->rowCount() !== 1;
            if (!$error) {
                try {
                    $res = Payjp\Subscription::retrieve($payjp_id);
                    $res->trial_end = $trial_end;
                    $res->save();
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($res['id'])) {
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
        } elseif (isset($_GET['ban']) && isset($payjp_id) && $payjp_id) {
            $ban_uid = htmlspecialchars($_GET['ban']);
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
            $error += !$ps->execute(array($uid, $rid, $payjp_id, $ban_uid, date('Y-m-d H:i:s'))) && $ps->rowCount() !== 1;
            $ps = $db->prepare("DELETE FROM t11roompay WHERE uid='$uid' AND rid=$rid;");
            $error += !$ps->execute() && $ps->rowCount() !== 1;
            if (!$error) {
                try {
                    $res = Payjp\Subscription::retrieve($payjp_id);
                    $res->delete();
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($res['id'])) {
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
header('Access-Control-Allow-Origin: *');
echo json_encode($json);

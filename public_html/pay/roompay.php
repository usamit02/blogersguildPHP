<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer) && isset($_GET['uid']) && isset($_GET['rid'])) {
    require_once __DIR__.'/payjp/init.php';
    require_once __DIR__.'/payinit.php';
    try {
        Payjp\Payjp::setApiKey($paySecret);
    } catch (Exception $e) {
        $json['error'] = "pay.jpの初期化に失敗しました。\r\nC-Lifeまでお問合せください。";
    }
    if (!isset($json)) {
        require_once __DIR__.'/../sys/dbinit.php';
        $url = parse_url($referer);
        $rid = htmlspecialchars($_GET['rid']);
        $uid = htmlspecialchars($_GET['uid']);
        $ban_uid = isset($_GET['ban']) ? htmlspecialchars($_GET['uid']) : false;
        if (isset($_GET['ok'])) {
            $payjp_id = $db->query("SELECT payjp_id FROM t11roompay WHERE uid='$uid' AND rid=$rid;")->fetchcolumn();
            if ($payjp_id) {
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
                    $json['error'] = "定額課金の変更に失敗しました。\r\nC-Lifeまでお問合せください。";
                }
            } else {
                $json['error'] = '部屋が見つかりません。';
            }
        }//引き落とし不能の場合今作った定額課金を以下ですぐ消す
        if ($ban_uid) {//課題：startdayと同日、cronで同期してupdされていないうちに削除すると、支払いが配分されない。
            $rs = $db->query("SELECT rid,payjp_id FROM t11roompay WHERE uid='$uid';");
            while ($r = $rs->fetch()) {//削除部屋の下層に参加している部屋があれば同時に削除する
                $parent = $r['rid'];
                $execute = false;
                do {//親に削除部屋があれば削除対象
                    if ($parent == $rid) {
                        $execute = true;
                        break;
                    }
                    $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchcolumn();
                } while ($parent);
                if ($execute) {
                    $db->beginTransaction();
                    $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
                    $ps1 = $ps->execute(array($uid, $r['rid'], $r['payjp_id'], $ban_uid, date('Y-m-d H:i:s'))) && $ps->rowCount() === 1;
                    $ps = $db->prepare('DELETE FROM t11roompay WHERE uid=? AND rid=? AND payjp_id=?;');
                    $ps2 = $ps->execute(array($uid, $r['rid'], $r['payjp_id'])) && $ps->rowCount() === 1;
                    if ($ps1 && $ps2) {
                        try {
                            $sub = Payjp\Subscription::retrieve($r['payjp_id']);
                            $sub->cancel();
                        } catch (Exception $e) {
                            $json['error'] = $e->getMessage();
                        }
                        if (isset($sub['id'])) {
                            $db->commit();
                            $json['msg'] = 'ok';
                        } else {
                            $db->rollBack();
                            $json['error'] = "定額課金の削除に失敗しました。\r\nC-Lifeまでお問合せください。";
                            break;
                        }
                    } else {
                        $db->rollBack();
                        $json['error'] = "データーベースエラーのため削除できません。\r\nC-Lifeまでお問合せください。";
                        break;
                    }
                }
            }
            if (!isset($json)) {
                $json['error'] = '退会する部屋が見つかりません。既に退会していませんか。。';
            }
        } else {
            $json['error'] = 'パラメータが不足しています。';
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

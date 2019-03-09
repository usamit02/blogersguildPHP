<?php

function addRooms($parent, $rooms)
{
    $res = [];
    $childs = array_filter($rooms, function ($room) use ($parent) {
        return $room['parent'] === $parent;
    });
    $res = array_merge($res, $childs);
    foreach ($childs as $child) {
        $res = array_merge($res, addRooms($child['id'], $rooms));
    }

    return $res;
}
header('Access-Control-Allow-Origin: *');
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['sql']) && (isset($_SERVER['HTTP_REFERER']))) {
    $error = 0;
    $res['msg'] = 'ok';
    if (isset($_GET['dels']) && isset($_GET['uid'])) {
        $dels = json_decode(htmlspecialchars($_GET['dels']));
        $uid = htmlspecialchars($_GET['uid']);
        require_once __DIR__.'/../pay/payjp/init.php';
        require_once __DIR__.'/../pay/payinit.php';
        try {
            Payjp\Payjp::setApiKey($paySecret);
        } catch (Exception $e) {
            $res['msg'] = 'pay.jpの初期化に失敗しました。';
        }
        foreach ($dels as $rid) {
            $rs = $db->query("SELECT uid,payjp_id FROM t11roompay WHERE rid=$rid;");
            while ($r = $rs->fetch()) {
                $db->beginTransaction();
                $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
                $error += !$ps->execute(array($r['uid'], $rid, $r['payjp_id'], $uid, date('Y-m-d H:i:s'))) && $ps->rowCount() !== 1;
                $ps = $db->prepare('DELETE FROM t11roompay WHERE uid=? AND rid=?;');
                $error += !$ps->execute(array($r['uid'], $rid)) && $ps->rowCount() !== 1;
                if (!$error && $res['msg'] === 'ok') {
                    try {
                        $sub = Payjp\Subscription::retrieve($payjp_id);
                        $sub->delete();
                    } catch (Exception $e) {
                        $res['msg'] = $e->getMessage();
                    }
                    if (isset($sub['id']) && $res['msg'] === 'ok') {
                        $db->commit();
                    } else {
                        $db->rollBack();
                        $res['msg'] = 'pay.jp定額課金の削除に失敗しました。';
                        break 2;
                    }
                } else {
                    $db->rollBack();
                    $res['msg'] = 'pay.jp定額課金削除データベースエラーが発生しました。';
                    break 2;
                }
            }
            $rs = $db->query("SELECT id,payjp_id FROM t13plan WHERE rid=$rid;");
            while ($r = $rs->fetch()) {
                $db->beginTransaction();
                $ps = $db->prepare('DELETE FROM t13plan WHERE id=? AND rid=?;');
                $error += !$ps->execute(array($r['id'], $rid)) && $ps->rowCount() !== 1;
                if (!$error && $res['msg'] === 'ok') {
                    try {
                        $a = $r['payjp_id'];
                        $plan = Payjp\Plan::retrieve($r['payjp_id']);
                        $plan->delete();
                    } catch (Exception $e) {
                        $res['msg'] = $e->getMessage();
                    }
                    if (isset($plan['id']) && $res['msg'] === 'ok') {
                        $db->commit();
                    } else {
                        $db->rollBack();
                        $res['msg'] = '課金の削除には成功しましたが、pay.jpプランの削除に失敗しました。';
                        break 2;
                    }
                } else {
                    $db->rollBack();
                    $res['msg'] = '課金の削除には成功しましたが、pay.jpプラン削除データベースエラー発生しました。';
                    break 2;
                }
            }
            if (!$error && $res['msg'] === 'ok') {
                $delroom = $db->query("SELECT parent,idx FROM t01room WHERE id=$rid;")->fetch();
                $db->beginTransaction();
                $ps = $db->prepare('DELETE FROM t03staff WHERE rid=?;');
                $error += !$ps->execute(array($rid));
                $ps = $db->prepare('DELETE FROM t12bookmark WHERE rid=?;');
                $error += !$ps->execute(array($rid));
                $ps = $db->prepare('DELETE FROM t14roomcursor WHERE rid=?;');
                $error += !$ps->execute(array($rid));
                $ps = $db->prepare('DELETE FROM t21story WHERE rid=?;');
                $error += !$ps->execute(array($rid));
                $ps = $db->prepare('DELETE FROM t01room WHERE id=?;');
                $error += !$ps->execute(array($rid));
                $ps = $db->prepare('UPDATE t01room SET parent=?,idx=idx+? WHERE parent=?;');
                $error += !$ps->execute(array($delroom['parent'], $delroom['idx'], $rid));
                if (!$error) {
                    $db->commit();
                    $path = __DIR__."/../media/$rid";
                    system("rm -rf {$path}");
                } else {
                    $db->rollBack();
                    $res['msg'] = '課金関係の削除に成功しましたが、データベースを削除できませんでした。';
                    break;
                }
            }
        }
    }
    if (!$error && $res['msg'] === 'ok' && $_GET['sql']) {
        $sql = explode("\n", $_GET['sql']);
        $db->beginTransaction();
        foreach ($sql as $s) {
            $ps = $db->prepare($s);
            $error += (($ps->execute())) ? 0 : 1;
        }
        if ($error) {
            $db->rollBack();
            $res['msg'] = 'データベースエラーが発生しました。';
        } else {
            $db->commit();
        }
    }
} elseif (isset($_GET['parent'])) {
    $res['maxId'] = 0;
    $maxId = $db->query('SELECT MAX(id) FROM t01room;')->fetchcolumn();
    if ($maxId) {
        ++$maxId;
        $parent = (int) $_GET['parent'];
        $ps = $db->prepare('INSERT INTO t01room SET id=?,na=?,parent=?;');
        if ($ps->execute(array($maxId, '新しい部屋', $parent)) && $ps->rowCount() === 1) {
            $res['maxId'] = $maxId;
        }
    }
} elseif (isset($_GET['uid'])) {
    $uid = htmlspecialchars($_GET['uid']);
    $sql = "SELECT t01room.id AS id,na,discription,parent,folder,t01room.idx AS idx,chat,story,auth,plan,prorate,amount,billing_day,
    trial_days,auth_days FROM t01room LEFT JOIN t03staff ON t01room.id = t03staff.rid AND t03staff.uid='$uid' 
    LEFT JOIN t13plan ON t01room.id = t13plan.rid AND t01room.plan = t13plan.id ORDER BY t01room.idx;";
    $rooms = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $authRooms = array_filter($rooms, function ($room) {
        return $room['auth'] >= 100 || $room['id'] === 0;
    });
    $res = [];
    foreach ($authRooms as $i => $room) {
        $root = true;
        $parent = $room['parent'];
        while (isset($parent)) {
            $key = array_search($parent, array_column($rooms, 'id'));
            if ($key !== false) {
                $id = $rooms[$key]['id'];
                if (count(array_filter($authRooms, function ($r) use ($id) {
                    return $r['id'] === $id;
                }))) {
                    $root = false;
                }
                $parent = $rooms[$key]['parent'];
            } else {
                $parent = null;
            }
        }
        if ($root) {
            $res[][0] = $room;
        }
    }
    foreach ($res as $i => $room) {
        $res[$i] = array_merge($res[$i], addRooms($room[0]['id'], $rooms));
    }
}
echo json_encode($res);

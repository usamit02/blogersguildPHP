<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER']; //uidならユーザーBAN,ridなら部屋解散
if (isset($referer) && (isset($_GET['uid']) || isset($_GET['rid'])) && isset($_GET['ban'])) {
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
        $uid = isset($_GET['uid']) ? htmlspecialchars($_GET['uid']) : false;
        $rid = isset($_GET['rid']) ? htmlspecialchars($_GET['rid']) : false;
        $ban = htmlspecialchars($_GET['ban']);
        $sql = $uid ? "SELECT payjp_id,rid FROM t11roompay WHERE uid='$uid';" :
                    "SELECT payjp_id,uid FROM t11roompay WHERE rid=$rid;";
        $rs = $db->query($sql);
        while ($r = $rs->fetch()) {
            $payjp_id = $r['payjp_id'];
            $r_id = $rid ? $rid : $r['rid'];
            $u_id = $uid ? $uid : $r['uid'];
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
            $ps1 = $ps->execute(array($u_id, $r_id, $payjp_id, $ban, date('Y-m-d H:i:s'))) && $ps->rowCount() === 1;
            $ps = $db->prepare('DELETE FROM t11roompay WHERE uid=? AND rid=? AND payjp_id=?;');
            $ps2 = $ps->execute(array($u_id, $r_id, $payjp_id)) && $ps->rowCount() === 1;
            if ($ps1 && $ps2) {
                try {
                    $sub = Payjp\Subscription::retrieve($payjp_id);
                    $sub->cancel();
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($sub['id'])) {
                    $db->commit();
                } else {
                    $db->rollBack();
                    $json['error'] = '定額課金の削除に失敗しました。';
                }
            } else {
                $db->rollBack();
                $json['error'] = 'データーベースエラーのため削除できません。';
            }
            if (isset($json)) {
                break;
            }
        }
        if (!isset($json)) {
            $json['msg'] = 'ok';
        }
    } else {
        $json['error'] = 'トークンがセットされていない';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
echo json_encode($json);

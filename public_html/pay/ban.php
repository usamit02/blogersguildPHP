<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer) && isset($_GET['uid']) && isset($_GET['ban'])) {
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
        $uid = htmlspecialchars($_GET['uid']);
        $ban = htmlspecialchars($_GET['ban']);
        $rs = $db->query("SELECT payjp_id,rid FROM t11roompay WHERE uid='$uid';");
        $error = 0;
        while ($r = $rs->fetch() && !$error) {
            $payjp_id = $r['payjp_id'];
            $rid = $r['rid'];
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,end_day) VALUES (?,?,?,?,?);');
            $error += !$ps->execute(array($uid, $rid, $payjp_id, $ban, date('Y-m-d H:i:s'))) && $ps->rowCount() !== 1;
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
                } else {
                    $db->rollBack();
                    $json['error'] = '定額課金の削除に失敗しました。';
                }
            } else {
                $db->rollBack();
                $json['error'] = 'データーベースエラーのため削除できません。';
            }
            $error += isset($json) ? 1 : 0;
        }
        if (!isset($json) && !$error) {
            $json['msg'] = 'ok';
        }
    } else {
        $json['error'] = 'トークンがセットされていない';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
echo json_encode($json);

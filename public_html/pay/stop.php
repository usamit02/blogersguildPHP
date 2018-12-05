<?php

$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer) && isset($_GET['uid']) && isset($_GET['rid'])) {
    $url = parse_url($referer);
    $rid = htmlspecialchars($_GET['rid']);
    $uid = htmlspecialchars($_GET['uid']);
    require_once __DIR__.'/../sys/dbinit.php';
    require_once __DIR__.'/payjp/init.php';
    require_once __DIR__.'/payinit.php';
    try {
        Payjp\Payjp::setApiKey($paySecret);
    } catch (Exception $e) {
        $json['error'] = 'pay.jpの初期化に失敗しました。';
    }
    if (isset($_GET['ban']) && !isset($json)) {
        $ban_uid = htmlspecialchars($_GET['ban']);
        $payjp_id = $db->query("SELECT payjp_id FROM t11roompay WHERE uid='$uid' AND rid='$rid';")->fetch();
        if ($payjp_id) {
            $error = 0;
            $db->beginTransaction();
            $ps = $db->prepare('INSERT INTO t51roompaid(uid,rid,payjp_id,ban_uid,ban_day) VALUES (?,?,?,?,?);');
            $error += !$ps->execute(array($uid, $rid, $payjp_id, $ban_uid, date('Y-m-d H:i:s'))) && $ps->rowCount() !== 1;
            $ps = $db->prepare("DELETE FROM t11roompay WHERE uid='$uid' AND rid=$rid;");
            $error += !$ps->execute() && $ps->rowCount() !== 1;
        }
        if (!$error) {
            try {
                $result = Payjp\Subscription::retrieve($payjp_id);
            } catch (Exception $e) {
                $json['error'] = $e->getMessage();
            }
            if (isset($result['id'])) {
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
    } elseif (!isset($json)) {
        $json['error'] = 'トークンがセットされていない';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
header('Access-Control-Allow-Origin: *');
echo json_encode($json);

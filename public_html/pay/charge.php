<?php

$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer)) {
    $url = parse_url($referer);
    if (isset($_GET['token']) && isset($_GET['uid'])) {
        $token = $_GET['token'];
        $userId = $_GET['uid'];
        $userName = $_GET['na'];
        $room = $_GET['room'];
        require_once __DIR__.'/../sys/dbinit.php';
        require_once __DIR__.'/payjp/init.php';
        require_once __DIR__.'/payinit.php';
        try {
            Payjp\Payjp::setApiKey($paySecret);
        } catch (Exception $e) {
            $json['error'] = 'pay.jpの初期化に失敗しました。';
        }
        $r = $db->query("SELECT id FROM t02user WHERE id='$userId';")->fetch();
        if (!$r) {
            $ps = $db->prepare('INSERT INTO t02user(id,na) VALUES (:id,:na);');
            if (!$ps->execute(array('id' => $userId, 'na' => $userName))) {
                $json['error'] = 'データベースエラーによりユーザーの追加に失敗しました。';
            }
        }
        $plan = $db->query("SELECT plan,trial_days,auth_days FROM t01room JOIN t13plan ON t01room.plan=t13plan.id WHERE t01room.id='$room';")->fetchAll(PDO::FETCH_ASSOC);
        if (!$plan) {
            $json['error'] = '部屋まちがってます。';
        }
        if (!isset($json)) {
            try {
                $result = Payjp\Customer::retrieve($userId);
            } catch (Exception $e) {
            }
            if (!isset($result['id'])) {
                try {
                    $result = Payjp\Customer::create(array('card' => $token, 'id' => $userId, 'description' => $userName));
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($result['id'])) {
                    $userId = $result['id'];
                } else {
                    $json['error'] = 'pay.jpの顧客作成に失敗しました。';
                }
            }
        }
        if (!isset($json)) {
            try {
                $result = Payjp\Subscription::create(array('customer' => $userId, 'plan' => $plan[0]['plan']));
            } catch (Exception $e) {
                $json['error'] = "payjpの定額課金に失敗しました。\r\n".$e->getMessage();
            }
            if (isset($result['id']) && $result['id'] !== $userId) {//既に定額課金があるとpayjpはresult.idにuserIdを返す
                $days = isset($plan[0]['trial_days']) ? $plan[0]['trial_days'] : 0;
                $days += isset($plan[0]['auth_days']) ? $plan[0]['auth_days'] : 0;
                $start_day = $days ? date('Y-m-d H:i:s', strtotime("+$days day")) : date('Y-m-d H:i:s');
                $ps = $db->prepare('INSERT INTO t11roompay(uid,rid,sub_day,start_day,payjp_id) VALUES (?,?,?,?,?);');
                if ($ps->execute(array($userId, $room, date('Y-m-d H:i:s'), $start_day, $result['id'])) && $ps->rowCount() === 1) {
                    $json['msg'] = 'ok';
                } else {
                    $json['error'] = "データベースエラーによりルーム支払データ挿入に失敗しました。\nC-Lifeまでお問合せください。";
                }
            } else {
                $json['error'] = "payjpの定額課金に失敗しました。\nC-Lifeまでお問合せください。";
            }
        }
    } else {
        $json['error'] = 'トークンがセットされていない';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
header('Access-Control-Allow-Origin: *');
echo json_encode($json);

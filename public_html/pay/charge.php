<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer)) {
    $url = parse_url($referer);
    if (isset($_GET['rid']) && isset($_GET['uid'])) {
        $uid = htmlspecialchars($_GET['uid']);
        $rid = htmlspecialchars($_GET['rid']);
        $token = htmlspecialchars($_GET['token']);
        $na = htmlspecialchars($_GET['na']);
        require_once __DIR__.'/../sys/dbinit.php';
        require_once __DIR__.'/payjp/init.php';
        require_once __DIR__.'/payinit.php';
        try {
            Payjp\Payjp::setApiKey($paySecret);
        } catch (Exception $e) {
            $json['error'] = 'pay.jpの初期化に失敗しました。';
        }
        $r = $db->query("SELECT id FROM t02user WHERE id='$uid';")->fetch();
        if (!$r) {
            $ps = $db->prepare('INSERT INTO t02user(id,na) VALUES (:id,:na);');
            if (!$ps->execute(array('id' => $uid, 'na' => $na))) {
                $json['error'] = 'データベースエラーによりユーザーの追加に失敗しました。';
            }
        }
        $plan = $db->query("SELECT plan,trial_days,auth_days FROM t01room JOIN t13plan 
        ON t01room.id=t13plan.rid AND t01room.plan=t13plan.id WHERE t01room.id=$rid;")->fetchAll(PDO::FETCH_ASSOC);
        if (!$plan || count($plan) !== 1) {
            $json['error'] = '部屋まちがってます。';
        }
        if (!isset($json)) {//顧客情報の読込または作成
            try {
                $customer = Payjp\Customer::retrieve($uid);
            } catch (Exception $e) {
                $json['error'] = 'pay.jpの顧客情報読み込みに失敗しました。';
            }
            if (!isset($customer['id'])) {
                if ($token) {
                    try {
                        $result = Payjp\Customer::create(array('card' => $token, 'id' => $uid, 'description' => $na));
                    } catch (Exception $e) {
                        $json['error'] = $e->getMessage();
                    }
                    if (isset($result['id'])) {
                        $uid = $result['id'];
                    } else {
                        $json['error'] = 'pay.jpの顧客作成に失敗しました。';
                    }
                } else {
                    $json['error'] = 'pay.jpの新規顧客作成にはカードトークンが必要です。';
                }
            } elseif ($token) {
                try {
                    if (!is_null($customer['default_card'])) {
                        $card = $customer->cards->retrieve($customer['default_card']);
                        $card->delete();
                    }
                    $card = $customer->cards->create(array('card' => $token, 'default' => true));
                } catch (Exception $e) {
                    $json['error'] = $e->getMessage();
                }
                if (isset($card['error'])) {
                    $json['error'] = 'pay.jpカード情報の変更に失敗しました。';
                }
            }
        }
        if (!isset($json)) {//定額課金作成
            try {
                $result = Payjp\Subscription::create(array('customer' => $uid, 'plan' => "blg$rid".'_'.$plan[0]['plan']));
            } catch (Exception $e) {
                $json['error'] = "payjpの定額課金に失敗しました。\r\n".$e->getMessage();
            }
            if (isset($result['id']) && $result['id'] !== $uid) {//既に定額課金があるとpayjpはresult.idにuidを返す
                $days = isset($plan[0]['trial_days']) ? $plan[0]['trial_days'] : 0;
                $days += isset($plan[0]['auth_days']) ? $plan[0]['auth_days'] : 0;
                $start_day = $days ? date('Y-m-d H:i:s', strtotime("+$days day")) : date('Y-m-d H:i:s');
                $ps = $db->prepare('INSERT INTO t11roompay(uid,rid,sub_day,start_day,payjp_id) VALUES (?,?,?,?,?);');
                if ($ps->execute(array($uid, $rid, date('Y-m-d H:i:s'), $start_day, $result['id'])) && $ps->rowCount() === 1) {
                    $json['msg'] = 'ok';
                    $json['plan'] = $plan[0]['auth_days'];
                } else {
                    $json['error'] = "データベースエラーによりルーム支払データ挿入に失敗しました。\nC-Lifeまでお問合せください。";
                }
            } else {
                $json['error'] = "payjpの定額課金に失敗しました。\nC-Lifeまでお問合せください。";
            }
        }
    } else {
        $json['error'] = 'パラメーターが不足しています。';
    }
} else {
    $json['error'] = '不適切なアクセス手順です。';
}
echo json_encode($json);

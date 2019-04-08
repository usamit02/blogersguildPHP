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
        $sid = isset($_GET['sid']) ? htmlspecialchars($_GET['sid']) : false; //単発買いのストーリー番号、なければ定額課金
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
        if ($sid) {//単発課金価格取得
            $amount = $db->query("SELECT pay FROM t21story WHERE rid=$rid AND id=$sid;")->fetchcolumn();
            if (!($amount > 49)) {
                $json['error'] = '価格情報の取得に失敗しました。';
            }
        } else {//定額課金情報取得
            $plan = $db->query("SELECT plan,trial_days,auth_days,prorate FROM t01room JOIN t13plan 
        ON t01room.id=t13plan.rid AND t01room.plan=t13plan.id WHERE t01room.id=$rid;")->fetchAll(PDO::FETCH_ASSOC);
            if (!$plan || count($plan) !== 1) {
                $json['error'] = '部屋まちがってます。';
            }
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
                        $customer = Payjp\Customer::create(array('card' => $token, 'id' => $uid, 'description' => $na));
                    } catch (Exception $e) {
                        $json['error'] = $e->getMessage();
                    }
                    if (isset($customer['id'])) {
                        $uid = $customer['id'];
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
        if (!isset($json) && isset($amount)) {//単発課金
            try {
                $result = Payjp\Charge::create(array('customer' => $customer, 'amount' => $amount, 'currency' => 'jpy'));
            } catch (Exception $e) {
                $json['error'] = "payjpの課金に失敗しました。\r\n".$e->getMessage();
            }
            if (isset($result['id'])) {
                $ps = $db->prepare('INSERT INTO t12storypay(uid,rid,sid,upd,payjp_id,amount) VALUES (?,?,?,?,?,?);');
                if ($ps->execute(array($uid, $rid, $sid, date('Y-m-d H:i:s', $result['created']), $result['id'], $amount)) && $ps->rowCount() === 1) {
                    $json['msg'] = 'charge';
                } else {
                    $json['error'] = "データベースエラーにより支払データ挿入に失敗しました。\nC-Lifeまでお問合せください。";
                }
            } else {
                $json['error'] = "payjpの課金処理に失敗しました。\nC-Lifeまでお問合せください。";
            }
        } elseif (!isset($json)) {//定額課金作成
            $prorate = $plan[0]['prorate'] === 1 ? true : false;
            try {
                $sub = Payjp\Subscription::create(array(
                    'customer' => $uid,
                    'plan' => "blg$rid".'_'.$plan[0]['plan'],
                    'prorate' => $prorate,
                ));
            } catch (Exception $e) {
                $json['error'] = "payjpの定額課金に失敗しました。\r\n".$e->getMessage();
            }
            if (isset($sub['id']) && $sub['id'] !== $uid) {//既に定額課金があるとpayjpはresult.idにuidを返す
                $active = $sub['status'] === 'active' || $sub['status'] === 'trial' && $plan[0]['auth_days'] === 0 ? 1 : 0;
                $ps = $db->prepare('INSERT INTO t11roompay(uid,rid,plan,created,start_day,end_day,payjp_id,active) 
                VALUES (?,?,?,?,?,?,?,?);');
                if ($ps->execute(array($uid, $rid, $plan[0]['plan'], date('Y-m-d H:i:s', $sub['created']),
                date('Y-m-d', $sub['current_period_start']), date('Y-m-d', $sub['current_period_end']),
                $sub['id'], $active, )) && $ps->rowCount() === 1) {
                    $json['msg'] = 'plan';
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

//$days = isset($plan[0]['trial_days']) ? $plan[0]['trial_days'] : 0;
                //$days += isset($plan[0]['auth_days']) ? $plan[0]['auth_days'] : 0;
                //$start_day = $days ? date('Y-m-d H:i:s', strtotime("+$days day")) : date('Y-m-d H:i:s');

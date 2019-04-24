<?php

header('Access-Control-Allow-Origin: *');
if (isset($_GET['uid']) && isset($_GET['rid'])) {
    $pid = isset($_GET['pid']) ? htmlspecialchars($_GET['pid']) : false;
    $rid = htmlspecialchars($_GET['rid']);
    $uid = htmlspecialchars($_GET['uid']);
    require_once __DIR__.'/../sys/dbinit.php';
    require_once __DIR__.'/payjp/init.php';
    require_once __DIR__.'/payinit.php';
    try {
        Payjp\Payjp::setApiKey($paySecret);
    } catch (Exception $e) {
        $res['error'] = 'pay.jpの初期化に失敗しました。';
    }
    if ($pid) {
        $plan = $db->query("SELECT amount,billing_day,trial_days,auth_days,prorate FROM t13plan WHERE rid=$rid AND id=$pid;")->fetchAll(PDO::FETCH_ASSOC);
        if ($plan && count($plan) === 1) {
            $res['plan'] = $plan[0];
        } else {
            $res['error'] = '該当するプランがありません。';
        }
    }
    if($db->query("SELECT payjp FROM t02user WHERE id='$uid';")->fetchcolumn()){
        try {
            $customer = Payjp\Customer::retrieve($uid);
        } catch (Exception $e) {
            $res['error'] = 'pay.jpの顧客情報取得に失敗しました。\n' + $e->getMessage();
        }
    }
    if (isset($customer['id'])) {
        if (is_null($customer['default_card'])) {
            $res['card']['last4'] = 0;
        } else {
            try {
                $card = $customer->cards->retrieve($customer['default_card']);
                $res['card']['brand'] = $card['brand'];
                $res['card']['last4'] = $card['last4'];
                $res['card']['exp_month'] = $card['exp_month'];
                $res['card']['exp_year'] = $card['exp_year'];
                $res['card']['change'] = false;
            } catch (Exception $e) {
                $res['error'] = "pay.jpのカード情報取得に失敗しました。\n" + $e->getMessage();
            }
        }
    } else {
        $res['card']['last4'] = 0;
    }
} else {
    $res['error'] = 'パラメーターが不足しています。';
}
$res['msg']=isset($res['error'])?"プランの読込に失敗しました。\r\n".$res['error']:"ok";
echo json_encode($res);

<?php

header('Access-Control-Allow-Origin: *');
if (isset($_GET['pid']) && isset($_GET['uid']) && isset($_GET['rid'])) {
    $pid = htmlspecialchars($_GET['pid']);
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
    $plan = $db->query("SELECT amount,billing_day,trial_days,auth_days FROM t13plan WHERE rid=$rid AND id=$pid;")->fetchAll(PDO::FETCH_ASSOC);
    if ($plan && count($plan) === 1) {
        $res['plan'] = $plan[0];
        try {
            $customer = Payjp\Customer::retrieve($uid);
        } catch (Exception $e) {
            $res['error'] = 'pay.jpの顧客情報取得に失敗しました。\n' + $e->getMessage();
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
            $res['error'] = 'pay.jpの顧客情報が存在しません。';
        }
    } else {
        $res['error'] = '該当するプランがありません。';
    }
} else {
    $res['error'] = 'パラメーターが不足しています。';
}
echo json_encode($res);

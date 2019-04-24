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
            if ($db->query("SELECT payjp FROM t02user WHERE id='$uid';")->fetchcolumn()) {
                try {
                    $customer = Payjp\Customer::retrieve($uid);
                } catch (Exception $e) {
                    $json['error'] = 'pay.jpの顧客情報読み込みに失敗しました。';
                }
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
                        $ps = $db->prepare('UPDATE t02user SET payjp=1 WHERE uid=?;');
                        if (!$ps->execute(array($uid)) || $ps->rowCount() !== 1) {
                            try {
                                $customer = Payjp\Customer::retrieve($uid);
                                $customer->delete();
                            } catch (Exception $e) {
                                $json['error'] = 'データーベースエラーによる顧客作成の取消に失敗しました。';
                            }
                            $json['error'] = 'データーベースエラーにより顧客作成を取消しました。';
                        }
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
                $charge = Payjp\Charge::create(array('customer' => $customer, 'amount' => $amount, 'currency' => 'jpy'));
            } catch (Exception $e) {
                $json['error'] = "payjpの課金に失敗しました。\r\n".$e->getMessage();
            }
            if (isset($charge['id'])) {
                if ($charge['captured']) {
                    $ps = $db->prepare('INSERT INTO t12storypay(uid,rid,sid,upd,payjp_id,amount) VALUES (?,?,?,?,?,?);');
                    if ($ps->execute(array($uid, $rid, $sid, date('Y-m-d H:i:s', $charge['created']), $charge['id'], $amount)) && $ps->rowCount() === 1) {
                        $json['msg'] = 'ok';
                        $json['typ'] = 'charge';
                        $parent = $rid;
                        do {//部屋にスタッフがいなければ上層部屋のスタッフを探す
                            $allStaffs = $db->query("SELECT uid,parent,rate,auth FROM t03staff JOIN t01room 
                            ON t03staff.rid=t01room.id WHERE t03staff.rid=$parent;")->fetchAll(PDO::FETCH_ASSOC);
                            if (count($allStaffs)) {
                                break;
                            } else {
                                $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchcolumn();
                            }
                        } while ($parent);
                        if (count($allStaffs)) {
                            $staffs = array_values(array_filter($allStaffs, function ($staff) {
                                return $staff['rate'] > 0;
                            }));
                            if (count($staffs)) {//レート設定有の人数で分配
                                $sumRate = 0;
                                foreach ($staffs as $staff) {
                                    $sumRate += $staff['rate'];
                                }
                                for ($j = 0; $j < count($staffs); ++$j) {
                                    $staffs[$j]['div'] = $staffs[$j]['rate'] / $sumRate;
                                }
                            } else {//最高職位の人数で頭割り
                                $auth = $allStaffs[0]['auth'];
                                for ($j = 1; $j < count($allStaffs); ++$j) {
                                    if ($auth < $allStaffs[$j]['auth']) {
                                        $auth = $allStaffs[$j]['auth'];
                                    }
                                }
                                $staffs = array_values(array_filter($allStaffs, function ($staff) use ($auth) {
                                    return $staff['auth'] === $auth;
                                }));
                                for ($j = 0; $j < count($staffs); ++$j) {
                                    $staffs[$j]['div'] = 1 / count($staffs);
                                }
                            }
                            $db->beginTransaction();
                            foreach ($staffs as $staff) {
                                $p = floor($amount * $staff['div']);
                                $ps = $db->prepare('INSERT INTO t57storydiv (rid,uid,mid,amount,billing_day) VALUES (?,?,?,?,?);');
                                $ps1 = $ps->execute(array($rid, $staff['uid'], $uid, $p, date('Y-m-d H:i:s', $charge['created'])))
                                || $ps->rowCount() === 1;
                                $ps = $db->prepare('UPDATE t02user SET p=p+? WHERE id=?;');
                                $ps2 = $ps->execute(array($p, $staff['uid'])) && $ps->rowCount() === 1;
                                if (!($ps1 && $ps2)) {
                                    $db->rollback();
                                    $mail = $db->query('SELECT mail FROM t02user WHERE no=1;')->fetchcolumn();
                                    mail($mail, 'warning from guild system', "t57storydiv insertion or t02user point update failed.check payjp data at $rid room");
                                    break;
                                }
                            }
                            $db->commit();
                        } else {
                            $mail = $db->query('SELECT mail FROM t02user WHERE no=1;')->fetchcolumn();
                            $payjp = $charge['id'];
                            mail($mail, 'warning from guild system', "sales can not divident.check payjp $payjp data at $sid row in $rid room ");
                        }
                    } else {
                        $json['error'] = "データベースエラーにより支払データ挿入に失敗しました。\nC-Lifeまでお問合せください。";
                    }
                } else {
                    $json['error'] = "カード決済できません。\r\n".$charge['failure_message'];
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
                    $json['msg'] ='ok';
                    $json['typ'] = 'plan';
                    $json['plan'] = $plan[0]['auth_days'];
                    if ($sub['status'] === 'trial' && $plan[0]['auth_days'] > 0) {//要審査
                        $parent = $rid;
                        do {//部屋にマネージャー職以上のスタッフがいなければ上層部屋のスタッフを探す
                            $staffs = $db->query("SELECT uid,parent,mail,t01room.na AS room FROM t03staff 
                            JOIN t01room ON t03staff.rid=t01room.id JOIN t02user ON t03staff.uid=t02user.id
                            WHERE t03staff.rid=$parent AND t03staff.auth>=200;")->fetchAll(PDO::FETCH_ASSOC);
                            if (count($staffs)) {
                                break;
                            } else {
                                $parent = $db->query("SELECT parent FROM t01room WHERE id=$parent;")->fetchcolumn();
                            }
                        } while ($parent);
                        $limitDay = date('n月j日', strtotime(date('Y-m-d', $sub['created']).' +'.$plan[0]['auth_days'].'days'));
                        foreach ($staffs as $staff) {
                            $uno = $db->query("SELECT no FROM t02user WHERE id='$uid';")->fetchcolumn();
                            if ($staff['mail']) {
                                $room = $staff['room'];
                                mb_send_mail($staff['mail'],'ギルドシステムよりお知らせ',
                               "<a href='https://$hpadress/home/room/$rid' target='_blank'>$room</a>".
                               'に'."<a href='https://$hpadress/detail/$uno' target='_blank'>$na</a>".
                               "から参加申込がありました。\r\n".$limitDay.'までに加入審査してください。');
                            }
                        }
                    }
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

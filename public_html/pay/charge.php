<?php
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer)) {
    $url = parse_url($referer);
    if (isset($_GET['token'])&&isset($_GET['userId'])) {
        $token=$_GET['token'];
        $userId=$_GET['userId'];
        $userName=$_GET['userName'];
        $price=$_GET['price'];    
        $room=$_GET['room'];
        require_once(__DIR__."/../sys/dbinit.php");
        require_once(__DIR__.'/payjp/init.php');
        require_once(__DIR__.'/payinit.php');
        try{
          Payjp\Payjp::setApiKey($paySecret);
        } catch (Exception $e){
          $json['error']="pay.jpの初期化に失敗しました。";
        }    
        $r=$db->query("SELECT id FROM t02user WHERE id='$userId';")->fetch();
        if($r){
            $payjpId=$r['payjp_id'];
        }else{
            try{
                $result = Payjp\Customer::create(array("card"=>$token,"id"=>$userId,"description"=>$userName));
            } catch (Exception $e) {
                //$json['msg']=$e['message'];
            }
            if(isset($result['id'])){
                $payjpId=$result['id'];
                $ps=$db->prepare("INSERT INTO t02user(id,na,payjp_id) VALUES (:id,:na,:payjp);");
                //$ps=$db->prepare("INSERT INTO t02user(id,na,payjp_id) VALUES (:id,:na,:payjp) ON DUPLICATE KEY UPDATE na=VALUES('na'),payjp_id=VALUES('payjp');");
                if (!$ps->execute(array("id" =>$userId,"na"=>$userName,"payjp"=>$payjpId))) {
                    $json['error']="データベースエラーによりユーザーの追加に失敗しました。";
                    $customer = \Payjp\Customer::retrieve($payjpId);
                    $customer->delete();
                }
            }else{
                $json['error']="pay.jpの顧客作成に失敗しました。";
            }
        }
        if(isset($payjpId)&&!isset($json)){
            try{
              $result=Payjp\Subscription::create(array("customer"=>$payjpId,"plan"=>"room".$room));
            } catch (Exception $e) {
                //$json['msg']=$e['message'];
                $json['error']="payjpの定額課金に失敗しました。";
            }            
            if (isset($result['id'])) {
                $ps=$db->prepare("INSERT INTO t11roompay(uid,rid,start_day,payjp_id) VALUES (?,?,?,?)");
                if ($ps->execute(array($userId,$room,date('Y-m-d H:i:s'),$payjpId))) {
                    $json['msg']="ok";
                }else{
                    $json['error']="データベースエラーによりルーム支払データ挿入に失敗しました。";
                }
            }else{
                $json['error']="payjpの定額課金に失敗しました。";
            }
        }
    }else{
        $json['error']="トークンがセットされていない";
    }
}else{
    $json['error']="不適切なアクセス手順です。";
}
header("Access-Control-Allow-Origin: *");
echo json_encode($json);
?>
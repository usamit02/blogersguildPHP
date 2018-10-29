<?php
session_start();
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer)) {
    $url = parse_url($referer);
    $ref=substr($url['path'],strlen($url['path'])-8);
    if(!($ref=="cart.php"||$ref=="load.php")){
        echo"不適切なアクセス手順です。";exit;
    }
}else{
    echo"不適切なアクセス手順です。";exit;
}
if(!(isset($_SESSION['key'])&&isset($_POST['key'])&&$_SESSION['key']==$_POST['key'])){//連投防止
    echo"すでに購入手続き済みです。";exit();
}
unset($_SESSION['key']);
require_once(__DIR__."/../sys/dbinit.php");
require_once(__DIR__."/../sys/id.php");
require_once(__DIR__.'/payjp/init.php');
require_once(__DIR__.'/payinit.php');
$error=0;$amount=0;$message="";$maxprice=0;$num=0;$now=date('Y-m-d H:i:s');$capture=true;
$db->beginTransaction();
foreach($_POST['pay'] as $i=>$pay){
    $q=$db->prepare("INSERT INTO t27pay (mid,pid,sid,num,price,pay_day,fix) VALUES (?,?,?,?,?,?,?);");
    $error+=($q->execute(array($id,$pay['pid'],$pay['sid'],$pay['num'],$pay['price'],$now,$now))&&$q->rowCount()==1)?0:1;
    $amount+=$pay['num']*$pay['price'];
    if($pay['pid']){//送料以外は在庫-　送料はpid=0
        if($ref=="cart.php"){
            $q=$db->prepare("UPDATE t22goods SET stk=stk-?,fix=? WHERE pid=? AND sid=?;");
            $error+=($q->execute(array($pay['num'],date('Y-m-d H:i:s'),$pay['pid'],$pay['sid']))&&$q->rowCount()==1)?0:1;
            $message.=($q->errorCode()==22003)?"商品番号".$pay["pid"]."-".$pay['sid']."の在庫が不足しています。</br>":"";
        }
        if($pay['price']>$maxprice){
            $maxprice=$pay['price'];$pid=$pay["pid"];$sid=$pay["sid"];
        }
        $num+=$pay["num"];
    }
}
if($ref=="cart.php"){
    if(isset($pid)&&isset($sid)&&$maxprice<30000&&$amount>49&&$amount<100000){//単価3万円、合計10万円以上の購入は弾く
        $q=$db->prepare("INSERT INTO t28order (mid,pay_day,pid,sid,num,total,fix) VALUES (?,?,?,?,?,?,?);");
        $error+=($q->execute(array($id,$now,$pid,$sid,$num,$amount,$now))&&$q->rowCount()==1)?0:1;
        $capture=false;
    }else{
        $error++;$message="システムエラー　不適切な購入を実行しようとしました。";
    }
}
if($error){
    $db->rollBack();
    echo"データーベースエラー".$q->errorCode()."により購入を記録できませんでした。";
    echo$message;
    exit;
}else{
    if (!isset($_POST['payjp-token'])) {
        if (isset($_POST['access_token'])) {
            $headers = array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Bearer '.$_POST['access_token']
            );
            $options = array('http' => array(
            'method' => 'POST',
            'header' => implode("\r\n", $headers)
            ));
            $res = file_get_contents('https://api.pay.jp/u/v1/'.'cards/default/tokenize', false, stream_context_create($options));
            $card = json_decode($res, true);
            if($card['livemode']){
                echo "本番カードです。";$db->rollBack();
                exit;
            }
            $token=$card['id'];
        }else{
            echo "トークンがセットされていない";$db->rollBack();
            exit;
        }
    }else{
        $token=$_POST['payjp-token'];
    }
    $charge=array( "card" => $token,"amount" => $amount,"currency" => "jpy","capture" => $capture );
    if(!$capture){$charge[]=array("expiry_days" => 30 );}//true=即確定(download)、false=与信枠のみ(cart)//与信枠を確保する日数
    try {
        Payjp\Payjp::setApiKey($paySecret);
        $result = Payjp\Charge::create($charge);
        if (isset($result['error'])) {
            throw new Exception();
        }
    } catch (Exception $e) {
        if (isset($result['error'])) {
            echo $result['error']['message'];
        }else{
            echo "pay jpの内部エラーです。";
        }
        $db->rollBack();exit;
    }
    $db->commit();
    $q=$db->prepare("UPDATE t28order SET chid=? WHERE mid=? AND pay_day=?;");
    if(!($q->execute(array($result['id'],$id,$now))&&$q->rowCount()==1)){
        //mb_send_mail($mymail,$subject."さんから".$r['h2']."他".strval($num-1)."点".number_format($amount).
        //"円の注文のデータベース書き込みに失敗","課金id:".$result['id']." 時刻:".$now."PAY JPのダッシュボードから手作業で確定or
        //返金処理してください。");
    }
    $home=$_SERVER['HTTP_HOST'];
    if ($ref=="load.php") {
        $message=isset($name)?"購入は記録されましたので元のページからもいつでもご覧になれます。":"";
        echo"<HTML><HEAD><META HTTP-EQUIV='Content-Type' CONTENT='text/html;charset=UTF-8'>
        <title>コンテンツ購入済</title></HEAD><BODY>お買い上げありがとうございました。
        <br>".$message."
        <a href='https://$home/download.php?".$url['query']."'>ダウンロードする</a>
        <a href='https://$home/diary.php#n$sid?p=$pid'>元のページに戻る</a></body></html>";
        //header("Location:download.php?".$url['query']);
    } else if ($ref=="cart.php") {
        $db->exec("DELETE FROM t21cart WHERE mid='$id';");
        $subject=isset($name)?$name:strval($id);
        $r=$db->query("SELECT h2 FROM t12story WHERE pid=$pid AND id=$sid;")->fetch();
        //mb_send_mail($mymail,$subject."さんから".$r['h2']."他".strval($num-1)."点".number_format($amount)."円の注文");
        header("Location:order.php");
    }
}
?>
<?php
$referer = $_SERVER['HTTP_REFERER'];
if (isset($referer)) {
    $url = parse_url($referer);
 }else{
    $result['error']="不適切なアクセス手順です。";
}
if (isset($_GET['token'])) {
    $token=$_GET['token'];
    require_once(__DIR__."/../sys/dbinit.php");
    require_once(__DIR__.'/payjp/init.php');
    require_once(__DIR__.'/payinit.php');
    try{
      Payjp\Payjp::setApiKey($paySecret);
    } catch (Exception $e){
      $json['error']="pay.jpの初期化に失敗しました。";
    }   
    try {   
      $result = Payjp\Charge::create(array( "card" => $token,"amount" => 500,"currency" => "jpy","capture" => true ));
          if (isset($result['error'])) {
              throw new Exception();
          }else{
              $result['msg']="支払いOK";
          }
      } catch (Exception $e) {
          if (!isset($result['error'])) {
            $result['error']="pay jpの内部エラーです。";
          }
      }
}else{
    $result['error']="トークンがセットされていない";
}
header("Access-Control-Allow-Origin: *");
echo json_encode($result);
?>
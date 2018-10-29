<?php
$key=md5(microtime().mt_rand());
$_SESSION['key']=$key;
$str="<form action='pay/charge.php' method='POST' onsubmit='return beforePay()'><input type='hidden' name='key' value='$key'>";
foreach($pays as $i=>$pay){
    $postageClass=($i)?"":"class='postagePay'";
    $str.="<INPUT TYPE='hidden' NAME='pay[$i][pid]' VALUE='".$pay['pid']."'>";
    $str.="<INPUT TYPE='hidden' NAME='pay[$i][sid]' VALUE='".$pay['sid']."'>";
    $str.="<INPUT TYPE='hidden' $postageClass NAME='pay[$i][price]' VALUE='".$pay['price']."'>";
    $str.="<INPUT TYPE='hidden' NAME='pay[$i][num]' VALUE='".$pay['num']."'>";
}
if(isset($_SESSION['access_token'])){
    echo"$str<INPUT TYPE='hidden' NAME='access_token' value='".$_SESSION['access_token']."'>";
    echo'<button type="submit"><img src="img/payjp.jpg" alt="PAY IDで支払う">で支払う</button></form>';
}else{
    include(__DIR__.'/../include/login.php');
}
echo$str."<input id='guest' type='button' value='クレジットカードで支払う' onclick='guestPay()'>";
require_once(__DIR__."/payinit.php");
echo"<script src='https://checkout.pay.jp/' class='payjp-button' data-key='$payID' data-payjp='$clientID'></script></form>";
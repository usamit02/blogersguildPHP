<HTML>

  <HEAD>
    <META HTTP-EQUIV='Content-Type' CONTENT='text/html;charset=UTF-8'>
    <title>コンテンツ販売</title>
    <link rel="stylesheet" href="css/cart.css" type="text/css">
  </HEAD>

  <BODY>
    <script type="text/javascript" src="js/jquery-3.1.1.min.js"></script>
    <script type="text/javascript">
      function guestPay() {
        $("#payjp_checkout_box input[type='button']").click();
      }
    </script>
    <?php
echo"<input id='guest' type='button' value='クレジットカードで支払う' onclick='guestPay()'>";
require_once(__DIR__."/pay/payinit.php");
echo"<script src='https://checkout.pay.jp/' class='payjp-button' data-key='pk_test_a77ab4464e1cecb66c3d1b21' data-payjp='59df2b6249cf537df7375e97bacf439de965c515'></script></body></html>";
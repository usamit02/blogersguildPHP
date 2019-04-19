<?php

header('Access-Control-Allow-Origin: *');
$referer = $_SERVER['HTTP_REFERER'];
if (!isset($referer) || !isset($_GET['uid'])) {
    return;
}
$uid = htmlspecialchars($_GET['uid']);
require_once __DIR__.'/../sys/dbinit.php';
if (isset($_GET['amount'])) {
    $amount = htmlspecialchars($_GET['amount']);
    $db->beginTransaction();
    $ps = $db->prepare('INSERT INTO t58trance (uid,rqd,amount) VALUES (?,?,?);');
    $ps1 = $ps->execute(array($uid, date('Y-m-d H:i:s'), $amount)) && $ps->rowCount() === 1;
    $ps = $db->prepare('UPDATE t02user SET p=p-? WHERE id=?;');
    $ps2 = $ps->execute(array($amount, $uid));
    if ($ps1 && $ps2) {
        $db->commit();
        $res['msg'] = 'ok';
        $mail = $db->query('SELECT mail FROM t02user WHERE id=1;')->fetchcolumn();
        $bank = $db->query("SELECT na,branch,acctyp,accnum FROM t59bank WHERE uid='$uid' ORDER BY id DESC;")->fetch(PDO::FETCH_ASSOC);
        $acc=$bank['na']." ".$bank['branch']." ".$bank['acctyp']." ".$bank['accnum']; 
        mail($mail, 'trance request from the guild system', "id = $uid,amount = $amount,bank = $acc");
    } else {
        $db->rollBack();
        $res['msg'] = 'ポイント出金請求に失敗しました。C-Lifeまでお問合せください。';
    }
}else if(isset($_GET['bank'])){
    $bank = json_decode($_GET['bank'], true);
    $id=$db->query("SELECT MAX(id) AS id FROM t59bank WHERE uid='$uid';")->fetchcolumn();
    $id=$id?$id+1:1;
    $ps = $db->prepare('INSERT INTO t59bank (uid,id,na,branch,acctyp,accnum,upd) VALUES (?,?,?,?,?,?,?);');
    $ps1=$ps->execute(array($uid,$id,$bank['na'],$bank['branch'],$bank{'acctyp'},$bank['accnum'],date('Y-m-d H:i:s'))) && $ps->rowCount() === 1;
    if($ps1){
        $res['msg'] = 'ok';
    }else{
        $res['msg'] = 'データベースエラーにより口座情報登録に失敗しました。';
    }
} else {
    $res['book'] = $db->query("SELECT book.room,book.rqd,book.upd,book.amount FROM 
    (SELECT uid,null AS room,rqd,upd,-amount AS amount FROM t58trance 
    UNION SELECT uid,CONCAT(na,' 会費'),null,t56roomdiv.upd,amount FROM t56roomdiv JOIN t01room ON t56roomdiv.rid=t01room.id 
    UNION SELECT uid,na,null,t57storydiv.billing_day,amount FROM t57storydiv JOIN t01room ON t57storydiv.rid=t01room.id
    ) book 
    WHERE book.uid='$uid' ORDER BY book.rqd DESC,book.upd DESC;")->fetchAll(PDO::FETCH_ASSOC);
    $res['bank']= $db->query("SELECT na,branch,acctyp,accnum FROM t59bank WHERE uid='$uid' ORDER BY id DESC;")->fetch(PDO::FETCH_ASSOC);
}
echo json_encode($res);

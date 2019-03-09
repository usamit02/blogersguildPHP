<?php
require_once (__DIR__.'/sys/dbinit.php');
$p=$_GET['id'];
$log=0;$pay=0;
$data="";
$rs=$db->query("SELECT id,num,pt,ctl,h2,txt,photo,photo1,photo2,photo3,html FROM t22story WHERE pid=$p ORDER BY num;");
while ($r=$rs->fetch()) {
    $sid=$r['id'];$h2=$r['h2']; $pt=$r['pt'];$ctl=$r['ctl'];$txt="";
    for ($i=0; $i<4; $i++) {
        $j = ($i) ? $i : null;
        $pht = $r["photo".$j];
        if (isset($pht)&&strlen($pht)>4) {
            $photo[$i] = '<img src="https://bloggersguild.cf/img/'.$p."/".trim($pht, "`").'">';
            $photona[$i]=trim($pht, "`");
        } else {
            $photo[$i]="";
        }
    }
    if(isset($ctl)){
        $tag=getTag($ctl,"<log");
        if($tag){
            if($rnk==0){$log=intval($tag);}
        }
        $tag=getTag($ctl,"<pay");
        if($tag){
            if(!($rnk&&$db->query("SELECT num FROM t27pay WHERE pid=$p AND sid=$sid AND mid='$id';")->fetchcolumn())){
                $split=explode(",",$tag);
                $pay=intval($split[0]);$price=intval($split[1]);
            }
        }
        $tag=getTag($ctl,"<h2");
        if($tag){$h3=$tag;}
    }
    if (isset($h2)) {
        if(isset($h3)){
            if($h3!=""){
                $txt="<div><h2>$h3</h2></div><div><h3 id='n$sid'>$h2</h3></div>";
                $h3="";
            }else{
                $txt="<div><h3 id='n$sid'>$h2</h3></div>";
            }
            
        }else{
            $txt="<div><h2 id='n$sid'>$h2</h2></div>";
        }
    }
    if($pay){
        $pt=70;$pay--;
    }else if($log){
        $pt=60;$log--;
    }else{
        $txt.=isset($r['txt'])?$r['txt']:"";
    }
    $data.= '<div class="row">';
    if ($pt==0) {//ノーマル
        if ($photo[1]=="") {
            $data.= '<div class="txt">'.$txt.'</div>'.$photo[0];
        } else {
            if ($photo[2]!=""&&$photo[3]==""&&$txt=="") {
                $data.= $photo[0].$photo[1];
            } else {
                $data.= '<div class="thumbtext"><div class="txt">'.$txt.'</div>';
                $data.= '<ul class="thumblist">';
                for ($i=0; $i<$np; $i++) {
                    $data.= '<li><a href="img/'.$p."/".$photona[$i].'"><img src="img/'.$p.'/s-'.$photona[$i].'"></a></li>';
                }
                $data.= '</ul></div>';
                $data.= '<div class="thumbimg">'.$photo[0].'</div>';
            }
        }
    }
    else if($pt==10||$pt==50){//HTML //コード
        if($pt==50){
            $data.="<script type='text/javascript' src='//rawgit.com/google/code-prettify/master/loader/run_prettify.js?skin=sons-of-obsidian'></script>";
        }
        $rr=$db->query("SELECT h2,html FROM t16html WHERE pid=$p AND sid=".$r['id'])->fetch();
        $h2div= isset($rr['h2'])?'<h2 id="'.$r['id'].'">'.$rr['h2'].'</h2></div>':"";
        $html = isset($rr['html']) ? $rr['html'] : "エラー　htmlが見つかりません。";
        $html = $pt==50?"<pre class='prettyprint'>$html</pre>":$html;
        if(strlen($txt)==0&&$photo[0]==""){
            $data.="$h2div$html";
        }else if(strlen($txt)){
            $data.="<div class='txt'>$txt</div>$html";
        }else{
            $data.="$h2div$html".$photo[0]."</div>";
        }
    }else if($pt>10&&$pt<20){//広告//地図//HTML定番//
        $htmlid = isset($r["html"])?$r["html"]:1;
        $html=$db->query("SELECT html FROM t17html WHERE id=$htmlid;")->fetchcolumn();
        $html=$html?$html:'エラー　htmlが見つかりません';
        if ($photo[0]<>"") {
            $data.= '<div class="txt">'.$txt.$html."</div>".$photo[0];
        } else if(strlen($txt)){
            $data.= '<div class="txt">'.$txt.'</div>'.$html;
        }else{
            $data.=$html;
        }
    }else if($pt==60){//閲覧制限要ログイン
        $data.="<div class='txt'>$txt"."ご覧になるにはログインしてください。</div>";
    }else if($pt==70){//閲覧有料
        if($price){
            $data.="<div class='txt'>$txt<form action='download.php' method='GET' style='display:inline;'><input type='hidden'
            name='pid' value='$p'><input type='hidden' name='sid' value='$sid'><input type='submit' value='$price"
            ."円でご覧になる'></form></div>";
            $price=0;
        }else{
            $data.="<div class='txt'>$txt"."有料コンテンツです。</div>";
        }
    }
    $data.='</div>';
}
header("Access-Control-Allow-Origin: *");
echo json_encode($data);
?>
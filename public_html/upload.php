<?php

if (isset($_POST['rid']) && isset($_POST['id'])) {
    $rid = htmlspecialchars($_POST['rid'], ENT_QUOTES);
    $id = htmlspecialchars($_POST['id'], ENT_QUOTES);
    //foreach ($_FILES['file']['tmp_name'] as $key => $file) {
    if (is_uploaded_file($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file'];
        if ($file['size'] > 10000) {
            $in = imagecreatefromjpeg($file['tmp_name']);
            $size = getimagesize($file['tmp_name']);
            $h = 112;
            $w = $size[0] * ($h / $size[1]);
            $out = imagecreatetruecolor($w, $h);
            imagecopyresampled($out, $in, 0, 0, 0, 0, $w, $h, $size[0], $size[1]);
            imagejpeg($out, __DIR__."/img/$rid/s-$id.jpg");
            imagedestroy($in);
            imagedestroy($out);
        }
        if (move_uploaded_file($_FILES['file']['tmp_name'], __DIR__."/img/$rid/$id.jpg")) {
            $res['msg'] = 'ok';
        } else {
            $res['msg'] = 'ファイルの書き込みに失敗しました。';
        }
    } else {
        $res['msg'] = 'ファイルのアップロードに失敗しました。';
    }
    //}
} else {
    $res['msg'] = '部屋が選択されていません';
}
header('Access-Control-Allow-Origin: *');
echo json_encode($res);

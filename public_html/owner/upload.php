<?php

header('Access-Control-Allow-Origin: *');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rid']) && isset($_POST['id'])) {
    $path = __DIR__.'/../media/'.htmlspecialchars($_POST['rid'], ENT_QUOTES);
    $id = htmlspecialchars($_POST['id'], ENT_QUOTES);
    //foreach ($_FILES['file']['tmp_name'] as $key => $file) {
    if (is_uploaded_file($_FILES['file']['tmp_name'])) {
        $file = $_FILES['file'];
        if ($file['size'] <= 50000000) {
            if (file_exists($path)) {
                foreach (glob("$path/$id.*") as $f) {
                    unlink($f);
                }
            } else {
                mkdir($path);
            }
            if ($file['type'] === 'image/jpeg') {
                if ($file['size'] > 10000) {
                    $in = imagecreatefromjpeg($file['tmp_name']);
                    $size = getimagesize($file['tmp_name']);
                    $h = 112;
                    $w = $size[0] * ($h / $size[1]);
                    $out = imagecreatetruecolor($w, $h);
                    imagecopyresampled($out, $in, 0, 0, 0, 0, $w, $h, $size[0], $size[1]);
                    imagejpeg($out, "$path/s-$id.jpg");
                    imagedestroy($in);
                    imagedestroy($out);
                }
                if (move_uploaded_file($_FILES['file']['tmp_name'], "$path/$id.jpg")) {
                    $res['typ'] = 'img';
                    $res['ext'] = 'jpg';
                } else {
                    $res['err'] = 'ファイルの書き込みに失敗しました。';
                }
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                if (move_uploaded_file($_FILES['file']['tmp_name'], "$path/$id.$ext")) {
                    $res['typ'] = substr($file['type'], 0, strpos($file['type'], '/'));
                    $res['ext'] = $ext;
                } else {
                    $res['err'] = 'ファイルの書き込みに失敗しました。';
                }
            }
        } else {
            $res['err'] = 'ファイルサイスは50MBまでにしてください。';
        }
    } else {
        $res['err'] = 'ファイルのアップロードに失敗しました。';
    }
    //}
} else {
    $res['err'] = '部屋が選択されていません';
}

echo json_encode($res);

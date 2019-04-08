<?php

header('Access-Control-Allow-Origin: *');
set_time_limit(60);

class Tag
{
    public $name; //--------------------------探したいタグ
    public $pos; //-------------------------読み込んだ位置を保持、次にreadするときは$pos以降から

    public function __construct($name, $pos = 0)
    {
        $this->name = $name;
        $this->pos = $pos;
    }

    public function find($html, $item = 0)
    {
        $this->pos = ($this->pos > $item) ? $this->pos : $item;
        $i = mb_stripos($html, "<$this->name", $this->pos);
        if ($i === false) {
            return '';
        }
        $i = $i + mb_strlen("<$this->name>");
        $j = mb_stripos($html, "</$this->name>", $i);
        if ($j) {
            $this->pos = $j + mb_strlen("</$this->name>");
        } else {
            $j = mb_stripos($html, '>', $i);
            if ($j) {
                $this->pos = $j + 1;
            } else {
                return '';
            }
        }
        $len = ($j - $i > 0) ? $j - $i : 0; //見つからないときは空文字を返す
        //$len = ($len > 100) ? 100 : $len; //----------取り出す文字数は100文字以内にする
        return mb_substr($html, $i, $len);
    }
}
function attr($tag, $attr)
{
    $i = mb_stripos($tag, $attr);
    if ($i === false) {
        return '';
    }
    $i = mb_stripos($tag, 'content=');
    if ($i === false) {
        return '';
    }
    $i += 9;
    $j = mb_stripos($tag, '"', $i);
    if ($j === false) {
        $j = mb_stripos($tag, "'", $i);
        if ($j === false) {
            return '';
        }
    }
    $len = ($j - $i > 0) ? $j - $i : 0; //見つからないときは空文字を返す
    return mb_substr($tag, $i, $len);
}
$url = htmlspecialchars($_GET['url']);
$html = file_get_contents($url, false, null, 0);
$headtag = new Tag('head');
$res = array('title' => '', 'description' => '', 'image' => '');
//$res = array('image' => '');
$head = $headtag->find($html);
if ($head) {
    $metas = [];
    $metatag = new Tag('meta');
    do {
        $meta = $metatag->find($head);
        foreach ($res as $key => $val) {
            $attr = attr($meta, "og:$key");
            if ($attr) {
                $res[$key] = $attr;
            }
        }
    } while ($meta);
}
echo json_encode($res);

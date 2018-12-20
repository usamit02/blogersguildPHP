<?php

$consumer_key = 'q2OC7c0ftbN9PTL5pSkYLAvWV';
$cunsumer_secret = 'O9xubWcGixo47uD4K4GpCun2IXaakxdgkwt6JtN2KndKEywRR6';
$accessToken = '939729394636955648-q4dBSLT9tlRnXgWUeeHbJnDjvrAV0nI';
$accessToken_secret = 'Sq4iWQeOCzaYxKT6ZgkjaNRPdZV9U2ZnvUcEOlDYXsODR';

$keyword = $_POST['keyword'];
$values = array();
require '../vendor/autoload.php';
use Abraham\TwitterOAuth\TwitterOAuth;

//require_once 'twitter/TwitterOAuth.php';
$twitter = new TwitterOAuth($consumer_key, $cunsumer_secret, $accessToken, $accessToken_secret);

$json = $twitter->OAuthRequest(
  'https://api.twitter.com/1.1/search/tweets.json',
  'GET',
  array('q' => $keyword, 'count' => '100')
);
$obj = json_decode($json, true);

foreach ($obj['statuses'] as $result) {
    if (!isset($result['retweeted_status'])) {
        array_push($values, $result['id_str']);
    }
}
/*
$flg = ($obj['search_metadata']['next_results']) ? 1 : 0;

while ($flg) {
    $json = $twitter->OAuthRequest(
    'https://api.twitter.com/1.1/search/tweets.json'.$obj['search_metadata']['next_results'],
    'GET',
    ''
  );
    $obj = json_decode($json, true);

    foreach ($obj['statuses'] as $result) {
        if (!isset($result['retweeted_status'])) {
            array_push($values, $result['id_str']);
        }
    }

    $flg = ($obj['search_metadata']['next_results']) ? 1 : 0;
}
*/
echo json_encode($values);

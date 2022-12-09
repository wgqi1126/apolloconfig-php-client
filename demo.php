<?php

use Wgqi1126\ApolloconfigPhpClient\ApolloconfigClient;
use Wgqi1126\ApolloconfigPhpClient\ApolloconfigHelper;

require_once __DIR__ . '/vendor/autoload.php';

$config = [
    'config_server_url' => 'http://localhost:8080',
    'app_id' => '111',
    'cluster_name' => 'default',
    'ip' => '',
    'max_memory' => 100 * 1024 * 1024,
];

//$client = new ApolloconfigClient($config);
//
//$config = $client->getConfigInCache('application');
//print_r($config);
//
//$config = $client->getConfig();
//print_r($config);
//
//$client->watch(function ($changes, $details) {
//    print_r(['changes' => $changes, 'details' => $details]);
//});

$helper = new ApolloconfigHelper($config);
$helper->openLog();
$helper->watchAndSave();
//$helper->watchAndSave(['application'], './data', function ($file, $data) {
//    return [$file . '.txt', print_r($data, true)];
//});
//$helper->watchAndSave('application', function () {
//});
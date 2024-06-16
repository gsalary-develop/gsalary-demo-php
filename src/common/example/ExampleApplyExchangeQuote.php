<?php

namespace GSalaryDemo\common\example;

include_once "../lib/GSalaryRequest.php";
include_once "../lib/GSalaryClient.php";

use GSalaryDemo\common\lib\GSalaryRequest;
use GSalaryDemo\common\lib\GSalaryClient;

//从环境变量读取GSALARY_APPID, PRIVATE_KEY, PUBLIC_KEY

$APPID = getenv("GSALARY_APPID");
$CLIENT_PRIVATE_KEY_FILE = getenv("GSALARY_PRIV_KEY");
$SERVER_PUBLIC_KEY_FILE = getenv("GSALARY_PUB_KEY");


$CLIENT_PRIVATE_KEY = file_get_contents($CLIENT_PRIVATE_KEY_FILE);
$SERVER_PUBLIC_KEY = file_get_contents($SERVER_PUBLIC_KEY_FILE);

$client = new GSalaryClient("https://api-test.gsalary.com", $APPID, $CLIENT_PRIVATE_KEY, $SERVER_PUBLIC_KEY);

$request = new GSalaryRequest("POST", '/v1/exchange/quotes');
$request->addBodyArg("buy_currency", "CNY");
$request->addBodyArg("sell_currency", "USD");
$request->addBodyArg("buy_amount", "100");

$response = $client->request($request);
echo json_encode($response);

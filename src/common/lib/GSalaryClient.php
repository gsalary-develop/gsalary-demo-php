<?php

namespace GSalaryDemo\common\lib;

include_once 'GSalarySignature.php';
include_once 'GSalaryRequest.php';
include_once 'GSalaryException.php';

class GSalaryClient
{
    private $endpoint;
    private $appId;
    private $clientPrivateKey;
    private $serverPublicKey;

    //构造器

    /**
     * @param string $endpoint 接口地址
     * @param string $appId 商户ID
     * @param string $clientPrivateKey 商户私钥字符串，要求包含-----BEGIN PRIVATE KEY-----头部
     * @param string $serverPublicKey 服务端公钥字符串
     * @throws GSalaryException
     */
    public function __construct($endpoint, $appId, $clientPrivateKey, $serverPublicKey)
    {
        //删除endpoint末尾的'/'
        if (substr($endpoint, -1) == '/') {
            $endpoint = substr($endpoint, 0, strlen($endpoint) - 1);
        }
        $this->endpoint = $endpoint;
        $this->appId = $appId;
        $this->clientPrivateKey = openssl_get_privatekey(trim($clientPrivateKey));
        if (!$this->clientPrivateKey) {
            throw new GSalaryException('Invalid private key');
        }
        $serverPublicKey = trim($serverPublicKey);
        //判断是否存在PEM头尾标记，如不存在则添加RSA公钥的PEM头尾标记
        if (substr($serverPublicKey, 0, 26) != "-----BEGIN PUBLIC KEY-----") {
            $serverPublicKey = chunk_split($serverPublicKey, 64, "\n");
            $serverPublicKey = "-----BEGIN PUBLIC KEY-----\n" . $serverPublicKey . "-----END PUBLIC KEY-----";
        }
        $this->serverPublicKey = openssl_get_publickey($serverPublicKey);
        if (!$this->serverPublicKey) {
            throw new GSalaryException('Invalid public key');
        }
    }

    /**
     * @param GSalaryRequest $request
     * @return array 返回结果
     * @throws GSalaryException
     */
    public function request(GSalaryRequest $request)
    {
        $pathWithArgs = $request->getPath() . $request->getQueryArgs();

        $url = $this->endpoint . $pathWithArgs;
        $signature = $this->signature($request);
        $signHeader = $signature->asHeader();
        $method = $request->getMethod();
        //make https request
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        //设置header
        $headers = array("Accept: application/json",
            "X-Appid: $this->appId",
            "Authorization: $signHeader",
            "Content-Type: application/json");

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($method == 'POST' || $method == 'PUT') {
            //设置body
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getBodyString());
        }
        //发起请求
        $response = curl_exec($ch);
        $resp_headers = curl_getinfo($ch);
        if ($response) {
            error_log($response);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            curl_close($ch);
            $headers = substr($response, 0, $header_size);
            $body = substr($response, $header_size);
            $lines = explode("\n", $headers);
            $headersArr = array();
            foreach ($lines as $l) {
                $l = trim($l);
                if ($headers && !empty($l)) {
                    //如果$1从HTTP开头
                    if (substr($l, 0, 4) == 'HTTP') {
                        $p = explode(' ', $l);
                        $headersArr['http_status'] = $p[1];
                    } else {
                        $p = explode(':', $l);
                        $headersArr[strtolower($p[0])] = trim($p[1]);
                    }
                }
            }
            $body_decode = json_decode($body, true);
            $http_status = $headersArr['http_status'];

            if (array_key_exists('authorization',$headersArr)) {
                $respSignature = GSalarySignature::castHeader(($headersArr['authorization']));
                $this->verifySignature($request, $respSignature, $body);
                return $body_decode;
            } else {
                throw new GSalaryException("http请求失败，状态码:$http_status, $body");
            }
        } else {
            $error = curl_errno($ch);
            curl_close($ch);
            throw new GSalaryException("curl出错，错误码:$error");
        }

    }

    /**
     * @param GSalaryRequest $request
     * @return GSalarySignature 签名结果
     */
    private function signature(GSalaryRequest $request)
    {
        $method = $request->getMethod();
        if ($method == 'POST' || $method == 'PUT') {
            $body = $request->getBodyString();
            //计算body的sha256，并用base64编码
            $bodySha256 = hash('sha256', $body, true);
            $bodyHash = base64_encode($bodySha256);
        } else {
            $bodyHash = "";
        }
        //获取当前Unix时间戳，并转为字符串
        $time = time() . "000";

        $pathWithArgs = $request->getPath() . $request->getQueryArgs();
        $signBase = "$method $pathWithArgs\n" .
            "$this->appId\n" .
            "$time\n" .
            "$bodyHash\n";
        //使用$this->clientPrivateKey计算signBase的SHA256WithRSA签名
        openssl_sign($signBase, $sign, $this->clientPrivateKey, OPENSSL_ALGO_SHA256);
        //使用base64编码签名
        $sign = base64_encode($sign);
        //$sign转为URL-Safe
        $sign = str_replace('+', '-', $sign);
        $sign = str_replace('/', '_', $sign);
        $sign = str_replace('=', '', $sign);
        return new GSalarySignature($time, $sign);
    }

    /**
     * @throws GSalaryException
     */
    private function verifySignature(GSalaryRequest $request, GSalarySignature $signature, $response)
    {
        $method = $request->getMethod();
        //计算body的sha256，并用base64编码
        $bodySha256 = hash('sha256', $response, true);
        $bodyHash = base64_encode($bodySha256);

        $time = $signature->getTime();

        $pathWithArgs = $request->getPath() . $request->getQueryArgs();
        $signBase = "$method $pathWithArgs\n" .
            "$this->appId\n" .
            "$time\n" .
            "$bodyHash\n";
        $signature = $signature->getSignature();
        //使用$this->serverPublicKey验证signBase的SHA256WithRSA签名
        $result = openssl_verify($signBase, base64_decode($signature), $this->serverPublicKey, OPENSSL_ALGO_SHA256);
        if ($result != 1) {
            throw new GSalaryException("签名验证失败");
        }
    }

}


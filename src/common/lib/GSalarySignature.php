<?php

namespace GSalaryDemo\common\lib;

class GSalarySignature
{
    private $time;
    private $signature;
    private $algorithm;

    //构造器
    public function __construct($time, $signature, $algorithm = "RSA2")
    {
        $this->time = $time;
        $this->signature = $signature;
        $this->algorithm = $algorithm;
    }

    public static function castHeader($algorithm)
    {
        //从algorithm=RSA2,time=<TIMESTAMP>,signature=<SIGNATURE_BASE64>中提取time和signature
        $header = explode(',', $algorithm);
        $time = null;
        $signature = null;
        foreach ($header as $item) {
            $item = explode('=', trim($item));
            if ($item[0] == 'time') {
                $time = $item[1];
            } else if ($item[0] == 'signature') {
                $signature = $item[1];
                //进行URL-Decode处理
                $signature = urldecode($signature);
            }
        }
        return new GSalarySignature($time, $signature);
    }

    public function asHeader()
    {
        //输出为algorithm=RSA2,time=<TIMESTAMP>,signature=<SIGNATURE_BASE64>
        return "algorithm=$this->algorithm,time=$this->time,signature=$this->signature";
    }

    //getters
    public function getTime()
    {
        return $this->time;
    }

    public function getSignature()
    {
        return $this->signature;
    }
}
<?php

namespace GSalaryDemo\common\lib;
class GSalaryRequest
{
    protected $method;
    protected $path;
    protected $queryArgs = array();
    protected $bodyArgs = array();

    public function __construct($method, $path)
    {
        $this->method = $method;
        //如果path不是'/'开头，添加'/'
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        $this->path = $path;
    }

    public function addQueryArg($key, $value)
    {
        $this->queryArgs[$key] = $value;
    }

    public function addBodyArg($key, $value)
    {
        $this->bodyArgs[$key] = $value;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getQueryArgs()
    {
        //如果queryArgs为空返回空字符串
        if (empty($this->queryArgs)) {
            return '';
        }
        //把queryArgs参数按queryString格式拼接返回
        $queryString = '?';
        foreach ($this->queryArgs as $key => $value) {
            $queryString .= $key . '=' . $value . '&';
        }
        //删除最后一个&符号
        return substr($queryString, 0, strlen($queryString) - 1);
    }

    /**
     * @return array
     */
    public function getBodyArgs()
    {
        return $this->bodyArgs;
    }

    public function getBodyString()
    {
        //如果bodyArgs为空返回空字符串，如果不为空用json输出
        if (empty($this->bodyArgs)) {
            return '';
        }
        return json_encode($this->bodyArgs);
    }
}
<?php

namespace AresTrace;

use Jaeger;
use Jaeger\Config;
use OpenTracing\Formats;
use OpenTracing\GlobalTracer;
use OpenTracing\Reference;

class JaegerInject
{
    public static $instance = null;
    protected $serviceName;
    protected $tracer = null;
    protected static $parentSpanName = "";
    protected static $spanList = [];
    protected $config;
    
    private function __construct()
    {
    }
    
    public static function getInstance()
    {
        if (!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    //初始化，创建父span
    public function init($spanName)
    {
        $configArr = \AresTrace\Config::getConfig();
        $this->serviceName = $configArr["service_name"];
        unset($configArr["service_name"]);
        $this->config = new Config($configArr, $this->serviceName);
        $this->config->initializeTracer();
        $tracer = GlobalTracer::get();
        $this->tracer = $tracer;
        
        if ($spanName && !static::$parentSpanName) {
            static::$parentSpanName = $spanName;
        }
        
        if (!isset(static::$spanList[static::$parentSpanName])) {
            $this->startParentSpan();
        }
    }
    
    //创建子span
    public function subCreate($spanName, $preSpanName = "")
    {
        if ($preSpanName) {
            $preSpanName = $this->getSpanName($preSpanName);
        }

        $this->start($spanName, $preSpanName);
        $injectHeaders = $this->inject($spanName);
        return $injectHeaders;
    }
    
    //启动父span
    protected function startParentSpan()
    {
        $parentSpanName = static::$parentSpanName;
        $parentContext = $this->tracer->extract(Formats\TEXT_MAP, $this->getAllHeaders());
        if (!$parentContext) {
            $serverSpan = $this->tracer->startSpan($parentSpanName);
        } else {
            //TODO $parentContext要为$parentSpan,不知道怎么获取
            $serverSpan = $this->tracer->startSpan($parentSpanName, ['references' => [
                new Reference(Reference::FOLLOWS_FROM, $parentContext),
                new Reference(Reference::CHILD_OF, $parentContext),
            ]]);
        }
        
        $this->tracer->inject($serverSpan->getContext(), Formats\TEXT_MAP, $_SERVER);
//        $this->clientTracer = $this->config->initTracer('HTTP');
        static::$spanList[$parentSpanName] = [
            "current_span" => $serverSpan,
            "parent_context" => $parentContext
        ];
    }
    
    /*
     * 创建子span
     */
    public function start($spanName, $parentSpan = "")
    {
//        $spanContext = $this->clientTracer->extract(Formats\TEXT_MAP, $_SERVER);
        $spanContext = $this->tracer->extract(Formats\TEXT_MAP, $_SERVER);
        $clientSpan = null;
        $parentSpanIsObj = $parentSpan && gettype($parentSpan) == "object";
        if (!$spanContext) {
            $clientSpan = $this->tracer->startSpan($spanName);
        } else {
//            $clientSpan = $this->tracer->startSpan($spanName, ['references' => [
//                new Reference(Reference::FOLLOWS_FROM, $parentSpanIsObj ? $parentSpan->spanContext : $spanContext),
//                new Reference(Reference::CHILD_OF, $spanContext),
//            ]]);
            $clientSpan = $this->tracer->startSpan($spanName, ['child_of' => $parentSpan]);
        }
        
        static::$spanList[$spanName] = [
            "current_span" => $clientSpan,
            "parent_context" => $parentSpanIsObj ? $parentSpan->spanContext : $spanContext,
        ];
    }
    
    
    private function getAllHeaders()
    {
        $headers = array();
        
        $copy_server = array(
            'CONTENT_TYPE' => 'Content-Type',
            'CONTENT_LENGTH' => 'Content-Length',
            'CONTENT_MD5' => 'Content-Md5',
        );
        
        foreach ($_SERVER as $key => $value) {
            if (substr($key, 0, 5) === 'HTTP_') {
                $key = substr($key, 5);
                if (!isset($copy_server[$key]) || !isset($_SERVER[$key])) {
                    $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', $key))));
                    $headers[$key] = $value;
                }
            } elseif (isset($copy_server[$key])) {
                $headers[$copy_server[$key]] = $value;
            }
        }
        
        if (isset($_SERVER["UBER-TRACE-ID"])) {
            $headers["UBER-TRACE-ID"] = $_SERVER["UBER-TRACE-ID"];
        }
        
        if (!isset($headers['Authorization'])) {
            if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                $headers['Authorization'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
            } elseif (isset($_SERVER['PHP_AUTH_USER'])) {
                $basic_pass = isset($_SERVER['PHP_AUTH_PW']) ? $_SERVER['PHP_AUTH_PW'] : '';
                $headers['Authorization'] = 'Basic ' . base64_encode($_SERVER['PHP_AUTH_USER'] . ':' . $basic_pass);
            } elseif (isset($_SERVER['PHP_AUTH_DIGEST'])) {
                $headers['Authorization'] = $_SERVER['PHP_AUTH_DIGEST'];
            }
        }
        
        return $headers;
    }
    
    
    public function getSpanName($spanName)
    {
        return isset(static::$spanList[$spanName]) ? static::$spanList[$spanName]["current_span"] : "";
    }
    
    public function inject($spanName)
    {
        if (!isset(static::$spanList[$spanName])) {
            return [];
        }
        $info = static::$spanList[$spanName];
        $span = $info['current_span'];
        $injectHeaders = [];
        //TODO
//        $this->clientTracer->inject($span->getContext(), Formats\TEXT_MAP, $injectHeaders);
        $this->tracer->inject($span->getContext(), Formats\TEXT_MAP, $injectHeaders);
        return $injectHeaders;
    }
    
    /*
    * 记录链路追踪日志
    */
    public function log($spanName, $key = "", $value = "")
    {
        if (!isset(static::$spanList[$spanName])) {
            return "";
        }
        $info = static::$spanList[$spanName];
        $span = $info['current_span'];
        if ($key && $value) {
            $span->log([$key => $value]);
        } else if ($key && !$value) {
            $span->log(["message" => $key]);
        }
    }
    
    public function setTag($spanName, $key = "", $value = "")
    {
        if (!isset(static::$spanList[$spanName])) {
            return "";
        }
        $info = static::$spanList[$spanName];
        $span = $info['current_span'];
        $span->setTag($key, $value);
    }
    
    public function finish($spanName, array $tagList = [])
    {
        if (!isset(static::$spanList[$spanName])) {
            return;
        }
        $info = static::$spanList[$spanName];
        
        $span = $info['current_span'];
        $parentContext = $info['parent_context'];
        
        $span->setTag('parentSpan', $parentContext ? $parentContext->spanIdToString() : '');
        $span->setTag("time", date("Y-m-d H:i:s"));
        $tagList = !empty($tagList) ? $tagList : [];
        foreach ($tagList as $k => $v) {
            $span->setTag($k, $v);
        }
        $span->finish();
    }
    
    public function flush()
    {
        $this->tracer->flush();
    }
}


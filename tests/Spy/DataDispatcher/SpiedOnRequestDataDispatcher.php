<?php

namespace FormRelay\Request\Tests\Spy\DataDispatcher;

use FormRelay\Request\DataDispatcher\RequestDataDispatcher;
use FormRelay\Core\Log\LoggerInterface;

class SpiedOnRequestDataDispatcher extends RequestDataDispatcher implements RequestDataDispatcherSpyInterface
{
    public $spy;

    public function __construct(LoggerInterface $logger, RequestDataDispatcherSpyInterface $spy)
    {
        parent::__construct($logger);
        $this->spy = $spy;
    }

    public static function getKeyword(): string
    {
        return 'request';
    }

    public function send(array $data): bool
    {
        $this->spy->send($data);
        return true;
    }
    
    public function setUrl(string $url)
    {
        $this->spy->setUrl($url);
    }

    public function addCookies(array $cookies)
    {
        $this->spy->addCookies($cookies);
    }

    public function addHeaders(array $headers)
    {
        $this->spy->addHeaders($headers);
    }
}

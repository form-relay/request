<?php

namespace FormRelay\Request\Tests\Spy\DataDispatcher;

use FormRelay\Core\Tests\Spy\DataDispatcher\DataDispatcherSpyInterface;

interface RequestDataDispatcherSpyInterface extends DataDispatcherSpyInterface
{
    public function addHeaders(array $headers);
    public function addCookies(array $cookies);
    public function setUrl(string $url);
}

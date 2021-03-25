<?php

namespace FormRelay\Request;

use FormRelay\Core\Initialization;
use FormRelay\Request\DataDispatcher\RequestDataDispatcher;
use FormRelay\Request\Route\RequestRoute;

class RequestInitialization extends Initialization
{
    const DATA_DISPATCHERS = [
        RequestDataDispatcher::class,
    ];
}

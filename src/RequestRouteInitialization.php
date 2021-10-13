<?php

namespace FormRelay\Request;

use FormRelay\Core\Initialization;
use FormRelay\Request\Route\RequestRoute;

class RequestRouteInitialization extends Initialization
{
    const ROUTES = [
        RequestRoute::class,
    ];
}

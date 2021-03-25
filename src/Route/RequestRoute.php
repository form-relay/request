<?php

namespace FormRelay\Request\Route;

use FormRelay\Core\ConfigurationResolver\ContentResolver\GeneralContentResolver;
use FormRelay\Core\ConfigurationResolver\Context\ConfigurationResolverContext;
use FormRelay\Core\DataDispatcher\DataDispatcherInterface;
use FormRelay\Core\Route\Route;
use FormRelay\Request\DataDispatcher\RequestDataDispatcher;
use FormRelay\Request\Exception\InvalidUrlException;

abstract class RequestRoute extends Route
{
    const KEY_URL = 'url';
    const DEFAULT_URL = '';

    protected function getUrl(): string
    {
        $url = $this->getConfig(static::KEY_URL);
        if ($url) {
            $url = $this->resolveContent($url);
        }
        return $url ? $url : '';
    }

    protected function getDispatcher()
    {
        $url = $this->getUrl();
        if (!$url) {
            $this->logger->error('no url provided for request dispatcher');
            return null;
        }
        try {
            /** @var RequestDataDispatcher $dispatcher */
            $dispatcher = $this->registry->getDataDispatcher('request');
            $dispatcher->setUrl($url);
            return $dispatcher;
        } catch (InvalidUrlException $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public static function getDefaultConfiguration(): array
    {
        return [
            static::KEY_ENABLED => static::DEFAULT_ENABLED,
            static::KEY_URL => static::DEFAULT_URL,
        ]
        + parent::getDefaultConfiguration();
    }
}

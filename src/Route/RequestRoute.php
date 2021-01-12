<?php

namespace FormRelay\Request\Route;

use FormRelay\Core\ConfigurationResolver\ContentResolver\GeneralContentResolver;
use FormRelay\Core\ConfigurationResolver\Context\ConfigurationResolverContext;
use FormRelay\Core\DataDispatcher\DataDispatcherInterface;
use FormRelay\Core\Route\Route;
use FormRelay\Request\DataDispatcher\RequestDataDispatcher;
use FormRelay\Request\Exception\InvalidUrlException;

class RequestRoute extends Route
{
    const KEY_URL = 'url';
    const DEFAULT_URL = '';

    protected function getUrl(): string
    {
        $url = $this->getConfig('url', '');
        if ($url) {
            $context = new ConfigurationResolverContext($this->submission);
            /** @var GeneralContentResolver $contentResolver */
            $contentResolver = $this->registry->getContentResolver('general', $url, $context);
            $url = $contentResolver->resolve();
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
            return $this->registry->getDataDispatcher('request', $url);
        } catch (InvalidUrlException $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public static function getDefaultConfiguration(): array
    {
        return [
            'enabled' => false,
            static::KEY_URL => static::DEFAULT_URL,
        ]
        + parent::getDefaultConfiguration();
    }
}

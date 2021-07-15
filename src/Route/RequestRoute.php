<?php

namespace FormRelay\Request\Route;

use FormRelay\Core\Model\Submission\SubmissionInterface;
use FormRelay\Core\Request\RequestInterface;
use FormRelay\Core\Route\Route;
use FormRelay\Request\DataDispatcher\RequestDataDispatcherInterface;
use FormRelay\Request\Exception\InvalidUrlException;

class RequestRoute extends Route
{
    const KEY_URL = 'url';
    const DEFAULT_URL = '';

    const KEY_COOKIES = 'cookies';
    const DEFAULT_COOKIES = [];

    protected function getUrl(): string
    {
        $url = $this->getConfig(static::KEY_URL);
        if ($url) {
            $url = $this->resolveContent($url);
        }
        return $url ? $url : '';
    }

    protected function getCookiesToPass(array $requestCookies): array
    {
        $cookies = [];
        $allowedCookieNames = $this->getConfig(static::KEY_COOKIES);
        foreach ($requestCookies as $name => $value) {
            foreach ($allowedCookieNames as $allowedCookieName) {
                if (preg_match('/^' . $allowedCookieName . '$/', $name)) {
                    $cookies[$name] = $value;
                    break;
                }
            }
        }
        return $cookies;
    }

    protected function getDispatcherKeyword(): string
    {
        return 'request';
    }

    protected function getDispatcher()
    {
        $url = $this->getUrl();
        if (!$url) {
            $this->logger->error('no url provided for request dispatcher');
            return null;
        }

        $cookies = $this->getCookiesToPass($this->submission->getContext()->getCookies());

        try {
            /** @var RequestDataDispatcherInterface $dispatcher */
            $dispatcher = $this->registry->getDataDispatcher($this->getDispatcherKeyword());
            $dispatcher->setUrl($url);
            $dispatcher->addCookies($cookies);
            return $dispatcher;
        } catch (InvalidUrlException $e) {
            $this->logger->error($e->getMessage());
            return null;
        }
    }

    public function addContext(SubmissionInterface $submission, RequestInterface $request)
    {
        parent::addContext($submission, $request);
        $cookies = $this->getCookiesToPass($request->getCookies());
        $submission->getContext()->addCookies($cookies);
    }

    public static function getDefaultConfiguration(): array
    {
        return [
            static::KEY_ENABLED => static::DEFAULT_ENABLED,
            static::KEY_URL => static::DEFAULT_URL,
            static::KEY_COOKIES => static::DEFAULT_COOKIES,
        ]
        + parent::getDefaultConfiguration();
    }
}

<?php

namespace FormRelay\Request\DataDispatcher;

use FormRelay\Core\DataDispatcher\DataDispatcherInterface;
use FormRelay\Request\Exception\InvalidUrlException;

interface RequestDataDispatcherInterface extends DataDispatcherInterface
{
    public function getHeaders(): array;
    public function setHeaders(array $headers);
    public function addHeader(string $name, string $value);
    public function addHeaders(array $headers);
    public function removeHeader(string $name);

    public function getCookies(): array;
    public function setCookies(array $cookies);
    public function addCookie(string $name, string $value);
    public function addCookies(array $cookies);
    public function removeCookie(string $name);

    public function getUrl(): string;
    /**
     * @param string $url
     * @throws InvalidUrlException
     */
    public function setUrl(string $url);

    public function getMethod(): string;
    public function setMethod(string $method);

    public function getAllowedStatusCodes(): array;

    /**
     * @param string|array $statusCodes
     */
    public function setAllowedStatusCodes($statusCodes);
}

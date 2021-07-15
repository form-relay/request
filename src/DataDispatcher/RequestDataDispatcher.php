<?php

namespace FormRelay\Request\DataDispatcher;

use FormRelay\Core\DataDispatcher\DataDispatcher;
use FormRelay\Core\Model\Form\DiscreteMultiValueField;
use FormRelay\Request\Exception\InvalidUrlException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;

class RequestDataDispatcher extends DataDispatcher implements RequestDataDispatcherInterface
{
    protected $method = 'POST';

    protected $url = '';
    protected $headers = [];
    protected $cookies = [];

    protected function getDefaultHeaders(): array
    {
        return [
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Accept' => '*/*',
        ];
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }

    public function addHeader(string $name, string $value)
    {
            $this->headers[$name] = $value;
        }

    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
    }

    public function removeHeader(string $name)
    {
        $this->addHeader($name, null);
    }

    public function getCookies(): array
    {
        return $this->cookies;
    }

    public function setCookies(array $cookies)
    {
        $this->cookies = $cookies;
    }

    public function addCookie(string $name, string $value)
    {
        if ($value === null) {
            unset($this->cookies[$name]);
        } else {
            $this->cookies[$name] = $value;
        }
    }

    public function addCookies(array $cookies)
    {
        foreach ($cookies as $name => $value) {
            $this->addCookie($name, $value);
        }
    }

    public function removeCookie(string $name)
    {
        $this->addCookie($name, null);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url)
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (empty($host)) {
            throw new InvalidUrlException($url);
        }
        $this->url = $url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function setMethod(string $method)
    {
        $this->method = $method;
    }

    /**
     * urlencode data and parse fields of type DiscreteMultiValueField
     * @param array formData $data
     * @return array
     */
    protected function parameterize(array $data)
    {
        $params = [];
        foreach ($data as $key => $value) {
            if ($value instanceof DiscreteMultiValueField) {
                foreach ($value as $multiValue) {
                    $params[] = rawurlencode($key) . '=' . rawurlencode($multiValue);
                }
            } else {
                $params[] = rawurlencode($key) . '=' . rawurlencode($value);
            }
        }
        return $params;
    }

    protected function buildBody(array $data): string
    {
        $params = $this->parameterize($data);
        return implode('&', $params);
    }

    protected function buildHeaders(array $data): array
    {
        $requestHeaders = $this->getDefaultHeaders();
        foreach ($this->headers as $key => $value) {
            if ($value === null) {
                unset($requestHeaders[$key]);
            } else {
                $requestHeaders[$key] = $value;
            }
        }
        return $requestHeaders;
    }

    protected function buildCookieJar(array $data): CookieJar
    {
        $requestCookies = [];
        if (!empty($this->cookies)) {
            $host = parse_url($this->url, PHP_URL_HOST);
            foreach ($this->cookies as $cKey => $cValue) {
                // Set up a cookie - name, value AND domain.
                $cookie = new SetCookie();
                $cookie->setName($cKey);
                $cookie->setValue(rawurlencode($cValue));
                $cookie->setDomain($host);
                $requestCookies[] = $cookie;
            }
        }
        return new CookieJar(false, $requestCookies);
    }

    public function send(array $data): bool
    {
        $requestOptions = [
            'body' => $this->buildBody($data),
            'cookies' => $this->buildCookieJar($data),
            'headers' => $this->buildHeaders($data),
        ];

        try {
            $client = new Client();
            $response = $client->request($this->method, $this->url, $requestOptions);
            $status_code = $response->getStatusCode();
            if ($status_code < 200 || $status_code >= 300) {
                $this->logger->error('request returned status code "' . $status_code . '"');
                return false;
            }
            return true;
        } catch (GuzzleException $e) {
            $this->logger->error($e->getMessage());
            return false;
        }
    }
}

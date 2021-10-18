<?php

namespace FormRelay\Request\DataDispatcher;

use FormRelay\Core\DataDispatcher\DataDispatcher;
use FormRelay\Core\Exception\FormRelayException;
use FormRelay\Core\Model\Form\DiscreteMultiValueField;
use FormRelay\Core\Utility\GeneralUtility;
use FormRelay\Request\Exception\InvalidUrlException;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

class RequestDataDispatcher extends DataDispatcher implements RequestDataDispatcherInterface
{
    protected $method = 'POST';

    protected $url = '';
    protected $headers = [];
    protected $cookies = [];

    /**
     * list of allowed response status codes
     * can have an "x" to allow all digits
     * example: 2xx
     *
     * @var array $allowedStatusCodes
     */
    protected $allowedStatusCodes = [];

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

    /**
     * @param string $name
     * @param string|null $value
     */
    public function addHeader(string $name, $value)
    {
        if ($value === null) {
            unset($this->headers[$name]);
        } else {
            $this->headers[$name] = $value;
        }
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

    /**
     * @param string $name
     * @param string|null $value
     */
    public function addCookie(string $name, $value)
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

    public function getAllowedStatusCodes(): array
    {
        return $this->allowedStatusCodes;
    }

    /**
     * @param string|array $statusCodes
     */
    public function setAllowedStatusCodes($statusCodes)
    {
        $statusCodes = GeneralUtility::castValueToArray($statusCodes);
        $this->allowedStatusCodes = $statusCodes;
    }

    /**
     * url-encode data and parse fields of type DiscreteMultiValueField
     *
     * @param array $data
     * @return array
     */
    protected function parameterize(array $data): array
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

    protected function checkStatusCode(int $statusCode): bool
    {
        if (empty($this->allowedStatusCodes)) {
            return true;
        }
        foreach ($this->allowedStatusCodes as $allowedStatusCode) {
            $pattern = '/^' . str_replace('x', '[0-9]', strtolower($allowedStatusCode)) . '$/';
            if (preg_match($pattern, (string)$statusCode)) {
                return true;
            }
        }
        return false;
    }

    protected function validateResponse(ResponseInterface $response)
    {
        $statusCode = $response->getStatusCode();
        if (!$this->checkStatusCode($statusCode)) {
            throw new FormRelayException('request returned status code "' . $statusCode . '"');
        }
    }

    public function send(array $data)
    {
        $requestOptions = [
            'body' => $this->buildBody($data),
            'cookies' => $this->buildCookieJar($data),
            'headers' => $this->buildHeaders($data),
        ];

        try {
            $client = new Client();
            $response = $client->request($this->method, $this->url, $requestOptions);
            $this->validateResponse($response);
        } catch (GuzzleException $e) {
            throw new FormRelayException($e->getMessage(), $e->getCode(), $e);
        }
    }
}

<?php

namespace HealthChecker\Service;

use Symfony\Component\DomCrawler\Crawler;

class HttpService extends AbstractService
{
    protected $method;
    protected $host;
    protected $path;
    protected $headers = [];
    protected $content = '';
    protected $options = [];
    protected $response;

    public function validateRequiredParams()
    {
        if ( ! isset($this->config['request'])) {
            throw new Exception(sprintf("Request params are required for service of type '%s'", $this->config['type']));
        }

        if ( ! isset($this->config['request']['url'])) {
            throw new Exception(sprintf("URL parameter is required in request params for service of type '%s'", $this->config['type']));
        }
    }

    public function init()
    {
        $request = $this->config['request'];

        // get URL
        $url = $request['url'];

        // parse scheme, host and port
        $urlParts = parse_url($url);
        $scheme = isset($urlParts['scheme']) ? $urlParts['scheme'] : 'http';
        $port = isset($urlParts['port']) ? $urlParts['port'] : 80;
        $this->path = $urlParts['path'];
        $this->host = $scheme . '://' . $urlParts['host'] . ':' . $port;

        // get HTTP method
        $this->method = isset($request['method']) && in_array(strtoupper($request['method']), ['GET', 'POST', 'HEAD']) ? strtoupper($request['method']) : 'GET';

        // get HTTP headers
        if (isset($request['headers'])) {
            foreach ($request['headers'] as $key => $value) {
                $this->headers[ucfirst($key)] = $value;
            }
        }

        // get cookies
        if (isset($request['cookies'])) {
            $cookies = [];
            foreach ($request['cookies'] as $name => $value) {
                $cookies[] = $name . '=' . urlencode($value);
            }
            $this->headers['Cookie'] = implode('; ', $cookies);
        }

        // get POST params
        if ( ! empty($request['post_params'])) {
            $this->content = http_build_query($request['post_params']);
        }

        // get connection options
        if (isset($request['timeout'])) {
            $this->options[CURLOPT_TIMEOUT] = floatval($request['timeout']);
        }
        if (isset($request['follow_redirects'])) {
            $this->options[CURLOPT_FOLLOWLOCATION] = (bool)$request['follow_redirects'];
        }
    }

    protected function getResponse()
    {
        if ( ! isset($this->response)) {
            $request = new \Buzz\Message\Request($this->method, $this->path, $this->host);
            $response = new \Buzz\Message\Response();
            $client = new \Buzz\Client\Curl();

            $client->send($request, $response, $this->options);

            $this->response = $response;
        }

        return $this->response;
    }

    public function assertStatusIs($expectedStatusCode)
    {
        $actualStatusCode = $this->getResponse()->getStatusCode();

        if ($actualStatusCode !== $expectedStatusCode) {
            $this->errors['assertStatusIs'] = sprintf('Expected status code is %d, actual status is %d', $expectedStatusCode, $actualStatusCode);

            return false;
        }

        return true;
    }

    public function assertContentTypeIs($expectedContentType)
    {
        $actualContentType = explode(';', $this->getResponse()->getHeader('Content-Type'))[0];

        if ($actualContentType !== $expectedContentType) {
            $this->errors['assertContentTypeIs'] = sprintf('Expected content type is %s, actual content type is %s', $expectedContentType, $actualContentType);

            return false;
        }

        return true;
    }

    public function assertResponseTextContains(array $expectedStrings)
    {
        $responseContent = $this->getResponse()->getContent();

        foreach ($expectedStrings as $expectedString) {
            if (strpos($responseContent, $expectedString) === false) {
                $this->errors['assertResponseTextContains'] = sprintf("Expected string '%s' not found in response content", $expectedString);

                return false;
            }
        }

        return true;
    }
}
<?php
namespace Acme;

use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Response;

class Client extends AbstractBrowser
{
    protected function doRequest($request): Response
    {
        $uri = $request->getUri();
        $method = $request->getMethod();
        $body = $request->getContent();

        // Extract headers from server vars
        $server = $request->getServer();
        $headers = [];
        foreach ($server as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $headerName = str_replace('HTTP_', '', $key);
                $headerName = strtolower(str_replace('_', '-', $headerName));
                $headers[$headerName] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $headers[strtolower(str_replace('_', '-', $key))] = $value;
            }
        }

        // Initialize curl
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $uri);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // Set headers
        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = $name . ': ' . $value;
        }
        if ($curlHeaders) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        }

        // Handle cookies if jar is set
        if ($this->getCookieJar()) {
            $cookieJar = $this->getCookieJar();
            $cookies = $cookieJar->allValues($uri);
            if ($cookies) {
                curl_setopt($ch, CURLOPT_COOKIE, implode('; ', array_map(function($k, $v) {
                    return $k . '=' . $v;
                }, array_keys($cookies), $cookies)));
            }
        }

        // Execute request
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        // Separate headers and body
        $responseHeadersString = substr($response, 0, $headerSize);
        $content = substr($response, $headerSize);

        // Parse headers
        $responseHeaders = [];
        $headerLines = explode("\r\n", $responseHeadersString);
        foreach ($headerLines as $line) {
            if (strpos($line, ': ') !== false) {
                list($name, $value) = explode(': ', $line, 2);
                $responseHeaders[$name] = $value;
            }
        }

        // Update cookie jar with response cookies
        if ($this->getCookieJar() && isset($responseHeaders['Set-Cookie'])) {
            $cookieJar = $this->getCookieJar();
            // Simple parsing, assuming single cookie
            $cookie = $responseHeaders['Set-Cookie'];
            // This is simplistic; real parsing would be more complex
            if (preg_match('/([^=]+)=([^;]+)/', $cookie, $matches)) {
                $cookieJar->set(new \Symfony\Component\BrowserKit\Cookie($matches[1], $matches[2], null, null, $uri));
            }
        }

        return new Response($content, $status, $responseHeaders);
    }
}

<?php

namespace VeraTag\VeraTagBackend;

final class HttpClient
{
    private const DEFAULT_TIMEOUT     = 20;
    private const CHANNEL_TYPE_HEADER = 'X-Cms-Plugin-Channel-Type';
    private const VERSION_HEADER      = 'X-Cms-Plugin-Version';

    private readonly PluginIdentity $plugin_identity;

    public function __construct(?PluginIdentity $plugin_identity = null)
    {
        $this->plugin_identity = $plugin_identity ?? new PluginIdentity();
    }

    public function get(string $url, array $headers = array(), int $timeout = self::DEFAULT_TIMEOUT, ?int $maximum_response_size = null): HttpResponse
    {
        return $this->request('GET', $url, $headers, null, $timeout, $maximum_response_size);
    }

    public function post(string $url, array $headers = array(), array|string|null $body = null, int $timeout = self::DEFAULT_TIMEOUT): HttpResponse
    {
        return $this->request('POST', $url, $headers, $body, $timeout);
    }

    public function put(string $url, array $headers = array(), array|string|null $body = null, int $timeout = self::DEFAULT_TIMEOUT): HttpResponse
    {
        return $this->request('PUT', $url, $headers, $body, $timeout);
    }

    public function patch(string $url, array $headers = array(), array|string|null $body = null, int $timeout = self::DEFAULT_TIMEOUT): HttpResponse
    {
        return $this->request('PATCH', $url, $headers, $body, $timeout);
    }

    public function delete(string $url, array $headers = array(), array|string|null $body = null, int $timeout = self::DEFAULT_TIMEOUT): HttpResponse
    {
        return $this->request('DELETE', $url, $headers, $body, $timeout);
    }

    private function request(string $method, string $url, array $headers, array|string|null $body, int $timeout, ?int $maximum_response_size = null): HttpResponse
    {
        $normalized_url = strtolower(trim($url));

        if (0 !== strpos($normalized_url, 'https://')) {
            return new HttpResponse(0, '', array(), '', 'Backend requests must use HTTPS.');
        }

        if (null !== $maximum_response_size && 0 >= $maximum_response_size) {
            return new HttpResponse(0, '', array(), '', 'The maximum response size must be greater than zero.');
        }

        $request_headers = array_merge(
            array(
                'Accept'                  => 'application/json',
                self::CHANNEL_TYPE_HEADER => $this->plugin_identity->getChannelType(),
                self::VERSION_HEADER      => $this->plugin_identity->getVersion(),
            ),
            $headers
        );

        $request_arguments = array(
            'method'             => $method,
            'headers'            => $request_headers,
            'timeout'            => $timeout,
            'redirection'        => 0,
            'sslverify'          => true,
            'reject_unsafe_urls' => true,
        );

        if (null !== $maximum_response_size) {
            $request_arguments['limit_response_size'] = $maximum_response_size;
        }

        if (null !== $body) {
            if (is_array($body)) {
                $request_headers['Content-Type'] = 'application/json';
                $request_arguments['headers']    = $request_headers;
                $request_arguments['body']       = wp_json_encode($body);
            } else {
                $request_arguments['body'] = $body;
            }
        }

        $response = wp_safe_remote_request($url, $request_arguments);

        if (is_wp_error($response)) {
            return new HttpResponse(
                0,
                '',
                array(),
                '',
                $response->get_error_message()
            );
        }

        return new HttpResponse(
            (int) wp_remote_retrieve_response_code($response),
            (string) wp_remote_retrieve_response_message($response),
            (array) wp_remote_retrieve_headers($response),
            wp_remote_retrieve_body($response),
            null
        );
    }
}

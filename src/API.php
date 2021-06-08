<?php

namespace wondernetwork\wiuphp;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use wondernetwork\wiuphp\Exception;

class API implements APIInterface {
    const ENDPOINT = 'https://api.wheresitup.com/v4/';

    protected Client $client;
    protected array $validTests   = [
        'dig', 'host', 'ping', 'http', 'fast', 'edge', 'trace', 'shot', 'nametime'
    ];
    protected array $validSchemes = ['https', 'http', 'ftp'];

    public function __construct($id, $token, Client $client = null) {

        if (!$client) {
            $client = new Client(['base_uri' => self::ENDPOINT]);
        }

        if (!$client->getConfig('base_uri')) {
            throw new Exception\ClientException('API client is not configured with a base URL');
        }
        if (!ctype_xdigit($id) || !ctype_xdigit($token)) {
            throw new Exception\ClientException('User credentials are invalid');
        }

        $config = $client->getConfig();
        $config['headers'] = [
            'Auth' => "Bearer $id $token",
            'User-Agent' => 'WIU PHP Client/1.0'
        ];
        $this->client = new Client($config);
    }

    public function getClientConfig(string $config) {
        return $this->client->getConfig($config);
    }

    public function servers() {
        return $this->get('sources')['sources'];
    }

    public function submit($uri, array $servers, array $tests, array $options = []) {
        $uri = strip_tags($uri);
        if (!filter_var($uri, FILTER_VALIDATE_URL)) {
            $uri = filter_var('http://'.$uri, FILTER_VALIDATE_URL);
        }

        if (
            !$uri
            || ($parsed = parse_url($uri)) === false
            || !isset($parsed['host'])
            || !in_array($parsed['scheme'], $this->validSchemes)
        ) {
            throw new Exception\ClientException('Requested address is missing or invalid');
        }

        $servers = array_values(array_filter($servers, function($server) {
            return preg_match('/^[a-z]+$/', $server);
        }));
        if (!$servers) {
            throw new Exception\ClientException('No valid servers requested');
        }

        $tests = array_values(array_intersect($this->validTests, $tests));
        if (!$tests) {
            throw new Exception\ClientException('No valid tests requested');
        }

        $post = [
            'uri' => $uri,
            'sources' => $servers,
            'tests' => $tests,
            'options' => $options
        ];

        $response = $this->post('jobs', $post);
        return $response['jobID'];
    }

    public function submitRaw($request) {
        if (!is_string($request)) {
            throw new Exception\ClientException('Raw request must be a string');
        }

        if (is_null($request = json_decode($request, true)) || !is_array($request)) {
            throw new Exception\ClientException('Failed to decode raw request JSON');
        }

        $this->submit(
            isset($request['uri']) ? $request['uri'] : '',
            isset($request['sources']) && is_array($request['sources']) ? $request['sources'] : [],
            isset($request['tests']) && is_array($request['tests']) ? $request['tests'] : [],
            isset($request['options']) && is_array($request['options']) ? $request['options'] : []

        );
    }


    public function retrieve($id) {
        if (!ctype_xdigit($id)) {
            throw new Exception\ClientException('Job ID is invalid');
        }

        return $this->get("jobs/$id");
    }

    protected function get($endpoint) {
        try {
            $response = $this->client->get($endpoint);
        } catch (BadResponseException $e) {
            throw new Exception\APIException($e);
        }

        return json_decode($response->getBody(), true);
    }

    protected function post($endpoint, $data) {
        try {
            $response = $this->client->post($endpoint, [
                RequestOptions::JSON => $data,
            ]);
        } catch (BadResponseException $e) {
            throw new Exception\APIException($e);
        }

        return json_decode($response->getBody(), true);
    }
}

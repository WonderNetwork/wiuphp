<?php

namespace wondernetwork\wiuphp;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\TransferException;
use wondernetwork\wiuphp\Exception;

class API implements APIInterface {
    const ENDPOINT = 'https://api.wheresitup.com/v4/';

    protected $client;
    protected $auth;
    protected $validTests = [
        'dig', 'host', 'ping', 'http', 'fast', 'edge', 'trace', 'shot', 'nametime'
    ];

    public function __construct($id, $token, Client $client = null) {

        if (!$client) {
            $client = new Client(['base_url' => self::ENDPOINT]);
        }

        if (!$client->getBaseUrl()) {
            throw new Exception\ClientException('API client is not configured with a base URL');
        }
        if (!ctype_xdigit($id) || !ctype_xdigit($token)) {
            throw new Exception\ClientException('User credentials are invalid');
        }

        $client->setDefaultOption('headers', [
            'Auth' => "Bearer $id $token",
            'User-Agent' => 'WIU PHP Client/1.0'
        ]);
        $this->client = $client;
    }

    public function servers() {
        return $this->get('sources')['sources'];
    }

    public function submit($uri, array $servers, array $tests, array $options = []) {
        $uri = filter_var($uri, FILTER_SANITIZE_URL);
        $parsed = parse_url($uri);
        if ($parsed && !isset($parsed['scheme'])) {
            $uri = 'http://'.$uri;
            $parsed = parse_url($uri);
        }
        if ($parsed === false || !isset($parsed['host'])) {
            throw new Exception\ClientException('Requested address is missing or invalid');
        }

        $servers = array_filter($servers, function($server) {
            return preg_match('/^[a-z]+$/', $server);
        });
        if (!$servers) {
            throw new Exception\ClientException('No valid servers requested');
        }

        $tests = array_intersect($this->validTests, $tests);
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

        return $response->json();
    }

    protected function post($endpoint, $data) {
        try {
            $response = $this->client->post($endpoint, [
                'json' => $data,
            ]);
        } catch (BadResponseException $e) {
            throw new Exception\APIException($e);
        }

        return $response->json();
    }
}

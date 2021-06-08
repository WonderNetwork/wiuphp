<?php

namespace wondernetwork\wiuphp\Exception;

use GuzzleHttp\Exception\RequestException;

class APIException extends Exception {
    public function __construct(RequestException $e, $message = '') {
        $prefix = ($message) ?: "Bad response from the WIU API";
        if ($e->getCode()) {
            $prefix .= " (HTTP status {$e->getCode()})";
        }

        $response = $e->getResponse();
        $body = json_decode($response->getBody(), true);
        $message = isset($body['message'])
            ? $body['message']
            : $response->getReasonPhrase();

        parent::__construct("$prefix: $message", $e->getCode(), null);
    }
}

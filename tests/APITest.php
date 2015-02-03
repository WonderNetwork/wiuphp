<?php

namespace wondernetwork\wiuphp\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use GuzzleHttp\Subscriber\Mock;
use wondernetwork\wiuphp\API;

class APITest extends \PHPUnit_Framework_TestCase {
    /** @var Client */
    protected $client;
    /** @var Mock */
    protected $mock;

    public function setUp() {
        $this->client = new Client(['base_url' => 'foo']);
        $this->mock = new Mock();

        $this->client->getEmitter()->attach($this->mock);
    }

    public function addMockResponse($status, $body = '') {
        $this->mock->addResponse(new Response($status, [], Stream::factory($body)));
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage User credentials are invalid
     */
    public function testInvalidID() {
        new API('this is not hex', '1234');
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage User credentials are invalid
     */
    public function testInvalidToken() {
        new API('1234', 'this is also not hex');
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage API client is not configured with a base URL
     */
    public function testBadClient() {
        $client = new Client();
        new API('1234', '1234', $client);
    }

    public function testAuthHeaderSet() {
        $api = new API('1234', '4321', $this->client);

        $headers = $this->client->getDefaultOption('headers');
        $this->assertEquals('Bearer 1234 4321', $headers['Auth']);
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage Requested address is missing or invalid
     *
     * @dataProvider badURLs
     */
    public function testBadURL($url) {
        $api = new API('1234', '1234', $this->client);
        $api->submit($url, [], []);
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage No valid servers requested
     *
     * @dataProvider goodURLs
     */
    public function testGoodURL($url) {
        $api = new API('1234', '1234', $this->client);
        $api->submit($url, [], []);
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage No valid servers requested
     *
     * @dataProvider badServers
     */
    public function testBadServers($servers) {
        $api = new API('1234', '1234', $this->client);
        $api->submit('google.com', $servers, []);
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage No valid tests requested
     *
     * @dataProvider badTests
     */
    public function testBadTests($tests) {
        $api = new API('1234', '1234', $this->client);
        $api->submit('foo', ['bar'], $tests);
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\APIException
     * @expectedExceptionMessage Bad response from the WIU API (HTTP status 403): foo bar
     * @expectedExceptionCode 403
     */
    public function testAPIExceptionOnSubmit() {
        $this->addMockResponse(403, '{"message": "foo bar"}');

        $api = new API('1234', '1234', $this->client);
        $api->submit('foo', ['bar'], ['dig']);
    }

    public function testSubmitSuccess() {
        $this->addMockResponse(200, '{"jobID": "bizbaz"}');

        $api = new API('1234', '1234', $this->client);
        $this->assertEquals("bizbaz", $api->submit('foo', ['bar'], ['dig']));
    }

    /**
     * @expectedException \wondernetwork\wiuphp\Exception\ClientException
     * @expectedExceptionMessage Job ID is invalid
     *
     * @dataProvider badIDs
     */
    public function testRetrieveBadID($id) {
        $api = new API('1234', '1234', $this->client);
        $api->retrieve($id);
    }

    public function testRetrieveSuccess() {
        $this->addMockResponse(200, '{"foo": "bar"}');

        $api = new API('1234', '1234', $this->client);
        $this->assertEquals(['foo' => 'bar'], $api->retrieve('1234'));
    }

    public function testServers() {
        $this->addMockResponse(200, '{"sources": ["foo", "bar"]}');

        $api = new API('1234', '1234', $this->client);
        $this->assertEquals(['foo', 'bar'], $api->servers());
    }

    public function badURLs() {
        return [
            ['/://:<>this is not a url'],
            ['#$%^&*()(*&^%$#$%^&*()(*&^%$%^&*('],
            [''],
            ["<script>alert('hi');</script>"],
            ['php://filter'],
            ['foo://bar'],
            ['mailto:person@something'],
            ['javascript://alert(123)'],
        ];
    }

    public function goodURLs() {
        return [
            ['google.com'],
            ['http://google.com'],
            ['https://google.com'],
            ['ftp://google.com'],
            ['google.com/asdf'],
            ['google.com/asdf?1=2&3=4'],
            ['google.com/asdf?a[]=1&a[]=2'],
            ['google.com/asdf?a=this+has+spaces'],
            ['google.com/asdf?a=this%20has%20spaces'],
        ];
    }

    public function badServers() {
        return [
            [[]],
            [['1234', 'ja9f87hfha', '$%^&*(', '', null]]
        ];
    }

    public function badTests() {
        return [
            [[]],
            [['none', 'of', 'these', 'are', 'valid', '', null]]
        ];
    }

    public function badIDs() {
        return [
            ['this is not hex'],
            ['asdf'],
            [''],
            [null],
        ];
    }
}

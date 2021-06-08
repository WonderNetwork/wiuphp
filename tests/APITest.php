<?php

namespace wondernetwork\wiuphp\tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use wondernetwork\wiuphp\API;
use wondernetwork\wiuphp\Exception\APIException;
use wondernetwork\wiuphp\Exception\ClientException;

class APITest extends TestCase {
    protected Client $client;
    protected HandlerStack $stack;

    public function setUp(): void {
        $this->stack = new HandlerStack();
        $this->client = new Client(['base_uri' => 'foo', 'handler' => $this->stack]);
    }

    public function addMockResponse($status, $body = '') {
        $this->stack->setHandler(MockHandler::createWithMiddleware([new Response($status, [], $body)]));
    }

    public function testInvalidID() {
        $this->expectException(ClientException::class);
        $this->expectExceptionMessage('User credentials are invalid');

        new API('this is not hex', '1234');
    }

    public function testInvalidToken() {
        $this->expectExceptionMessage("User credentials are invalid");
        $this->expectException(ClientException::class);

        new API('1234', 'this is also not hex');
    }

    public function testBadClient() {
        $this->expectExceptionMessage("API client is not configured with a base URL");
        $this->expectException(ClientException::class);

        $client = new Client();
        new API('1234', '1234', $client);
    }

    public function testAuthHeaderSet() {
        $api = new API('1234', '4321', $this->client);

        $headers = $api->getClientConfig('headers');
        $this->assertEquals('Bearer 1234 4321', $headers['Auth']);
    }

    /** @dataProvider badURLs */
    public function testBadURL($url) {
        $this->expectExceptionMessage("Requested address is missing or invalid");
        $this->expectException(ClientException::class);

        $api = new API('1234', '1234', $this->client);
        $api->submit($url, [], []);
    }

    /** @dataProvider goodURLs */
    public function testGoodURL($url) {
        $this->expectExceptionMessage("No valid servers requested");
        $this->expectException(ClientException::class);

        $api = new API('1234', '1234', $this->client);
        $api->submit($url, [], []);
    }

    /** @dataProvider badServers */
    public function testBadServers($servers) {
        $this->expectExceptionMessage("No valid servers requested");
        $this->expectException(ClientException::class);

        $api = new API('1234', '1234', $this->client);
        $api->submit('google.com', $servers, []);
    }

    /** @dataProvider badTests */
    public function testBadTests($tests) {
        $this->expectExceptionMessage("No valid tests requested");
        $this->expectException(ClientException::class);
        $api = new API('1234', '1234', $this->client);
        $api->submit('foo', ['bar'], $tests);
    }

    public function testAPIExceptionOnSubmit() {
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage("Bad response from the WIU API (HTTP status 403): foo bar");
        $this->expectException(APIException::class);

        $this->addMockResponse(403, '{"message": "foo bar"}');

        $api = new API('1234', '1234', $this->client);
        $api->submit('foo', ['bar'], ['dig']);
    }

    public function testSubmitSuccess() {
        $this->addMockResponse(200, '{"jobID": "bizbaz"}');

        $api = new API('1234', '1234', $this->client);
        $this->assertEquals("bizbaz", $api->submit('foo', ['bar'], ['dig']));
    }

    /** @dataProvider badIDs */
    public function testRetrieveBadID($id) {
        $this->expectExceptionMessage("Job ID is invalid");
        $this->expectException(ClientException::class);
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

    /** @dataProvider invalidRaw */
    public function testSubmitRawRequiresString($request) {
        $this->expectExceptionMessage("Raw request must be a string");
        $this->expectException(ClientException::class);
        $api = new API('1234', '1234', $this->client);
        $api->submitRaw($request);
    }

    public function invalidRaw() {
        return [
            [null],
            [false],
            [true],
            [1234],
            [[]],
            [(object) []],
        ];
    }

    /** @dataProvider invalidRawString */
    public function testSubmitRawRequiresValidJSON($request) {
        $this->expectExceptionMessage("Failed to decode raw request JSON");
        $this->expectException(ClientException::class);
        $api = new API('1234', '1234', $this->client);
        $api->submitRaw($request);
    }

    public function invalidRawString() {
        return [
            [''],
            ['asdf'],
            ['"asdf"'],
            ['this {} is not valid'],
            ['{ also not valid }'],
            ['[ continuing to be invalid ]'],
            ['false'],
        ];
    }

    /** @dataProvider rawRequest */
    public function testSubmitRawForwardsRequest(
        $request,
        $uri,
        $servers,
        $tests,
        $options
    ) {
        $api = $this->getMockBuilder('\wondernetwork\wiuphp\API')
                    ->disableOriginalConstructor()
                    ->setMethods(['submit'])
                    ->getMock();
        $api->expects($this->once())
            ->method('submit')
            ->with($uri, $servers, $tests, $options);

        $api->submitRaw($request);
    }

    public function rawRequest() {
        return [
            ['[]', '', [], [], []],
            ['{}', '', [], [], []],
            ['{ "unrecognized": "things" }', '', [], [], []],
            ['{ "uri": "foo" }', 'foo', [], [], []],
            ['{ "uri": "foo" }', 'foo', [], [], []],
            ['{ "uri": "" }', '', [], [], []],
            ['{ "options": "invalid" }', '', [], [], []],
            ['{ "tests": "invalid" }', '', [], [], []],
            ['{ "sources": "invalid" }', '', [], [], []],
            ['{ "sources": [] }', '', [], [], []],
            ['{ "sources": [1, 2] }', '', [1, 2], [], []],
            ['{ "tests": [1, 2] }', '', [], [1, 2], []],
            ['{ "options": [1, 2] }', '', [], [], [1, 2]],
            ['{ "options": { "things": "stuff" } }', '', [], [], ['things' => 'stuff']],
            [
                '{
                "uri": "google.com",
                "tests": ["dig", "host"],
                "sources": ["chicago", "denver"],
                "options": {
                  "expire_after": "3 days",
                  "timeout": 60,
                  "dig": {
                    "nameserver": "localhost"
                  }
                }
                }',
                'google.com',
                ['chicago', 'denver'],
                ['dig', 'host'],
                ['expire_after' => '3 days', 'timeout' => 60, 'dig' => ['nameserver' => 'localhost']]
            ],
        ];
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

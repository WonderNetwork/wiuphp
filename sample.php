<?php

use wondernetwork\wiuphp;

require_once __DIR__.'/vendor/autoload.php';

/*
 * set up the API with your client ID and token
 */
$api = new wiuphp\API('YOUR_CLIENT_ID', 'YOUR_CLIENT_TOKEN');

/*
 * if you're using Memcached, you can cache results to avoid duplicate API
 * calls using the MemcachedAPI decorator:
 */
$api = new wiuphp\MemcachedAPI(
    new wiuphp\API('YOUR_CLIENT_ID', 'YOUR_CLIENT_TOKEN'),
    new Memcached()
);

/*
 * get the list of available edge servers
 */
$servers = $api->servers();

/*
 * submit a new request to ping http://google.com from Denver and London
 */
$jobID = $api->submit('google.com', ['denver', 'london'], ['ping']);

/*
 * retrieve the job results
 */
$job = $api->retrieve($jobID);

/*
 * poll the job results for 10 seconds or until the results are complete
 */
$seconds = 0; $maxSeconds = 10;
do {
    sleep(1);
    $job = $api->retrieve($jobID);
} while ($job['response']['in_progress'] && $seconds++ < $maxSeconds);
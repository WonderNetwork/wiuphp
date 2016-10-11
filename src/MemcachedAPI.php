<?php

namespace wondernetwork\wiuphp;

class MemcachedAPI implements APIInterface {
    const NS = 'wiuphp.';

    protected $api;
    protected $cache;
    protected $ttl;

    public function __construct(APIInterface $api, \Memcached $cache, $ttl = 21600) {
        $this->api = $api;
        $this->cache = $cache;
        $this->ttl = ($ttl >= 0) ? $ttl : 0;
    }

    public function servers() {
        $key = self::NS.'servers';

        $servers = $this->cache->get($key);
        if ($this->cache->getResultCode() === \Memcached::RES_NOTFOUND) {
            $servers = $this->api->servers();
            $this->cache->set($key, $servers, $this->ttl);
        }

        return $servers;
    }

    public function submit($uri, array $servers, array $tests, array $options = []) {
        return $this->api->submit($uri, $servers, $tests, $options);
    }

    public function submitRaw($request) {
        return $this->api->submitRaw($request);
    }

    public function retrieve($id) {
        $key = self::NS.'job_'.$id;

        $job = $this->cache->get($key);
        if ($this->cache->getResultCode() === \Memcached::RES_NOTFOUND) {
            $job = $this->api->retrieve($id);

            /* only cache if the job is done */
            if (!$job['response']['in_progress']) {
                $this->cache->set($key, $job, $this->ttl);
            }
        }

        return $job;
    }
}


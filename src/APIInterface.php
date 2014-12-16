<?php

namespace wondernetwork\wiuphp;

interface APIInterface {

    /** Get all the available edge servers
     *
     * @return array indexed array, one element per server
     */
    public function servers();

    /** Submit a new job
     *
     * @param string $uri     URI to test
     * @param array  $servers servers to test from
     * @param array  $tests   tests to perform
     * @param array  $options test-specific settings
     *
     * @return string the ID of the job submitted
     */
    public function submit($uri, array $servers, array $tests, array $options = []);

    /** Get the results of a job
     *
     * @param string $id
     *
     * @return array full job results (see API docs for content details)
     */
    public function retrieve($id);
}

<?php

namespace BCLibCoop\SitkaCarousel;

/**
 * A basic OSRF Query generator and parser
 */
class OSRFQuery
{
    protected $catalogueUrl;
    protected $query;
    protected $results;

    /**
     * Query the OSRF HTTP translator and returns a parsed result
     */
    public function __construct($request_data, $library_cat_url = '')
    {
        $this->catalogueUrl = Constants::EG_URL;

        $cat_suffix = array_filter(explode('.', $library_cat_url));
        $cat_suffix = end($cat_suffix);

        if (!empty($cat_suffix) && !in_array($cat_suffix, Constants::PROD_LIBS)) {
            $this->catalogueUrl = 'https://' . $cat_suffix . Constants::CATALOGUE_SUFFIX;
        }

        // Build the request
        $this->query = $this->osrfHttpQueryBuilder($request_data);

        // Post to the translator service
        $eg_query_result = wp_remote_post(
            $this->catalogueUrl . '/osrf-http-translator',
            $this->query
        );

        // If the request completely errored, return null
        if (wp_remote_retrieve_response_code($eg_query_result) !== 200) {
            return null;
        }

        /**
         * Do some best-effort checking of the returned response. The translator
         * service seems to not be great about returning error status codes when then
         * are no results or otherwise an error, and the nexting level of the data we
         * want is inconsistent, so we do our best to check for errors and return data
         * at a soemwhat useful point
         */
        if ($json_result = json_decode(wp_remote_retrieve_body($eg_query_result))) {
            // Check for status message
            foreach ($json_result as $osrf_message) {
                if (isset($osrf_message->__p) && $osrf_message->__p->type === 'STATUS') {
                    $status_code = $osrf_message->__p->payload->__p->statusCode;

                    // If the internal status code isn't in the 1xx or 2xx range, return null
                    if ($status_code >= 300) {
                        return null;
                    }

                    break;
                }
            }

            // Check for results message
            foreach ($json_result as $osrf_message) {
                if (isset($osrf_message->__p) && $osrf_message->__p->type === 'RESULT') {
                    $content = $osrf_message->__p->payload->__p->content;

                    // Status codes don't seem to indicate a true bad response,
                    // stacktrace seems to be a somewhat reliable way of finding an error
                    if (isset($content->stacktrace)) {
                        return null;
                    }

                    // Sometimes nested one more level, sometimes not.
                    $this->results[] = $content->__p ?? $content;
                }
            }
        }
    }

    public function getResult()
    {
        return count($this->results) > 1 ? $this->results : $this->results[0];
    }

    /**
     * Helper function to generate the WP_Http::request() args for an OSRF
     * request
     *
     * @param array $query_data
     * @return array
     */
    protected function osrfHttpQueryBuilder($request_data)
    {
        $request = [
            'timeout' => Constants::QUERY_TIMEOUT,
            'headers' => ['X-OpenSRF-service' => $request_data['service']],
        ];

        $osrf_msg = [];

        $osrf_msg[] = [
            '__c' => 'osrfMessage',
            '__p' => [
                'threadTrace' => '0',
                'locale' => 'en-US',
                'type' => 'REQUEST',
                'payload' => [
                    '__c' => 'osrfMethod',
                    '__p' => [
                        'method' => $request_data['method'],
                        'params' => $request_data['params'],
                    ],
                ],
            ],
        ];

        $request['body'] = 'osrf-msg=' . json_encode($osrf_msg);

        return $request;
    }
}

<?php

namespace Drupal\content_direct;

/**
 * This interface as representing objects that can push content to a remote site
 *
 * @ingroup content_direct
 */
interface ContentPusherInterface
{

    /**
     * Make an HTTP Request.
     *
     * @param string $method
     *   The HTTP method to be used.
     * @param string $uri
     *   The URI resource to which the HTTP request will be made.
     * @param array $request_options
     *   An array of options passed directly to the request.
     *
     * @see http://gsa.github.io/slate
     * @see http://guzzle.readthedocs.org/en/5.3/quickstart.html
     *
     * @return bool
     *   Return if request successfully
     */
    public function request($method, $uri, $request_options);

}
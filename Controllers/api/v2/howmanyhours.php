<?php
/**
 * Minds FAQ
 *
 * @version 2
 * @author Juan Manuel Solaro
 */
namespace Minds\Controllers\api\v2;

use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Interfaces;
use Minds\Api\Factory;
use Minds\Entities;

class howmanyhours implements Interfaces\Api
{
    /**
     * returns seconds since user registered
     * @param  
     * @return mixed|null
     */
    public function get($pages)
    {
        // Prepare response
        $response = [];

        // Get user from session and use time_created data for response
        $user = Core\Session::getLoggedInUser();
        $response['seconds'] = $user->time_created;

        // Return response
        return Factory::response($response);
    }

    /**
     * Equivalent to HTTP POST method
     * @param  array $pages
     * @return mixed|null
     */
    public function post($pages)
    {
        return Factory::response([]);
    }

    /**
     * Equivalent to HTTP PUT method
     * @param  array $pages
     * @return mixed|null
     */
    public function put($pages)
    {
        return Factory::response([]);
    }

    /**
     * Equivalent to HTTP DELETE method
     * @param  array $pages
     * @return mixed|null
     */
    public function delete($pages)
    {
        return Factory::response([]);
    }
}

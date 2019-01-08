<?php

namespace Minds\Controllers\api\v2\entities;

use Minds\Api\Factory;
use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Interfaces;

class suggested implements Interfaces\Api
{
    /**
     * Gets a list of suggested hashtags, including the ones the user has opted in
     * @param array $pages
     * @throws \Exception
     */
    public function get($pages)
    {
        Factory::isLoggedIn();

        $type = '';
        switch ($pages[0]) {
            case 'activities':
                $type = 'activity';
                break;
            case 'channels':
                $type = 'user';
                break;
            case 'images':
                $type = 'object:image';
                break;
            case 'videos':
                $type = 'object:video';
                break;
            case 'groups':
                $type = 'group';
                break;
            case 'blogs':
                $type = 'object:blog';
                break;
        }

        $all = isset($pages[1]) && $pages[1] === 'all';

        $offset = 0;

        if (isset($_GET['offset'])) {
            $offset = intval($_GET['offset']);
        }

        $limit = 12;

        if (isset($_GET['limit'])) {
            $offset = intval($_GET['offset']);
        }

        $rating = 1;
        if (isset($_GET['rating'])) {
            $rating = intval($_GET['rating']);
        }

        if ($type == 'user') {
            $rating = 1;
        }

        $hashtag = null;
        if (isset($_GET['hashtag'])) {
            $hashtag = $_GET['hashtag'];
        }

        /** @var Core\Feeds\Suggested\Manager $repo */
        $repo = Di::_()->get('Feeds\Top\Manager');

        $opts = [
            'user_guid' => Core\Session::getLoggedInUserGuid(),
            'rating' => $rating,
            'limit' => $limit,
            'offset' => $offset,
            'type' => $type,
            'all' => $all,
            'algorithm' => $_GET['algorithm'] ?? 'best',
            'period' => $_GET['period'] ?? '12h',
        ];

        if ($hashtag) {
            $opts['hashtag'] = $hashtag;
        }

        if (isset($_GET['hashtags']) && !$all) {
            $opts['hashtags'] = explode(',', $_GET['hashtags']);
        }

        $result = $repo->getList($opts);

        // Remove all unlisted content if it appears
        $result = array_values(array_filter($result, function($entity) {
            return $entity->getAccessId() != 0;
        }));

        return Factory::response([
            'status' => 'success',
            'entities' => Factory::exportable($result),
            'load-next' => $limit + $offset,
        ]);
    }

    public function post($pages)
    {
        return Factory::response([]);
    }

    public function put($pages)
    {
        return Factory::response([]);
    }

    public function delete($pages)
    {
        return Factory::response([]);
    }
}

<?php

namespace Minds\Core\Feeds\Hot;

use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Entities\Entity;
use Minds\Core\EntitiesBuilder;

class Manager
{
    /** @var Repository */
    protected $feedsRepository;
    /** @var Core\EntitiesBuilder */
    protected $entitiesBuilder;
    /** @var \Minds\Core\Hashtags\Entity\Repository */
    private $entityHashtagsRepository;
    /** @var array */
    private $maps;
    /** @var Core\Trending\EntityValidator */
    private $validator;

    private $from;
    private $to;


    public function __construct(
        $repo = null,
        $entityHashtagsRepository = null,
        $validator = null,
        $maps = null,
        $entitiesBuilder = null
    ) {
        $this->feedsRepository = $repo ?: Di::_()->get('Feeds\Suggested\Repository');
        $this->entityHashtagsRepository = $entityHashtagsRepository ?: Di::_()->get('Hashtags\Entity\Repository');
        $this->validator = $validator ?: new Core\Trending\EntityValidator();
        $this->maps = $maps ?: Maps::$maps;
        $this->entitiesBuilder = $entitiesBuilder ?: new EntitiesBuilder;

        $this->from = strtotime('-12 hours') * 1000;
        $this->to = time() * 1000;
    }

    /**
     * @param array $opts
     * @return Entity[]
     * @throws \Exception
     */
    public function getFeed(array $opts = [])
    {
        $opts = array_merge([
            'user_guid' => null,
            'offset' => 0,
            'limit' => 12,
            'rating' => 1,
            'type' => null,
            'all' => false,
        ], $opts);

        $guids = [];
        foreach ($this->feedsRepository->getFeed($opts) as $item) {
            $guids[] = $item['guid'];
        }

        $entities = [];
        if (count($guids) > 0) {
            $entities = $this->entitiesBuilder->get(['guids' => $guids]);
        }

        return $entities;
    }

    public function run(string $type)
    {
        //\Minds\Core\Security\ACL::$ignore = true;
        $scores = [];

        $maps = null;
        switch ($type) {
            case 'all':
                $maps = $this->maps;
                break;
            case 'channels':
                $maps = ['user' => $this->maps['channels']];
                break;
            case 'newsfeed':
                $maps = ['newsfeed' => $this->maps['newsfeed']];
                break;
            case 'images':
                $maps = ['image' => $this->maps['images']];
                break;
            case 'videos':
                $maps = ['video' => $this->maps['videos']];
                break;
            case 'groups':
                $maps = ['group' => $this->maps['groups']];
                break;
            case 'blogs':
                $maps = ['blog' => $this->maps['blogs']];
                break;
            case 'default':
                throw new \Exception("Invalid type. Valid values are: 'newsfeed', 'images', 'videos', 'groups' and 'blogs'");
                break;
        }

        $entities = [];

        foreach ($maps as $key => $map) {
            if (!isset($scores[$key])) {
                $scores[$key] = [];
            }
            
            foreach ($map['aggregates'] as $aggregate) {
                $class = is_string($aggregate) ? new $aggregate : $aggregate;
                $class->setLimit(10000);
                $class->setType($map['type']);
                $class->setSubtype($map['subtype']);
                $class->setFrom($this->from);
                $class->setTo($this->to);

                foreach ($class->get() as $guid => $score) {

                    echo "\n$key:$aggregate: $guid ($score)";
                    //collect the entity
                    $entity = $this->entitiesBuilder->single($guid);

                    if (!$entity->guid) {
                        continue;
                    }

                    if ($entity->container_guid != 0 && $entity->container_guid != $entity->owner_guid && $key == 'newsfeed') {
                        echo " skipping because group post";
                        continue; // skip groups
                    }

                    //validate this entity is ok
                    if (!$this->validator->isValid($entity)) {
                        echo "\n[$entity->getRating()] $key: $guid ($score) invalid";
                        continue;
                    }

                    $entities[$guid] = $entity;
                    if (!isset($scores[$key][$guid])) {
                        $scores[$key][$guid] = 0;
                    }
                    $scores[$key][$guid] += $score;
                }
            }

            //arsort($scores[$key]);

            $sync = time();
            foreach ($scores as $_key => $_scores) {
                echo "\nRunning $_key";
                foreach ($_scores as $guid => $score) {
                    if (! (int) $score || !$guid) {
                        continue;
                    }

                    $entity = $entities[$guid];
                    $age = time() - $entity->time_created;
                    $score = log(abs($score)) + ($age / 45000);

                    $
                    $this->repository->add(

                    echo "\n\t$_key: $guid ($score)";
                }
            }
        }
        //\Minds\Core\Security\ACL::$ignore = false;
    }

}

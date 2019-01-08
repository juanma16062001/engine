<?php

namespace Minds\Core\Feeds\Top;

use Minds\Core;
use Minds\Core\Di\Di;
use Minds\Entities\Entity;
use Minds\Core\EntitiesBuilder;
use Minds\Core\Trending\Aggregates;

class Manager
{
    /** @var Repository */
    protected $repository;
    /** @var Core\EntitiesBuilder */
    protected $entitiesBuilder;
    /** @var array */
    private $maps;
    /** @var Core\Trending\EntityValidator */
    private $validator;

    private $from;
    private $to;

    private $type = 'activity';
    private $subtype = '';

    public function __construct(
        $repo = null,
        $validator = null,
        $maps = null,
        $entitiesBuilder = null
    ) {
        $this->repository = $repo ?: new Repository;
        $this->validator = $validator ?: new Core\Trending\EntityValidator();
        $this->maps = $maps ?: Maps::$maps;
        $this->entitiesBuilder = $entitiesBuilder ?: new EntitiesBuilder;

        $this->from = strtotime('-7 days') * 1000;
        $this->to = time() * 1000;
    }

    /**
     * @param array $opts
     * @return Entity[]
     * @throws \Exception
     */
    public function getList(array $opts = [])
    {
        $opts = array_merge([
            'user_guid' => null,
            'offset' => 0,
            'limit' => 12,
            'rating' => 1,
            'type' => null,
            'all' => false,
        ], $opts);

        if ($opts['algorithm'] == 'hot') {
            $opts['period'] = '12h';
        }

        $guids = [];
        foreach ($this->repository->getList($opts) as $guid) {
            $guids[] = $guid;
        }
        
        $entities = [];
        if (count($guids) > 0) {
            $entities = $this->entitiesBuilder->get(['guids' => $guids]);
        }

        return $entities;
    }

    public function run($opts = [])
    {
        $opts = array_merge([
            'type' => 'activity',
            'subtype' => '',
            'period' => '12h',
        ], $opts);
        $dispatcher = Di::_()->get('EventsDispatcher');

        $maps = [
            '12h' => [
                'period' => '12h',
                'from' => strtotime('-12 hours') * 1000,
            ],
            '24h' => [
                'period' => '24h',
                'from' =>  strtotime('-24 hours') * 1000,
            ],
            '7d' => [
                'period' => '7d',
                'from' => strtotime('-7 days') * 1000,
            ],
            '30d' => [
                'period' => '30d',
                'from' => strtotime('-30 days') * 1000,
            ],
            '1y' => [
                'period' => '1y',
                'from' => strtotime('-1 year') * 1000,
            ],
        ];

        $period = $opts['period'];
        $this->from = $maps[$period]['from'];

        $type = implode(':', [ $this->type, $this->subtype ]);
        if (!$this->subtype) {
            $type = $this->type;
        }
        
        //sync
        foreach ($this->getVotesUp() as $guid => $count) {
            //$entity = $this->entitiesBuilder->single($guid);
            $metric = new MetricsSync();
            $metric
                ->setGuid($guid)
                ->setType($type)
                ->setMetric('votes:up')
                ->setCount($count)
                ->setPeriod($maps[$period]['period'])
                ->setSynced(time());
            try {
                $this->repository->add($metric);
            } catch (\Exception $e) {
//                $entity = $this->entitiesBuilder->single($guid);
//                $dispatcher->trigger('search:index', 'all', [ 'entity' => $entity ]);
            }
            echo "\nUP:$guid: $count";
        }

        //sync
        foreach ($this->getVotesDown() as $guid => $count) {
            $metric = new MetricsSync();
            $metric
                ->setGuid($guid)
                ->setType($type)
                ->setMetric('votes:down')
                ->setCount($count*-1)
                ->setPeriod($maps[$period]['period'])
                ->setSynced(time());
            try {
                $this->repository->add($metric);
            } catch (\Exception $e) {
//                $entity = $this->entitiesBuilder->single($guid);
//                $dispatcher->trigger('search:index', 'all', [ 'entity' => $entity ]);
            } 
            echo "\nDOWN:$guid: " . ($count * -1);
        }
    }


    protected function getVotesUp()
    {
        $aggregates = new Aggregates\Votes;
        $aggregates->setLimit(10000);
        $aggregates->setType($this->type);
        $aggregates->setSubtype($this->subtype);
        $aggregates->setFrom($this->from);
        $aggregates->setTo($this->to);

        return $aggregates->get();
    }

    protected function getVotesDown()
    {
        $aggregates = new Aggregates\DownVotes;
        $aggregates->setLimit(10000);
        $aggregates->setType($this->type);
        $aggregates->setSubtype($this->subtype);
        $aggregates->setFrom($this->from);
        $aggregates->setTo($this->to);

        return $aggregates->get();
    }

}

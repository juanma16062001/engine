<?php

namespace Minds\Core\Feeds\Top;

use Minds\Core\Di\Di;
use Minds\Core\Data\ElasticSearch\Prepared;

class Repository
{
    /** @var ElasticSeach */
    protected $client;

    public function __construct($client = null)
    {
        $this->client = $db ?: Di::_()->get('Database\ElasticSearch'); 
    }

    /**
     * @param array $opts
     * @return array
     * @throws \Exception
     */
    public function getList(array $opts = [])
    {
        $opts = array_merge([
            'user_guid' => null,
            'offset' => 0,
            'limit' => 12,
            'rating' => 1,
            'hashtag' => null,
            'type' => null,
            'all' => false, // if true, it ignores user selected hashtags
            'period' => '12h',
            'algorithm' => 'best',
        ], $opts);

        if (!$opts['type']) {
            throw new \Exception('type must be provided');
        }

        if (!in_array($opts['period'], [ '12h', '24h', '7d', '30d', '1y' ])) {
            throw new \Exception('unsupported period');
        }

        if ($opts['hashtag']) {
            $opts['hashtags'] = [ $opts['hashtag'] ];
        }

        $body = [
            'query' => [
                'function_score' => [
                    'query' => [
                        'bool' => [
                            'must' => [
                                [
                                    'exists' => [
                                        'field' => 'votes:up:' . $opts['period'],
                                    ],
                                ],
                            ],
                            'must_not' => [
                                'term' => [
                                    'mature' => true,
                                ],
                            ],
                        ],
                    ],
                    'script_score' => [
                        'script' => [
                            'source' => "Math.log(doc['votes:up:12h'].value - doc['votes:down:12h'].value) - ((new Date().getTime() - doc['@timestamp'].value)/45000000)"
                        ]
                    ]
                ]
            ],
        ];

        if ($opts['hashtags']) {
            $should = [];
            foreach ($opts['hashtags'] as $hashtag) {
                $should[] = [
                    'match_phrase' => [
                        'tags' => $hashtag,
                    ],
                ];
            }
            $body['query']['function_score']['query']['bool']['must'][] = [
                'bool' => [
                    'should' => $should,
                ],
            ];
        }

        switch ($opts['algorithm']) {
           case "best":
                $body['query']['function_score']['script_score']['script']['source'] = "
                    def up = doc['votes:up:{$opts['period']}'].value;
                    def down = doc['votes:down:{$opts['period']}'].value > 0 ? doc['votes:down:{$opts['period']}'].value : 1;
                    def age = (new Date().getTime() - doc['@timestamp'].value);
                    def _log = Math.round(Math.log(up/down) * 1000) / 1000;

                    def valid = (doc['votes:up:{$opts['period']}:synced'].value + 43200) > (new Date().getTime()/1000);

                    if (!valid) {
                        return -1000;
                    }

                    return _log - (age/45000000)
                ";
            break;
            case "controversial":
                $body['query']['function_score']['script_score']['script']['source'] = "
                    def up = doc['votes:up:{$opts['period']}'].value;
                    def down = doc['votes:down:{$opts['period']}'].value;

                    if (down <= 0 || up <= 0) {
                        return -1;
                    }
            
                    def total = up + down;
                    def balance = up > down ? (down / up) : (up / down);
                    def age = (new Date().getTime() - doc['@timestamp'].value);
                    return (total * balance) - (age/45000000)
                 ";
                 $body['min_score'] = 0;
            break;
            case "hot":
                $body['query']['function_score']['script_score']['script']['source'] = "
                    def up = doc['votes:up:{$opts['period']}'].value ?: 0;
                    def down = doc['votes:down:{$opts['period']}'].value ?: 0;
                    def age = (new Date().getTime() - doc['@timestamp'].value);
                    def valid = (doc['votes:up:{$opts['period']}:synced'].value + 43200) > (new Date().getTime()/1000);
                    def _log = Math.round(Math.log(up - down) * 1000) / 1000;

                    if (!valid) {
                        return -100;
                    }
        
                    return _log - (age/45000)
                ";
             break;
             case "latest":
                 $body['query']['function_score']['script_score']['script']['source'] = "
                     return Math.log(doc['@timestamp'].value);
                 ";
            break;
        }
        //$body['min_score'] = 0;
        
        $query = [
            'index' => 'minds_badger',
            'type' => $opts['type'],
            'body' => $body,
            'size' => $opts['limit'],
            'from' => $opts['offset'],
        ];

        $prepared = new Prepared\Search();
        $prepared->query($query);

        $response = $this->client->request($prepared);

        foreach ($response['hits']['hits'] as $doc) {
            yield $doc['_source']['guid'];
        }

    }

    public function add(MetricsSync $metric)
    {
        $body = [];

        $key = $metric->getMetric() . ':' . $metric->getPeriod();
        $body[$key] = $metric->getCount();

        $body[$key.':synced'] = $metric->getSynced();
        
        $query = [
            'index' => 'minds_badger',
            'type' => $metric->getType(),
            'id' => $metric->getGuid(),
            'body' => [ 'doc' => $body ],
        ];
        
        $prepared = new Prepared\Update();
        $prepared->query($query);

        $this->client->request($prepared);
    }

    public function removeAll($type)
    {
    }
}

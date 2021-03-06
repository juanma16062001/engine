<?php
/**
 * Votes aggregates
 */
namespace Minds\Core\Trending\Aggregates;

use Minds\Core\Data\ElasticSearch;

class Votes extends Aggregate
{

    protected $multiplier = 1;

    private $page = -1;
    private $partitions = 20;

    public function fetch()
    {
        $filter = [ 
            'term' => [
                'action' => 'vote:up'
            ]
        ];

        $must = [
            [
                'range' => [
                '@timestamp' => [
                    'gte' => $this->from,
                    'lte' => $this->to
                    ]
                ]
            ]
        ];
        
        if ($this->type && $this->type != 'group') {
            $must[]['match'] = [
                'entity_type' => $this->type
            ];
        }

        if ($this->subtype) {
            $must[]['match'] = [
                'entity_subtype' => $this->subtype
            ];
        }
        
        $field = 'entity_guid';
        //$cardinality_field = 'user_phone_number_hash';
        $cardinality_field = 'ip_hash';

        if ($this->type == 'group') {
            $field = 'entity_container_guid';
            $this->multiplier = 4;
            $must[]['range'] = [
                'entity_access_id' => [
                  'gte' => 3, //would be group
                  'lt' => null,
                ]
            ];
        }

        //$must[]['match'] = [
        //    'rating' => $this->rating
        //];

        $query = [
            'index' => 'minds-metrics-*',
            'type' => 'action',
            'size' => 0, //we want just the aggregates
            'body' => [
                'query' => [
                    'bool' => [
                        'filter' => $filter,
                        'must' => $must
                    ]
                ],
                'aggs' => [
                    'entities' => [
                        'terms' => [ 
                            'field' => "$field.keyword",
                            'size' => $this->limit,
                            'include' => [
                                'partition' => $this->page,
                                'num_partitions' => $this->partitions,
                            ],
                            // 'order' => [ 'uniques' => 'DESC' ],
                        ],
                        'aggs' => [
                            'uniques' => [
                                'cardinality' => [
                                    'field' => "$cardinality_field.keyword",
                                    //'precision_threshold' => 40000
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $prepared = new ElasticSearch\Prepared\Search();
        $prepared->query($query);
    
        return $this->client->request($prepared);
    }

    public function get()
    {
        while ($this->page++ < $this->partitions - 1) {
            $result = $this->fetch();
            foreach ($result['aggregations']['entities']['buckets'] as $entity) {
                yield $entity['key'] => ($entity['uniques']['value'] ?: 1) * $this->multiplier;
            }
        }
    }

}

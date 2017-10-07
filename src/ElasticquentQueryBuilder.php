<?php

namespace Elasticquent;

use InvalidArgumentException;

class ElasticquentQueryBuilder
{
    /**
     * The base query builder instance.
     *
     * @var \Illuminate\Database\Query\Builder
     */
    protected $query = [];

    /**
     * All of the available clause operators.
     *
     * @var array
     */
    protected $operators = [
        '=' => 'term',
        '<' => 'lt',
        '>' => 'gt',
        '<=' => 'lte',
        '>=' => 'gte',
        '<>' => 'term',
        '!=' => 'term'
    ];

    /**
     * All of the available clause elasticsearch operators.
     *
     * @var array
     */
    protected $es_operators = [
        'match_phrase', 'multi_match', 'prefix', 'wildcard', 'regexp', 'fuzzy'
    ];

    /**
     * Constructor.
     *
     * @param array $body
     */
    public function __construct(array $body = [])
    {
        $this->query['body'] = $body;
    }

    /**
     * Return query.
     *
     * @return array
     */
    protected function getQuery()
    {
        return $this->query;
    }

    /**
     * Build query.
     *
     * @return array
     */
    public function build()
    {
        return $this->getQuery();
    }

    /**
     * Build Merge Query.
     *
     * @return array
     */
    public function buildMergeQuery()
    {
        return $this->build();

        if (array_key_exists('query', $query['body'])) {
            $bool_filter = $query['body']['query']['filtered']['filter']['bool'];

            if (isset($query['body']['query']['filtered']['query'])) {
                $bool_query = $query['body']['query']['filtered']['query'];
            }
            
            if (array_key_exists('must', $bool_filter) && array_key_exists('must_not', $bool_filter) && array_key_exists('should', $bool_filter)) {
                $params = [];
                $params['body']['query']['filtered']['filter']['bool']['must'][] = [
                    'bool' => [
                        'should' => array_merge($bool_filter['must'], $bool_filter['should'])
                    ]
                ];

                $params['body']['query']['filtered']['filter']['bool']['must'][] = [
                    'bool' => [
                        'must_not' => $bool_filter['must_not']
                    ]
                ];

                $query['body']['query']['filtered']['filter'] = $params['body']['query']['filtered']['filter'];
                unset($params);

            } elseif (array_key_exists('must', $bool_filter) && array_key_exists('should', $bool_filter)) {
                $params = [];
                $params['body']['query']['filtered']['filter']['bool']['must'][] = [
                    'bool' => [
                        'should' => array_merge($bool_filter['must'], $bool_filter['should'])
                    ]
                ];
                
                $query['body']['query']['filtered']['filter'] = $params['body']['query']['filtered']['filter'];
                unset($params);

            } elseif (array_key_exists('must_not', $bool_filter) && array_key_exists('should', $bool_filter)) {
                $params = [];
                $params['body']['query']['filtered']['filter']['bool']['should'][] = [
                    'bool' => [
                        'must_not' => $bool_filter['must_not']
                    ]
                ];

                $params['body']['query']['filtered']['filter']['bool']['should'][] = [
                    'bool' => [
                        'should' => $bool_filter['should']
                    ]
                ];
                
                $query['body']['query']['filtered']['filter'] = $params['body']['query']['filtered']['filter'];
                unset($params);

            }

            if (isset($bool_query)) {
                $query['body']['query']['filtered']['query'] = $bool_query;
                unset($bool_query);
            }
            unset($bool_filter);
        }

        return $query;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->toJson();
    }

    /**
     * @param int $options
     * @return string
     */
    public function toJson($options = 0)
    {
        return json_encode($this->getQuery(), $options);
    }

    /**
     * Add a basic match clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  mixed   $value
     * @param  string  $boost
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function fuzzyQuery($column, $value = null, $fuzziness = 'AUTO', $operator = 'and', $boolean = 'must')
    {
        $query = [
            'match' => [
                $column => [
                    'query' => $value,
                    'fuzziness' => $fuzziness,
                    'operator' => $operator
                ]
            ]
        ];
        $this->mergeQuery($query, $boolean);
        
        return $this;
    }

    /**
     * Add a basic match clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  mixed   $value
     * @param  string  $boost
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function matchQuery($column, $value = null, $boost = '100%', $boolean = 'must')
    {
        if ($boost) {
            $query = [
                'match' => [
                    $column => [
                        'query' => $value,
                        'minimum_should_match' => $boost
                    ]
                ]
            ];
        } else {
            $query = [
                'match' => [
                    $column => [
                        'query' => $value
                    ]
                ]
            ];
        }
        $this->mergeQuery($query, $boolean);
        
        return $this;
    }

    /**
     * Add a basic match clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  mixed   $value
     * @param  string  $boost
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function match($column, $value = null, $boost = '100%', $boolean = 'must')
    {
        $query = [
            'query' => [
                'match' => [
                    $column => [
                        'query' => $value,
                        'minimum_should_match' => $boost
                    ]
                ]
            ]
        ];
        $this->merge($query, $boolean);
        
        return $this;
    }

    /**
     * Add a raw where clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function whereRaw($column, $operator, $value, $boolean = 'must')
    {
        if (in_array($operator, $this->es_operators)) {

            if (is_array($value) && count($value) > 1) {
                $params = [
                    'value' => $value[0],
                    'boost' => $value[1]
                ];

                if (count($value) === 3) {
                    $params = array_merge_recursive($value[2], $params);
                }

                $query = [
                    $operator => [
                        $column => $params
                    ]
                ];
            } else {

                if (is_array($column)) {
                    $query = [
                        $operator => [
                            'query' => $value,
                            'fields' => $column
                        ]
                    ];
                } else {
                    $query = [
                        $operator => [
                            $column => $value
                        ]
                    ];
                }
            }
            $this->merge($query, $boolean);

            return $this;
        } else {
            throw new InvalidArgumentException('Illegal elasticsearch operator and value combination.');
        }
    }

    /**
     * Add a basic where clause to the query.
     *
     * @param  string|array|\Closure  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @param  string  $boolean
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function where($column, $operator = null, $value = null, $boost = null, $boolean = 'must')
    {
        if (func_num_args() == 2) {
            list($value, $operator) = [$operator, $this->operators['=']];
        } elseif ($this->invalidOperatorAndValue($operator, $value)) {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        } else {
            if (! in_array(strtolower($operator), array_keys($this->operators), true)) {
                list($value, $operator) = [$operator, $this->operators['=']];
            } else {
                $operator = $this->operators[$operator];
            }
            if ($operator !== 'term') {
                $value = [
                    $operator => $value
                ];
                $operator = 'range';
            }
        }

        if (is_null($boost)) {
            $query = [
                $operator => [
                    $column => $value
                ]
            ];
        } else {
            $query = [
                $operator => [
                    $column => array_merge_recursive(['boost' => $boost], $value)
                ]
            ];
        }
        $this->merge($query, $boolean);

        return $this;
    }

    /**
     * Add an "or where" clause to the query.
     *
     * @param  string  $column
     * @param  string  $operator
     * @param  mixed   $value
     * @return \Illuminate\Database\Eloquent\Builder|static
     */
    public function orWhere($column, $operator = null, $value = null, $boost = null, $boolean = 'should')
    {
        return $this->where($column, $operator, $value, $boost, $boolean);
    }

    /**
     * Add a "where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereIn($column, array $values, $boolean = 'must')
    {
        if ($values instanceof Arrayable) {
            $values = $values->toArray();
        }

        $query = [
            'terms' => [
                $column => $values
            ]
        ];
        $this->merge($query, $boolean);

        return $this;
    }

    /**
     * Add a "or where in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function orWhereIn($column, array $values, $boolean = 'should')
    {
        return $this->whereIn($column, $values, $boolean);
    }

    /**
     * Add a "where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereNotIn($column, $values, $boolean = 'must_not')
    {
        return $this->whereIn($column, $values, $boolean);
    }

    /**
     * Add a "or where not in" clause to the query.
     *
     * @param  string  $column
     * @param  mixed   $values
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orWhereNotIn($column, $values, $boolean = 'should')
    {
        return $this->whereIn($column, $values, $boolean);
    }

    /**
     * Add a "where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function whereNull($column, $boolean = 'must', $not = false)
    {
        $operator = $not ? 'exists' : 'missing';

        $query = [
            $operator => [
                'field' => $column
            ]
        ];
        $this->merge($query, $boolean);

        return $this;
    }

    /**
     * Add a "or where null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @param  bool    $not
     * @return $this
     */
    public function orWhereNull($column, $boolean = 'should', $not = false)
    {
        return $this->whereNull($column, $boolean);
    }

    /**
     * Add a "where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereNotNull($column, $boolean = 'must')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a "or where not null" clause to the query.
     *
     * @param  string  $column
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orWhereNotNull($column, $boolean = 'should')
    {
        return $this->whereNull($column, $boolean, true);
    }

    /**
     * Add a where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @param  bool  $not
     * @return $this
     */
    public function whereBetween($column, array $values, $boost = null, $boolean = 'must')
    {
        if (count($values) === 2) {
            if ($boolean === 'must_not') {
                $value = [
                    'gte' => min($values),
                    'lte' => max($values)
                ];
            } else {
                $value = [
                    'gt' => min($values),
                    'lt' => max($values)
                ];
            }

            if (is_null($boost)) {
                $query = [
                    'range' => [
                        $column => $value
                    ]
                ];
            } else {
                $query = [
                    'range' => [
                        $column => array_merge_recursive(['boost' => $boost], $value)
                    ]
                ];
            }

            $this->merge($query, $boolean);
        } else {
            throw new InvalidArgumentException('Illegal operator and value combination.');
        }

        return $this;
    }

    /**
     * Add a or where between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function orWhereBetween($column, array $values, $boost = null, $boolean = 'should')
    {
        return $this->whereBetween($column, $values, $boost, $boolean);
    }

    /**
     * Add a where not between statement to the query.
     *
     * @param  string  $column
     * @param  array   $values
     * @param  string  $boolean
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function whereNotBetween($column, array $values, $boost = null, $boolean = 'must_not')
    {
        return $this->whereBetween($column, $values, $boost, $boolean);
    }

    /**
     * Add an "order by script" clause to the query.
     *
     * @param  string  $script
     * @return $this
     */
    public function orderByScript($script)
    {
        $this->query['body']['sort'][] = [
            '_script' => $script
        ];

        return $this;
    }

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'asc')
    {
        $this->query['body']['sort'][] = [
            $column => strtolower($direction) == 'asc' ? 'asc' : 'desc',
        ];

        return $this;
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function latest($column = 'created_at')
    {
        return $this->orderBy($column, 'desc');
    }

    /**
     * Add an "order by" clause for a timestamp to the query.
     *
     * @param  string  $column
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function oldest($column = 'created_at')
    {
        return $this->orderBy($column, 'asc');
    }

    /**
     * Set the "offset" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function offset($value)
    {
        $this->query['from'] = $value;
        return $this;
    }

    /**
     * Alias to set the "offset" value of the query.
     *
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function skip($value)
    {
        return $this->offset($value);
    }

    /**
     * Set the "limit" value of the query.
     *
     * @param  int  $value
     * @return $this
     */
    public function limit($value)
    {
        $this->query['size'] = $value;
        return $this;
    }

    /**
     * Alias to set the "limit" value of the query.
     *
     * @param  int  $value
     * @return \Illuminate\Database\Query\Builder|static
     */
    public function take($value)
    {
        return $this->limit($value);
    }

    /**
     * Set the columns to be selected.
     *
     * @param  array|mixed  $columns
     * @return $this
     */
    public function select($columns = [])
    {
        if (!empty($columns)) {
            $this->query['body']['_source']['include'] = is_array($columns) ? $columns : func_get_args();
        }
        return $this;
    }

    protected function merge(array $query, $mode = 'must')
    {
        if (!array_key_exists('query', $this->query['body'])) {
            $this->query['body']['query']['filtered']['filter']['bool'][$mode] = [];
        }
        $this->query['body']['query']['filtered']['filter']['bool'][$mode][] = $query;
        return $this;
    }

    protected function mergeQuery(array $query, $mode = 'must')
    {
        if (!array_key_exists('query', $this->query['body'])) {
            $this->query['body']['query']['filtered']['query']['bool'][$mode] = [];
        }
        $this->query['body']['query']['filtered']['query']['bool'][$mode][] = $query;
        return $this;
    }

    public function aggregation($field, $size = 0)
    {
        $this->query['body']['aggs'][$field] = [
            'terms' => [
                'field' => $field,
                'size' => $size
            ]
        ];
        return $this;
    }

    /**
     * Enable an "explanation" clause to the query.
     *
     * @param  bool  $explain
     * @return $this
     */
    public function explanation($explain = true)
    {
        $this->query['body']['explain'] = $explain;
        return $this;
    }

    /**
     * Enable an "trackScores" clause to the query.
     *
     * @param  bool  $track_scores
     * @return $this
     */
    public function trackScores($track_scores = true)
    {
        $this->query['body']['track_scores'] = $track_scores;
        return $this;
    }

    /**
     * Determine if the given operator and value combination is legal.
     *
     * @param  string  $operator
     * @param  mixed  $value
     * @return bool
     */
    protected function invalidOperatorAndValue($operator, $value)
    {
        $isOperator = in_array($operator, array_keys($this->operators));

        return is_null($value) && $isOperator && ! in_array($operator, ['=', '<>', '!=']);
    }
}

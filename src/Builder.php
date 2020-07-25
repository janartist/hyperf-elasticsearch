<?php

declare(strict_types=1);

namespace Chungou\Elasticsearch;

use Chungou\Core\Log\Log;
use Chungou\Core\Tools\Arr;
use Hyperf\Utils\Collection;

class Builder
{
    /**
     * @var \Elasticsearch\Client
     */
    protected $client;
    /**
     * The base query
     *
     * @var []
     */
    protected $query;
    /**
     * @var array
     */
    protected $highlight = [];

    protected $sql;
    /**
     * @var []
     */
    protected $sort = [];
    /**
     * The model being queried.
     *
     * @var Model
     */
    protected $model;
    /**
     * @var int
     */
    protected $take = 50;
    /**
     * @var array
     */
    protected $operate = ['=', '>', '<', '>=', '<=', '!=', '<>', 'in', 'not in', 'like', 'regex', 'prefix'];

    /**
     * 分页
     * @param int $page
     * @param int $size
     * @return Paginator
     */
    public function page(int $size = 50, int $page = 1) : Paginator
    {
        $from = (($page - 1) * $size) + 1;
        $this->sql = [
            'from' => $from,
            'size' => $size,
            'index' => $this->model->getIndex(),
            'body'  => [
                'query' => $this->query,
                'highlight' => $this->highlight,
                'sort' => $this->sort
            ]
        ];
        if (empty($this->query)) {
            $this->sql = [
                'from' => $from,
                'size' => $size,
                'index' => $this->model->getIndex(),
                'body'  => [
                    'query' => [
                        'match_all' => new \stdClass()
                    ]
                ]
            ];
        }
        $result = $this->run('search', $this->sql);
        $original = $result['hits']['hits'] ?? [];

        $collection = collect($original)->map(function ($value) {
            $attributes =  $value['_source'] ?? [];
            $this->model->setAttributes($attributes);
            $this->model->setOriginal($value);
            return $this->model;
        });
        return make(Paginator::class, ['items' => $collection, 'perPage' => $size, 'currentPage' => $page]);
    }
    public function get($size = 50): Collection
    {
        $this->sql = [
            'from' => 1,
            'size' => $size,
            'index' => $this->model->getIndex(),
            'body'  => [
                'query' => $this->query,
                'highlight' => $this->highlight,
                'sort' => $this->sort
            ]
        ];
        if (empty($this->query)) {
            $this->sql = [
                'from' => 1,
                'size'   => $size,
                'index' => $this->model->getIndex(),
                'body'  => [
                    'query' => [
                        'match_all' => new \stdClass()
                    ]
                ]
            ];
        }
        $result = $this->run('search', $this->sql);
        $original = $result['hits']['hits'] ?? [];

        $collection = collect($original)->map(function ($value) {
            $attributes =  $value['_source'] ?? [];
            $this->model->setAttributes($attributes);
            $this->model->setOriginal($value);
            return $this->model;
        });

        return $collection;
    }

    public function first() : Model
    {
        return $this->take(1)->get()->first();
    }
    public function take(int $take): Builder
    {
        $this->take = $take;
        return $this;
    }
    /**
     * 查找单条
     * @param $id
     * @return array
     */
    public function find($id) : Model
    {
        $this->sql = [
            'index' => $this->model->getIndex(),
            'id'    => $id
        ];
        $result = $this->run('get', $this->sql);

        $this->model->setAttributes($result['_source'] ?? []);
        $this->model->setOriginal($result);
        return $this->model;
    }
    /**
     * insert
     *
     * @param array $values
     *
     * @return Collection
     */
    public function insert(array $values): Collection
    {
        $body = [];
        foreach ($values as $value) {
            $body['body'][] = [
                'index' => [
                    '_index' => $this->model->getIndex(),
                ]
            ];
            $body['body'][] = $value;
        }
        $this->sql = $body;
        $result = $this->run('bulk', $this->sql);
        $collection = collect($result['items'])->map(function ($value, $key) use ($values) {
            $this->model->setAttributes(Arr::merge($values[$key] ?? [], ['_id' => $values['index']['_id'] ?? '']));
            $this->model->setOriginal($value);
            return $this->model;
        });

        return $collection;
    }
    /**
     * insert
     *
     * @param array $values
     *
     * @return Collection
     */
    public function create(array $value): Model
    {
        $body = Arr::except($value, ['id', 'routing', 'timestamp']);
        $except = Arr::only($value, ['id', 'routing', 'timestamp']);
        $this->sql = Arr::merge($except, [
            'index' => $this->model->getIndex(),
            'body' => $body
        ]);
        $result = $this->run('index', $this->sql);
        if (!empty($data['result']) && $data['result'] == 'created') {
            $this->model->setOriginal($result);
            $this->model->setAttributes(Arr::merge($body, ['_id' => $result['_id'] ?? '']));
        }
        return $this->model;
    }
    /**
     * update
     *
     * @param string $id
     * @param array $value
     *
     * @return bool
     */
    public function update(array $value, $id)
    {
        $result = $this->run('update', [
            [
                'index' => $this->model->getIndex(),
                'id' => $id,
                'body' => [
                    'doc' => $value,
                ],
            ]
        ]);
        if (!empty($result['result']) && ($result['result'] == 'updated' || $result['result'] == 'noop')) {
            $this->model->setOriginal($result);
            $this->model->setAttributes(['_id' => $result['_id'] ?? '']);
        }
        return $this->model;
    }
    /**
     * delete
     *
     * @param string $id
     *
     * @return bool
     */
    public function delete($id):bool
    {
        $result = $this->run('delete', [
            [
                'index' => $this->model->getIndex(),
                'id' => $id,
            ]
        ]);
        d($result);
        if (!empty($result['result']) && $result['result'] == 'deleted') {
            return true;
        }
        return false;
    }
    public function updateMapping(array $mappings)
    {
        $mappings = collect($mappings)->map(function ($value, $key) {
            $valued = [];
            if (is_string($value)) {
                $valued['type'] = $value;
            }
            if (is_array($value)) {
                $valued = $value;
            }
            return $valued;
        })->toArray();
        $this->sql = [
            'index' => $this->model->getIndex(),
            'body' => [
                'properties' => $mappings
            ]
        ];
        $result = $this->run('indices.putMapping', $this->sql);

        return $result;
    }
    public function updateSetting(array $settings)
    {
        $this->sql = [
            'index' => $this->model->getIndex(),
            'body' => [
                'settings' => $settings
            ]
        ];
        $result = $this->run('putSettings', $this->sql);

        return $result;
    }
    public function createIndex(array $mappings = [], array $settings = [])
    {
        $mappings = Arr::merge(
            collect($this->model->getCasts())->map(function ($value, $key) {
                $valued = '';
                if (is_string($value)) {
                    $valued['type'] = $value;
                }
                if (is_array($value)) {
                    $valued = $value;
                }
                return $valued;
            })->toArray(),
            collect($mappings)->map(function ($value, $key) {
                $valued = '';
                if (is_string($value)) {
                    $valued['type'] = $value;
                }
                if (is_array($value)) {
                    $valued = $value;
                }
                return $valued;
            })->toArray()
        );
        $this->sql = [
            'index' => $this->model->getIndex(),
            'id' => '1',
            'body' => compact('mappings') + compact('settings')
        ];
        // Create the index
        $result = $this->run('create', $this->sql);

        return $result;
    }

    public function matchPhrase(string $field, $value)
    {
        $this->query['match_phrase'][$field] = $value;
        return $this;
    }
    public function match(string $field, $value)
    {
        $this->query['match'][$field] = $value;
        return $this;
    }
    /**
     * @param string $field
     * @param string $operate
     * @param null $value
     */
    public function where(string $field, string $operate, $value = null): Builder
    {
        if (is_null($value)) {
            $value = $operate;
            $operate = '=';
        }
        if (in_array($operate, $this->operate)) {
            $this->parseQuery($field, $operate, $value);
        }
        return $this;
    }
    public function whereIn(string $field, $value): Builder
    {
        return $this->where($field, 'in', $value);
    }
    public function whereNotIn(string $field, $value): Builder
    {
        return $this->where($field, 'not in', $value);
    }
    public function whereLike(string $field, $value): Builder
    {
        return $this->where($field, 'like', $value);
    }
    /**
     * parseWhere
     *
     * @param string $field
     * @param string $operate
     * @param        $value
     *
     * @return array
     */
    protected function parseQuery(string $field, string $operate, $value): Builder
    {
        switch ($operate) {
            case '=':
                $type = 'must';
                $result = ['match' => [$field => $value]];
                break;
            case '>':
                $type = 'must';
                $result = ['range' => [$field => ['gt' => $value]]];
                break;
            case '<':
                $type = 'must';
                $result = ['range' => [$field => ['lt' => $value]]];
                break;
            case '>=':
                $type = 'must';
                $result = ['range' => [$field => ['gte' => $value]]];
                break;
            case '<=':
                $type = 'must';
                $result = ['range' => [$field => ['lte' => $value]]];
                break;
            case '<>':
            case '!=':
                $type = 'must_not';
                $result = ['match' => [$field => $value]];
                break;
            case 'in':
                $type = 'must';
                $result = ['terms' => [$field => $value]];
                break;
            case 'not in':
                $type = 'must_not';
                $result = ['terms' => [$field => $value]];
                break;
            case 'like':
                $type = 'must';
                $result = ['match' => [$field => $value]];
                break;
            case 'regex':
                $type = 'must';
                $result = ['regexp' => [$field => $value]];
                break;
            case 'prefix':
                $type = 'must';
                $result = ['prefix' => [$field => $value]];
                break;
        }
        $this->query['bool'][$type][] = $result;
        return $this;
    }
    protected function run($method, ... $parameters)
    {
        $client = $this->client;
        $sql = $this->sql;
        if (strpos($method, '.')) {
            $methods = explode('.', $method);
            $method = $methods[1];
            $client = $this->client->{$methods[0]}();
        }
        Log::get('elasticsearch', 'sql')->debug('Elasticsearch run', compact('method', 'parameters', 'sql'));
        return call([$client, $method], ... $parameters);
    }
    public function highlight($fields)
    {
        $fields = (array) $fields;

        $fields = Collection::make($fields)
            ->map(function ($item) {
                return [
                    $item => new \stdClass()
                ];
            })->toArray();
        $this->highlight = compact('fields');
        return $this;
    }
    /**
     * orderBy
     *
     * @param string $field
     * @param string $sort
     *
     * @return Builder
     */
    public function orderBy(string $field, bool $desc = false): Builder
    {
        $sort = $desc ? 'desc' : 'asc';
        if (!is_array($this->sort)) {
            $this->sort = [];
        }
        $this->sort[] = [$field => ['order' => $sort]];

        return $this;
    }
    /**
     * Set a model instance for the model being queried.
     *
     * @param Model $model
     * @return $this
     */
    public function setModel(Model $model)
    {
        $this->model = $model;
        $this->client = $model->getClient();
        $this->highlight = new \stdClass();
        $this->sort = new \stdClass();
        return $this;
    }
}

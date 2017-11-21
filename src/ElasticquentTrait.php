<?php

namespace Elasticquent;

use Carbon\Carbon, ReflectionMethod;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

use Elasticquent\ElasticquentQueryBuilder;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Elasticsearch\Common\Exceptions\Missing404Exception;
use Elasticsearch\Common\Exceptions\Conflict409Exception;

use Elasticquent\ElasticquentPaginator;
use /** @noinspection PhpUndefinedNamespaceInspection */
    Illuminate\Pagination\LengthAwarePaginator as Paginator;

/**
 * Elasticquent Trait
 *
 * Functionality extensions for Elequent that
 * makes working with Elasticsearch easier.
 */
trait ElasticquentTrait
{
    use ElasticquentClientTrait;

    /**
     * Uses Timestamps In Index
     *
     * @var bool
     */
    protected $usesTimestampsInIndex = true;

    /**
     * Is ES Document
     *
     * Set to true when our model is
     * populated by a
     *
     * @var bool
     */
    protected $isDocument = false;

    /**
     * Document Score
     *
     * Hit score when using data
     * from Elasticsearch results.
     *
     * @var null|int
     */
    protected $documentScore = null;

    /**
     * Document Version
     *
     * Elasticsearch document version.
     *
     * @var null|int
     */
    protected $documentVersion = null;

    public static function bootElasticquentTrait()
    {
        static::saved(function(Model $model)
        {
            $model->autoIndex();
        });

        static::updated(function(Model $model)
        {
            $model->autoIndex();
        });

        static::created(function(Model $model)
        {
            $model->autoIndex();
        });

        static::deleted(function(Model $model)
        {
            $model->autoIndex();
        });
    }

    /**
     * New Collection
     *
     * @param array $models
     * @return ElasticquentCollection
     */
    public function newCollection(array $models = array())
    {
        return new ElasticquentCollection($models);
    }

    /**
     * Get Type Name
     *
     * @return string
     */
    public function getTypeName()
    {
        return $this->getTable();
    }

    /**
     * Uses Timestamps In Index.
     */
    public function usesTimestampsInIndex()
    {
        return $this->usesTimestampsInIndex;
    }

    /**
     * Use Timestamps In Index.
     */
    public function useTimestampsInIndex($shouldUse = true)
    {
        $this->usesTimestampsInIndex = $shouldUse;
    }

    /**
     * Don't Use Timestamps In Index.
     *
     * @deprecated
     */
    public function dontUseTimestampsInIndex()
    {
        $this->useTimestampsInIndex(false);
    }

    /**
     * Get Mapping Properties
     *
     * @return array
     */
    public function getMappingProperties()
    {
        return $this->mappingProperties;
    }

    /**
     * Set Mapping Properties
     *
     * @param    array $mapping
     * @internal param array $mapping
     */
    public function setMappingProperties(array $mapping = null)
    {
        $this->mappingProperties = $mapping;
    }

    /**
     * Get Index Settings
     *
     * @return array
     */
    public function getIndexSettings()
    {
        return $this->indexSettings;
    }

    /**
     * Is Elasticsearch Document
     *
     * Is the data in this module sourced
     * from an Elasticsearch document source?
     *
     * @return bool
     */
    public function isDocument()
    {
        return $this->isDocument;
    }

    /**
     * Get Document Score
     *
     * @return null|float
     */
    public function documentScore()
    {
        return $this->documentScore;
    }

    /**
     * Document Version
     *
     * @return null|int
     */
    public function documentVersion()
    {
        return $this->documentVersion;
    }

    /**
     * Get Index Document Data
     *
     * Get the data that Elasticsearch will
     * index for this particular document.
     *
     * @return array
     */
    public function getIndexDocumentData()
    {
        return $this->toArray();
    }

    /**
     * Index Documents
     *
     * Index all documents in an Eloquent model.
     *
     * @return array
     */
    public static function addAllToIndex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->addToIndex();
    }

    /**
     * Re-Index All Content
     *
     * @return array
     */
    public static function reindex()
    {
        $instance = new static;

        $all = $instance->newQuery()->get(array('*'));

        return $all->reindex();
    }

    /**
     * Determine if a model has a elasticsearch global scope.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return bool
     */
    public static function hasESGlobalScope()
    {
        $instance = new static;
        if (method_exists(get_class($instance->model), 'addESGlobalScope')) {
            return ! is_null($instance->addESGlobalScope());
        }
        return false;
    }

    /**
     * Get a elasticsearch global scope registered with the model.
     *
     * @param  \Illuminate\Database\Eloquent\Scope|string  $scope
     * @return \Illuminate\Database\Eloquent\Scope|\Closure|null
     */
    public static function getESGlobalScope()
    {
        $instance = new static;

        if ($instance->hasESGlobalScope()) {

            $es_scope = $instance->addESGlobalScope();

            if ($es_scope instanceof ElasticquentQueryBuilder) {
                $es_scope = $es_scope->buildMergeQuery();
            }

            return $es_scope;
        }

        return [];
    }

    /**
     * Search By Query
     *
     * Search with a query array
     *
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function searchByQuery($query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null, $sort = null, $es_global_scope = true)
    {
        $instance = new static;

        $result = $instance->scopeSearchResult(null, $query, $aggregations, $sourceFields, $limit, $offset, $sort);

        return static::hydrateElasticsearchResult($result);
    }

    /**
     * Search By Query return Elastisearch Search Results
     *
     * Search with a query array
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function scopeSearchResult($q, $query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null, $sort = null)
    {
        $instance = new static;

        $params = $instance->getBasicEsParams(true, true, $limit, $offset);

        if (!empty($sourceFields)) {
            $params['body']['_source']['include'] = $sourceFields;
        }

        if (!empty($query)) {
            $params['body']['query'] = $query;
        }

        if (!empty($aggregations)) {
            $params['body']['aggs'] = $aggregations;
        }

        if (!empty($sort)) {
            $params['body']['sort'] = $sort;
        }

        return $instance->getElasticSearchClient()->search($params);
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param  mixed  $results
     * @return Collection
     */

    public static function scopeResultMapping($q, $results)
    {
        $instance = new static;

        if (count($results['hits']['total']) === 0) {
            return Collection::make();
        }

        $keys = collect($results['hits']['hits'])->pluck('_id')->values()->all();
        $models = $q->whereIn($instance->getKeyName(), $keys)->get()->keyBy($instance->getKeyName());

        return collect($results['hits']['hits'])->map(function ($hit) use ($models) {
            if (isset($models[$hit['_id']])){
                $model = $models[$hit['_id']];
                $model->es_score = $hit['_score'];
                return $model;
            } else {
                null;
            }
        })->filter();
    }


    /**
     * Search By Model Query return Model Collection
     *
     * Search with a query array
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function scopeSearchByModelQuery($q, $query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null, $sort = null)
    {
        $instance = new static;

        $results = $instance->searchResult($query, $aggregations, $sourceFields, $limit, $offset, $sort);

        return $instance->scopeResultMapping($q, $results);
    }

    /**
     * Search By Model Query Builder return Model Collection
     *
     * Search with a query array
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function scopeSearchByModelQueryBuilder($q, $params = [], $columns = [], $es_global_scope = true)
    {
        $instance = new static;

        $results = $instance->complexSearchResult($params, $columns, $es_global_scope);

        return $instance->scopeResultMapping($q, $results);
    }

    /**
     * Search By Model Query return Model Collection with Pagination
     *
     * Search with a query array
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function scopePaginateByModelQuery($q, $query = null, $aggregations = null, $sourceFields = null, $limit = null, $offset = null, $sort = null)
    {
        $instance = new static;

        $results = $instance->searchResult($query, $aggregations, $sourceFields, $limit, $offset, $sort);
        $models = $instance->scopeResultMapping($q, $results);

        return new ElasticquentPaginator($models, $results['hits']['hits'], $results['hits']['total'], $limit, Paginator::resolveCurrentPage() ? : 1, ['path' => Paginator::resolveCurrentPath(), 'aggregations' => isset($results['aggregations']) ? $results['aggregations'] : []]);
    }

    /**
     * Search By Model Query Builder return Model Collection with Pagination
     *
     * Search with a query array
     *
     * @param \Illuminate\Database\Eloquent\Builder $q
     * @param array $query
     * @param array $aggregations
     * @param array $sourceFields
     * @param int   $limit
     * @param int   $offset
     * @param array $sort
     *
     * @return ElasticquentResultCollection
     */
    public static function scopePaginateByModelQueryBuilder($q, $params = [], $columns = [], $es_global_scope = true)
    {
        $instance = new static;

        $results = $instance->complexSearchResult($params, $columns, $es_global_scope);
        $models = $instance->scopeResultMapping($q, $results);

        if ($params instanceof ElasticquentQueryBuilder) {
            $params = $params->buildMergeQuery();
        }

        return new ElasticquentPaginator($models, $results['hits']['hits'], $results['hits']['total'], $params['size'], Paginator::resolveCurrentPage() ? : 1, ['path' => Paginator::resolveCurrentPath(), 'aggregations' => isset($results['aggregations']) ? $results['aggregations'] : []]);
    }

    /**
     * Perform a "complex" or custom search.
     *
     * Using this method, a custom query can be sent to Elasticsearch.
     *
     * @param  $params parameters to be passed directly to Elasticsearch
     * @return ElasticquentResultCollection
     */
    public static function complexSearch($params = [], $columns = [], $es_global_scope = true)
    {
        $instance = new static;

        $result = $instance->scopeComplexSearchResult(null, $params, $columns, $es_global_scope);

        return static::hydrateElasticsearchResult($result);
    }

    /**
     * Perform a "complex" or custom search.
     *
     * Using this method, a custom query can be sent to Elasticsearch.
     *
     * @param  $params parameters to be passed directly to Elasticsearch
     * @return ElasticquentResultCollection
     */
    public static function scopeComplexSearchResult($q, $params = [], $columns = [], $es_global_scope = true)
    {
        $instance = new static;
        $es_scope = [];

        if ($params instanceof ElasticquentQueryBuilder) {
            $params = $params->buildMergeQuery();
        }

        if ($es_global_scope) {
            $es_scope = $instance->getESGlobalScope();
        }

        $params = array_merge_recursive($instance->getBasicEsParams(true, true), $params, $es_scope);

        if (!empty($columns)) {
            if (array_key_exists('_source', $params['body'])) {
                $params['body']['_source']['include'] = array_values(array_unique(array_merge_recursive($params['body']['_source']['include'], $columns)));
            } else {
                $params['body']['_source']['include'] = $columns;
            }
        }

        return $instance->getElasticSearchClient()->search($params);
    }

    /**
     * Search
     *
     * Simple search using a match _all query
     *
     * @param string $term
     *
     * @return ElasticquentResultCollection
     */
    public static function search($term = '')
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        $params['body']['query']['match']['_all'] = $term;

        $result = $instance->getElasticSearchClient()->search($params);

        return static::hydrateElasticsearchResult($result);
    }

    /**
     * Add to Search Index
     *
     * @throws Missing404Exception
     * @param int $version
     * @return array
     */
    public function addToIndex($version = null)
    {
        if (!$this->exists) {
            throw new Missing404Exception('Document does not exist.');
        }

        $params = $this->getBasicEsParams();

        // Get our document body data.
        $body = $this->getIndexDocumentData();

        foreach ($body as $field => $value) {
            if ($value instanceof Carbon) {
                $body[$field] = $value->toDateTimeString();
            }
        }

        $params['body'] = $body;

        // Get our document with version.
        if ($version) {
            $params['version'] = $version;
        }

        // The id for the document must always mirror the
        // key for this model, even if it is set to something
        // other than an auto-incrementing value. That way we
        // can do things like remove the document from
        // the index, or get the document from the index.
        $params['id'] = $this->getKey();

        return $this->getElasticSearchClient()->index($params);
    }

    /**
     * Remove From Search Index
     *
     * @return array|bool
     */
    public function removeFromIndex()
    {
        try {
            return $this->getElasticSearchClient()->delete($this->getBasicEsParams());
        } catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * Partial Update to Indexed Document
     *
     * @return array|bool
     */
    public function updateIndex()
    {
        $params = $this->getBasicEsParams();

        // Get our document body data.
        $body = $this->getIndexDocumentData();

        foreach ($body as $field => $value) {
            if ($value instanceof Carbon) {
                $body[$field] = $value->toDateTimeString();
            }
        }

        $params['body']['doc'] = $body;

        try {
            return $this->getElasticSearchClient()->update($params);
        } catch (Missing404Exception $e) {
            return false;
        }
    }

    /**
     * @param int $version
     * @return array|bool
     */
    public function indexWithVersion($version)
    {
        try {
            return $this->addToIndex($version);
        } catch (Missing404Exception $e) {
            return false;
        } catch (Conflict409Exception $e) {
            return false;
        }
    }

    /**
     * Runs indexing functions after calling
     * Eloquent's increment() method.
     *
     * @param array $options
     * @return mixed
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        return $this->autoIndex(parent::increment($column, $amount, $extra));
    }

    /**
     * Runs indexing functions after calling
     * Eloquent's decrement() method.
     *
     * @param array $options
     * @return mixed
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        return $this->autoIndex(parent::decrement($column, $amount, $extra));
    }

    public function autoIndex($saved = true)
    {
        if ($this->getAutoIndex()) {
            // When updating fails, it means that the index doesn't exist, so it is created.
            if (!$this->updateIndex()) {
                $this->addToIndex();
            }
        }
        return $saved;
    }

    /**
     * Get Search Document
     *
     * Retrieve an ElasticSearch document
     * for this entity.
     *
     * @return array
     */
    public function getIndexedDocument()
    {
        return $this->getElasticSearchClient()->get($this->getBasicEsParams());
    }

    /**
     * Get Basic Elasticsearch Params
     *
     * Most Elasticsearch API calls need the index and
     * type passed in a parameter array.
     *
     * @param bool $getIdIfPossible
     * @param bool $getSourceIfPossible
     * @param int  $limit
     * @param int  $offset
     *
     * @return array
     */
    public function getBasicEsParams($getIdIfPossible = true, $getSourceIfPossible = false, $limit = null, $offset = null)
    {
        $params = array(
            'index' => $this->getIndexName(),
            'type' => $this->getTypeName(),
        );

        if ($getIdIfPossible && $this->getKey()) {
            $params['id'] = $this->getKey();
        }

        $fields = $this->buildFieldsParameter($getSourceIfPossible);
        if (!empty($fields)) {
            $params['fields'] = implode(',', $fields);
        }

        if (is_numeric($limit)) {
            $params['size'] = $limit;
        }

        if (is_numeric($offset)) {
            $params['from'] = $offset;
        }

        return $params;
    }

    /**
     * Build the 'fields' parameter depending on given options.
     *
     * @param bool   $getSourceIfPossible
     * @return array
     */
    private function buildFieldsParameter($getSourceIfPossible)
    {
        $fieldsParam = array();

        if ($getSourceIfPossible) {
            $fieldsParam[] = '_source';
        }

        return $fieldsParam;
    }

    /**
     * Mapping Exists
     *
     * @return bool
     */
    public static function mappingExists()
    {
        $instance = new static;

        $mapping = $instance->getMapping();

        return (empty($mapping)) ? false : true;
    }

    /**
     * Get Mapping
     *
     * @return void
     */
    public static function getMapping()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->getMapping($params);
    }

    /**
     * Put Mapping.
     *
     * @param bool $ignoreConflicts
     *
     * @return array
     */
    public static function putMapping($ignoreConflicts = false)
    {
        $instance = new static;

        $mapping = $instance->getBasicEsParams();

        $params = array(
            '_source' => array('enabled' => true),
            'properties' => $instance->getMappingProperties(),
        );

        $mapping['body'][$instance->getTypeName()] = $params;

        return $instance->getElasticSearchClient()->indices()->putMapping($mapping);
    }

    /**
     * Delete Mapping
     *
     * @return array
     */
    public static function deleteMapping()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->deleteMapping($params);
    }

    /**
     * Rebuild Mapping
     *
     * This will delete and then re-add
     * the mapping for this model.
     *
     * @return array
     */
    public static function rebuildMapping()
    {
        $instance = new static;

        // If the mapping exists, let's delete it.
        if ($instance->mappingExists()) {
            $instance->deleteMapping();
        }

        // Don't need ignore conflicts because if we
        // just removed the mapping there shouldn't
        // be any conflicts.
        return $instance->putMapping();
    }

    /**
     * Create Index
     *
     * @param int $shards
     * @param int $replicas
     *
     * @return array
     */
    public static function createIndex($shards = null, $replicas = null)
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        $settings = $instance->getIndexSettings();
        if (!is_null($settings)) {
            $index['body']['settings'] = $settings;
        }

        if (!is_null($shards)) {
            $index['body']['settings']['number_of_shards'] = $shards;
        }

        if (!is_null($replicas)) {
            $index['body']['settings']['number_of_replicas'] = $replicas;
        }

        $mappingProperties = $instance->getMappingProperties();
        if (!is_null($mappingProperties)) {
            $index['body']['mappings'][$instance->getTypeName()] = [
                '_source' => array('enabled' => true),
                'properties' => $mappingProperties,
            ];
        }

        return $client->indices()->create($index);
    }

    /**
     * Delete Index
     *
     * @return array
     */
    public static function deleteIndex()
    {
        $instance = new static;

        $client = $instance->getElasticSearchClient();

        $index = array(
            'index' => $instance->getIndexName(),
        );

        return $client->indices()->delete($index);
    }

    /**
     * Type Exists.
     *
     * Does this type exist?
     *
     * @return bool
     */
    public static function typeExists()
    {
        $instance = new static;

        $params = $instance->getBasicEsParams();

        return $instance->getElasticSearchClient()->indices()->existsType($params);
    }

    /**
     * New From Hit Builder
     *
     * Variation on newFromBuilder. Instead, takes
     *
     * @param array $hit
     *
     * @return static
     */
    public function newFromHitBuilder($hit = array())
    {
        $key_name = $this->getKeyName();
        
        $attributes = $hit['_source'];

        if (isset($hit['_id'])) {
            $attributes[$key_name] = is_numeric($hit['_id']) ? intval($hit['_id']) : $hit['_id'];
        }
        
        // Add fields to attributes
        if (isset($hit['fields'])) {
            foreach ($hit['fields'] as $key => $value) {
                $attributes[$key] = $value;
            }
        }

        $instance = $this::newFromBuilderRecursive($this, $attributes);

        // In addition to setting the attributes
        // from the index, we will set the score as well.
        $instance->documentScore = $hit['_score'];

        // This is now a model created
        // from an Elasticsearch document.
        $instance->isDocument = true;

        // Set our document version if it's
        if (isset($hit['_version'])) {
            $instance->documentVersion = $hit['_version'];
        }

        return $instance;
    }

    /**
     * Create a elacticquent result collection of models from plain elasticsearch result.
     *
     * @param  array  $result
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public static function hydrateElasticsearchResult(array $result)
    {
        $items = $result['hits']['hits'];
        return static::hydrateElasticquentResult($items, $meta = $result);
    }

    /**
     * Create a elacticquent result collection of models from plain arrays.
     *
     * @param  array  $items
     * @param  array  $meta
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public static function hydrateElasticquentResult(array $items, $meta = null)
    {
        $instance = new static;

        $items = array_map(function ($item) use ($instance) {
            return $instance->newFromHitBuilder($item);
        }, $items);

        return $instance->newElasticquentResultCollection($items, $meta);
    }

    /**
     * Create a new model instance that is existing recursive.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array  $attributes
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $parentRelation
     * @return static
     */
    public static function newFromBuilderRecursive(Model $model, array $attributes = [], Relation $parentRelation = null)
    {
        $instance = $model->newInstance([], $exists = true);

        $instance->setRawAttributes((array)$attributes, $sync = true);

        // Load relations recursive
        static::loadRelationsAttributesRecursive($instance);
        // Load pivot
        static::loadPivotAttribute($instance, $parentRelation);

        return $instance;
    }

    /**
     * Create a collection of models from plain arrays recursive.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation $parentRelation
     * @param  array $items
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function hydrateRecursive(Model $model, array $items, Relation $parentRelation = null)
    {
        $instance = $model;

        $items = array_map(function ($item) use ($instance, $parentRelation) {
            // Convert all null relations into empty arrays
            $item = $item ?: [];
            
            return static::newFromBuilderRecursive($instance, $item, $parentRelation);
        }, $items);

        return $instance->newCollection($items);
    }

    /**
     * Get the relations attributes from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model $model
     */
    public static function loadRelationsAttributesRecursive(Model $model)
    {
        $attributes = $model->getAttributes();

        foreach ($attributes as $key => $value) {
            if (method_exists($model, $key)) {
                $reflection_method = new ReflectionMethod($model, $key);

                // Check if method class has or inherits Illuminate\Database\Eloquent\Model
                if(!static::isClassInClass("Illuminate\Database\Eloquent\Model", $reflection_method->class)) {
                    $relation = $model->$key();

                    if ($relation instanceof Relation) {
                        // Check if the relation field is single model or collections
                        if (is_null($value) === true || !static::isMultiLevelArray($value)) {
                            $value = [$value];
                        }

                        $models = static::hydrateRecursive($relation->getModel(), $value, $relation);

                        // Unset attribute before match relation
                        unset($model[$key]);
                        $relation->match([$model], $models, $key);
                    }
                }
            }
        }
    }

    /**
     * Get the pivot attribute from a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  \Illuminate\Database\Eloquent\Relations\Relation  $parentRelation
     */
    public static function loadPivotAttribute(Model $model, Relation $parentRelation = null)
    {
        $attributes = $model->getAttributes();

        foreach ($attributes as $key => $value) {
            if ($key === 'pivot' && $parentRelation) {
                unset($model[$key]);
                $pivot = $parentRelation->newExistingPivot($value);
                $model->setRelation($key, $pivot);
            }
        }
    }

    /**
     * Create a new Elasticquent Result Collection instance.
     *
     * @param  array  $models
     * @param  array  $meta
     * @return \Elasticquent\ElasticquentResultCollection
     */
    public function newElasticquentResultCollection(array $models = [], $meta = null)
    {
        return new ElasticquentResultCollection($models, $meta);
    }

    /**
     * Check if an array is multi-level array like [[id], [id], [id]].
     *
     * For detect if a relation field is single model or collections.
     *
     * @param  array  $array
     * @return boolean
     */
    private static function isMultiLevelArray(array $array)
    {
        foreach ($array as $key => $value) {
            if (!is_array($value)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Check the hierarchy of the given class (including the given class itself)
     * to find out if the class is part of the other class.
     *
     * @param string $classNeedle
     * @param string $classHaystack
     * @return bool
     */
    private static function isClassInClass($classNeedle, $classHaystack)
    {
        // Check for the same
        if($classNeedle == $classHaystack) {
            return true;
        }

        // Check for parent
        $classHaystackReflected = new \ReflectionClass($classHaystack);
        while ($parent = $classHaystackReflected->getParentClass()) {
            /**
             * @var \ReflectionClass $parent
             */
            if($parent->getName() == $classNeedle) {
                return true;
            }
            $classHaystackReflected = $parent;
        }

        return false;

    }
}
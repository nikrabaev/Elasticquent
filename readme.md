# Elasticquent Beta

_Elasticsearch for Eloquent Laravel Models_

Elasticquent makes working with [Elasticsearch](http://www.elasticsearch.org/) and [Eloquent](http://laravel.com/docs/eloquent) models easier by mapping them to Elasticsearch types. You can use the default settings or define how Elasticsearch should index and search your Eloquent models right in the model.

Elasticquent uses the [official Elasticsearch PHP API](https://github.com/elasticsearch/elasticsearch-php). To get started, you should have a basic knowledge of how ElasticSearch works (indexes, types, mappings, etc). This is meant for use with Elasticsearch 1.x.

## Overview

Elasticquent allows you take an Eloquent model and easily index and search its contents in Elasticsearch.

    $books = Book::where('id', '<', 200)->get();
    $books->addToIndex();

When you search, instead of getting a plain array of search results, you instead get an Eloquent collection with some special Elasticsearch functionality.

    $books = Book::search('Moby Dick');
    echo $books->totalHits();

Plus, you can still use all the Eloquent collection functionality:

    $books = $books->filter(function($book)
    {
        return $book->hasISBN();
    });

Check out the rest of the documentation for how to get started using Elasticsearch and Elasticquent!

## Setup

Before you start using Elasticquent, make sure you've installed [Elasticsearch](http://www.elasticsearch.org/guide/en/elasticsearch/reference/current/_installation.html).

To get started, add Elasticquent to you composer.json file:

    "fairholm/elasticquent" => "master-dev"

Once you've run a `composer update`, add the Elasticquent trait to any Eloquent model that you want to be able to index in Elasticsearch:

```php
use Elasticquent\ElasticquentTrait;

class Book extends Eloquent {

    use ElasticquentTrait;

}
```

Now your Eloquent model has some extra methods that make it easier to index your model's data using Elasticsearch. 

## Indexes and Mapping

While you can definitely build your indexes and mapping through the Elasticsearch API, you can also use some helper methods to build indexes and types right from your models.

If you want a simple way to create indexes, Elasticquent models have a function for that: 

    Book::createIndex($shards = null, $replicas = null);

For mapping, you can set a `mappingProperties` property in your model and use some mapping functions from there:

```php
protected $mappingProperties = array(
   'title' => array(
        'type' => 'string',
        'analyzer' => 'standard'
    )
);
```

If you'd like to setup a model's type mapping based on your mapping properties, you can use:

    Book::putMapping($ignoreConflicts = true);

To delete a mapping:

    Book::deleteMapping();

To rebuild (delete and re-add, useful when you make important changes to your mapping) a mapping:

    Book::rebuildMapping();

## Basic Usage

To index all the entries in an Eloquent model, use `addAllToIndex`:

    Book::addAllToIndex();

You can also index a collection of models:

    $books = Book::where('id', '<', 200)->get();
    $books->addToIndex();

You can index individual entries as well:

    $book = Book::find($id);
    $book->addToIndex();

### Setting the Type Name

By default, Elasticquent will use the table name for your model as the type name for indexing. If you'd like to override it, you can with the `getTypeName` function.

```php
function getTypeName()
{
    return 'custom_type_name';
}
```

### Document IDs & Sources

Elasticquent will use whatever is set as the `primaryKey` for your Eloquent models as the id for your Elasticsearch documents.

Elasticquent also must use Elasticsearch with the document source on. This is because when search results are returned by Elasticquent, it uses the document source to populate a collection in the same way it'd populate a collection from the database.

### Document Data

By default, Elasticquent will use the entire attribute array for your Elasticsearch documents. However, if you want to customize how your search documents are structured, you can set a `getIndexDocumentData` function that returns you own custom document array.

```php
function getIndexDocumentData()
{
    return array(
        'id'      => $this->id,
        'title'   => $this->title,
        'custom'  => 'variable'
    );
}
```

### More Options

By default, document sources are enabled. To turn document sources off, set a property in your Eloquent model:

    protected $enableDocumentSource = false;

_Note that you must rebuild your mapping and re-index for this to take effect._

To check if the type for the Elasticquent model exists yet, use `typeExists`:

    $typeExists = Book::typeExists();

### Using Elasticquent With Custom Collections

If you are using a custom collection with your Eloquent models, you just need to add the `ElasticquentCollectionTrait` to your collection so you can use `addToIndex`.

```php
class MyCollection extends \Illuminate\Database\Eloquent\Collection {

    use ElasticquentCollectionTrait;
}
```
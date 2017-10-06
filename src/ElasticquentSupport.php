<?php

namespace Elasticquent;

use Illuminate\Foundation\Application;

class ElasticquentSupport
{
    use ElasticquentClientTrait;

    public static function isLaravel5()
    {
        return version_compare(Application::VERSION, '5', '>');
    }

    public static function isLumen5()
    {
        return str_contains(app()->version(), ['Lumen', 'Laravel Components']);
    }
}

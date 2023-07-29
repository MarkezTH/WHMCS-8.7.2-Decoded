<?php

namespace WHMCS\Knowledgebase;

class KnowledgebaseServiceProvider implements \WHMCS\Route\Contracts\DeferredProviderInterface
{
    use \WHMCS\Route\ProviderTrait;

    protected function getRoutes()
    {
        return ["/knowledgebase" => [["name" => "knowledgebase-article-view", "method" => ["GET", "POST"], "path" => "/{id:\\d+}[/{slug}.html]", "handle" => ["WHMCS\\Knowledgebase\\Controller\\Article", "view"]], ["name" => "knowledgebase-category-view", "method" => "GET", "path" => "/{categoryId:\\d+}/{categoryName}", "handle" => ["WHMCS\\Knowledgebase\\Controller\\Category", "view"]], ["name" => "knowledgebase-tag-view", "method" => "GET", "path" => "/tag/{tag}", "handle" => ["WHMCS\\Knowledgebase\\Controller\\Knowledgebase", "viewTag"]], ["name" => "knowledgebase-search", "method" => ["GET", "POST"], "path" => "/search[/{search}]", "handle" => ["WHMCS\\Knowledgebase\\Controller\\Knowledgebase", "search"]], ["name" => "knowledgebase-index", "method" => "GET", "path" => "", "handle" => ["WHMCS\\Knowledgebase\\Controller\\Knowledgebase", "index"]]]];
    }

    public function getDeferredRoutePathNameAttribute()
    {
        return "knowledgebase-";
    }
}

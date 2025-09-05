<?php

namespace vnali\counter\gql\arguments;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class TrendingPagesArguments
{
    // Public Methods
    // =========================================================================
    public static function getArguments(): array
    {
        return [
            'dateRange' => [
                'name' => 'dateRange',
                'type' => Type::nonNull(Type::STRING()),
                'description' => 'Date Range',
            ],
            'growthType' => [
                'name' => 'growthType',
                'type' => Type::STRING(),
                'description' => 'Growth Type',
            ],
            'ignoreNewPages' => [
                'name' => 'ignoreNewPages',
                'type' => Type::BOOLEAN(),
                'description' => 'Exclude pages without previous visits',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::STRING(),
                'description' => 'Site Id',
            ],
            'limit' => [
                'name' => 'limit',
                'type' => Type::INT(),
                'description' => 'Limit',
            ],
            'showElementTitle' => [
                'name' => 'showElementTitle',
                'type' => Type::BOOLEAN(),
                'description' => 'Show element title',
            ],
            'filters' => [
                'name' => 'filters',
                'type' => self::getFiltersInputType(),
                'description' => 'Filter elements including section handles',
            ],
            't' => [
                'name' => 't',
                'type' => Type::STRING(),
                'description' => 'Random unique value to prevent reading result from cache',
            ],
        ];
    }

    private static function getFiltersInputType(): InputObjectType
    {
        return new InputObjectType([
            'name' => 'TrendingFiltersInput',
            'fields' => [
                'sectionHandles' => [
                    'type' => Type::listOf(Type::STRING()),
                    'description' => 'Array of section handles',
                ],
                'items' => [
                    'type' => Type::listOf(Type::STRING()),
                    'description' => 'Array of items: page, entry, category',
                ],
            ],
        ]);
    }
}

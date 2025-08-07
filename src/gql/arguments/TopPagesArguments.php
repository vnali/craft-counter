<?php

namespace vnali\counter\gql\arguments;

use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\Type;

class TopPagesArguments
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
            'name' => 'FiltersInput',
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

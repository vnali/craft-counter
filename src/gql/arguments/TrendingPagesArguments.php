<?php

namespace vnali\counter\gql\arguments;

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
                'description' => 'Ignore new pages',
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
}

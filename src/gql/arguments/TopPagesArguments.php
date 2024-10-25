<?php

namespace vnali\counter\gql\arguments;

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

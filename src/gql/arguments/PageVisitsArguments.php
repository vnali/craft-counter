<?php

namespace vnali\counter\gql\arguments;

use GraphQL\Type\Definition\Type;

class PageVisitsArguments
{
    // Public Methods
    // =========================================================================
    public static function getArguments(): array
    {
        return [
            'page' => [
                'name' => 'page',
                'type' => Type::nonNull(Type::STRING()),
                'description' => 'Page',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::STRING(),
                'description' => 'Site Id',
            ],
            't' => [
                'name' => 't',
                'type' => Type::STRING(),
                'description' => 'Random unique value to prevent reading result from cache',
            ],
        ];
    }
}

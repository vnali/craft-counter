<?php

namespace vnali\counter\gql\arguments;

use GraphQL\Type\Definition\Type;

class CounterArguments
{
    // Public Methods
    // =========================================================================
    public static function getArguments(): array
    {
        return [
            'calendar' => [
                'name' => 'calendar',
                'type' => Type::STRING(),
                'description' => 'Calendar',
            ],
            'dateRange' => [
                'name' => 'dateRange',
                'type' => Type::STRING(),
                'description' => 'Date Range',
            ],
            'endDate' => [
                'name' => 'endDate',
                'type' => Type::STRING(),
                'description' => 'End Date',
            ],
            'ignoreVisitsInterval' => [
                'name' => 'ignoreVisitsInterval',
                'type' => Type::BOOLEAN(),
                'description' => 'Ignore visits interval',
            ],
            'onlineThreshold' => [
                'name' => 'onlineThreshold',
                'type' => Type::INT(),
                'description' => 'Online Threshold',
            ],
            'siteId' => [
                'name' => 'siteId',
                'type' => Type::STRING(),
                'description' => 'Site Id',
            ],
            'startDate' => [
                'name' => 'startDate',
                'type' => Type::STRING(),
                'description' => 'Start Date',
            ],
            't' => [
                'name' => 't',
                'type' => Type::STRING(),
                'description' => 'Random unique value to prevent reading result from cache',
            ],
        ];
    }
}

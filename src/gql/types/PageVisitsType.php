<?php

namespace vnali\counter\gql\types;

use craft\gql\base\GqlTypeTrait;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;
use vnali\counter\gql\helpers\Gql;

class PageVisitsType extends ObjectType
{
    use GqlTypeTrait;

    public static function getName(): string
    {
        return 'PageVisitsType';
    }

    public static function getFieldDefinitions(): array
    {
        $fields = [];
        if (Gql::canQueryItem('pageVisitsAll')) {
            $fields['all'] = [
                'name' => 'all',
                'type' => Type::INT(),
                'description' => 'Page all time visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsAllIgnoreInterval')) {
            $fields['allIgnoreInterval'] = [
                'name' => 'allIgnoreInterval',
                'type' => Type::INT(),
                'description' => 'Page all time visits (ignoring interval)',
            ];
        }
        if (Gql::canQueryItem('pageVisitsThisYear')) {
            $fields['thisYear'] = [
                'name' => 'thisYear',
                'type' => Type::INT(),
                'description' => 'Page this year visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsThisMonth')) {
            $fields['thisMonth'] = [
                'name' => 'thisMonth',
                'type' => Type::INT(),
                'description' => 'Page this month visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsThisWeek')) {
            $fields['thisWeek'] = [
                'name' => 'thisWeek',
                'type' => Type::INT(),
                'description' => 'Page week visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsToday')) {
            $fields['today'] = [
                'name' => 'today',
                'type' => Type::INT(),
                'description' => 'Page today visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsPreviousYear')) {
            $fields['previousYear'] = [
                'name' => 'previousYear',
                'type' => Type::INT(),
                'description' => 'Page previous year visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsPreviousMonth')) {
            $fields['previousMonth'] = [
                'name' => 'previousMonth',
                'type' => Type::INT(),
                'description' => 'Page previous month visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsPreviousWeek')) {
            $fields['previousWeek'] = [
                'name' => 'previousWeek',
                'type' => Type::INT(),
                'description' => 'Page Previous week visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsYesterday')) {
            $fields['yesterday'] = [
                'name' => 'yesterday',
                'type' => Type::INT(),
                'description' => 'Page yesterday visits',
            ];
        }
        if (Gql::canQueryItem('pageVisitsLastVisit')) {
            $fields['lastVisit'] = [
                'name' => 'lastVisit',
                'type' => Type::STRING(),
                'description' => 'Page last visit',
            ];
        }
        if (Gql::canQueryItem('debugMessage')) {
            $fields['debugMessage'] = [
                'name' => 'debugMessage',
                'type' => Type::STRING(),
                'description' => 'Debug message',
            ];
        }
        return $fields;
    }

    public static function getType(): Type
    {
        if ($type = GqlEntityRegistry::getEntity(static::getName())) {
            return $type;
        }

        return GqlEntityRegistry::createEntity(
            static::getName(),
            new self([
                'name' => static::getName(),
                'fields' => static::class . '::getFieldDefinitions',
            ])
        );
    }
}

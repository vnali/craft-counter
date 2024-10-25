<?php

namespace vnali\counter\gql\types;

use craft\gql\base\GqlTypeTrait;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;
use vnali\counter\gql\helpers\Gql;

class CounterType extends ObjectType
{
    use GqlTypeTrait;

    public static function getName(): string
    {
        return 'CounterType';
    }

    public static function getFieldDefinitions(): array
    {
        $fields = [];
        if (Gql::canQueryItem('visits')) {
            $fields['visits'] = [
                'name' => 'visits',
                'type' => Type::INT(),
                'description' => 'Site visits',
            ];
        }
        if (Gql::canQueryItem('visitors')) {
            $fields['visitors'] = [
                'name' => 'visitors',
                'type' => Type::INT(),
                'description' => 'Site visitors',
            ];
        }
        if (Gql::canQueryItem('averageVisitors')) {
            $fields['averageVisitors'] = [
                'name' => 'averageVisitors',
                'type' => Type::INT(),
                'description' => 'Site average visitors',
            ];
        }
        if (Gql::canQueryItem('onlineVisitors')) {
            $fields['onlineVisitors'] = [
                'name' => 'onlineVisitors',
                'type' => Type::INT(),
                'description' => 'Site online visitors',
            ];
        }
        if (Gql::canQueryItem('maxOnline')) {
            $fields['maxOnline'] = [
                'name' => 'maxOnline',
                'type' => Type::INT(),
                'description' => 'Site max Online',
            ];
        }
        if (Gql::canQueryItem('maxOnline')) {
            $fields['maxOnlineDate'] = [
                'name' => 'maxOnlineDate',
                'type' => Type::STRING(),
                'description' => 'Site max Online Date',
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

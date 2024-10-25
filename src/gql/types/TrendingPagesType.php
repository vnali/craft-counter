<?php

namespace vnali\counter\gql\types;

use craft\gql\base\GqlTypeTrait;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

class TrendingPagesType extends ObjectType
{
    use GqlTypeTrait;

    public static function getName(): string
    {
        return 'TrendingPagesType';
    }

    public static function getFieldDefinitions(): array
    {
        $fields = [];
        $fields['page'] = [
            'name' => 'page',
            'type' => Type::STRING(),
            'description' => 'Page',
        ];
        $fields['current'] = [
            'name' => 'current',
            'type' => Type::INT(),
            'description' => 'Current views',
        ];
        $fields['previous'] = [
            'name' => 'previous',
            'type' => Type::INT(),
            'description' => 'Previous views',
        ];
        $fields['growth'] = [
            'name' => 'growth',
            'type' => Type::STRING(),
            'description' => 'Growth of views',
        ];
        $fields['debugMessage'] = [
            'name' => 'debugMessage',
            'type' => Type::STRING(),
            'description' => 'Debug message',
        ];
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

<?php

namespace vnali\counter\gql\types;

use craft\gql\base\GqlTypeTrait;
use craft\gql\base\ObjectType;
use craft\gql\GqlEntityRegistry;
use GraphQL\Type\Definition\Type;

class TopPagesType extends ObjectType
{
    use GqlTypeTrait;

    public static function getName(): string
    {
        return 'TopPagesType';
    }

    public static function getFieldDefinitions(): array
    {
        $fields = [];
        $fields['page'] = [
            'name' => 'page',
            'type' => Type::STRING(),
            'description' => 'Page',
        ];
        $fields['visits'] = [
            'name' => 'visits',
            'type' => Type::INT(),
            'description' => 'Visits',
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

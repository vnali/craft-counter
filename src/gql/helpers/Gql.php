<?php

namespace vnali\counter\gql\helpers;

use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\ResolveInfo;

class Gql extends GqlHelper
{
    // Static Methods
    // =========================================================================

    /**
     * If it has permission to the schema
     *
     * @param string $item
     * @return bool
     */
    public static function canQueryItem(string $item): bool
    {
        $allowedEntities = self::extractAllowedEntitiesFromSchema();
        if (isset($allowedEntities['counter']) && in_array($item, $allowedEntities['counter'])) {
            return true;
        }
        return false;
    }

    /**
     * Return top level requested fields in GQL query
     *
     * @param ResolveInfo $info
     * @return array
     */
    public static function getTopLevelFieldNames(ResolveInfo $info): array
    {
        $fields = [];
        $fieldASTs = $info->fieldNodes;
        
        foreach ($fieldASTs as $fieldAST) {
            if (isset($fieldAST->selectionSet)) {
                foreach ($fieldAST->selectionSet->selections as $selection) {
                    if ($selection->kind === 'Field') {
                        /** @var mixed $selection */
                        $fields[] = $selection->name->value;
                    }
                }
            }
        }
    
        return array_unique($fields);
    }
}

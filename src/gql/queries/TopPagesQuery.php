<?php

namespace vnali\counter\gql\queries;

use Craft;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use vnali\counter\Counter;
use vnali\counter\gql\arguments\TopPagesArguments;
use vnali\counter\gql\helpers\Gql;
use vnali\counter\gql\types\TopPagesType;

class TopPagesQuery extends Query
{
    // Public Methods
    // =========================================================================
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !Gql::canQueryItem('topPages')) {
            return [];
        }
        return [
            'topPages' => [
                'type' => Type::listOf(TopPagesType::getType()),
                'args' => TopPagesArguments::getArguments(),
                'resolve' => function($root, $args, $context, $info) {
                    $fields = Gql::getTopLevelFieldNames($info);

                    $dateRange = null;
                    if (isset($args['dateRange'])) {
                        $dateRange = $args['dateRange'];
                    }

                    $limit = null;
                    if (isset($args['limit'])) {
                        $limit = $args['limit'];
                    }

                    // Check access to dateRange
                    if (!Gql::canQueryItem('topPages' . ucfirst($dateRange))) {
                        return [['debugMessage' => 'Selected dateRange is not allowed']];
                    }

                    $siteService = Craft::$app->sites;

                    if (isset($args['siteId'])) {
                        $siteId = $args['siteId'];
                        if ($siteId != '*') {
                            $site = $siteService->getSiteById($args['siteId']);
                        }
                    } else {
                        $site = $siteService->getPrimarySite();
                        $siteId = $site->id;
                    }
                    if ($siteId == '*') {
                        if (!Gql::canQueryItem('sitesAll')) {
                            return [['debugMessage' => 'The site * is not allowed for this schema']];
                        }
                    } elseif (!isset($site->uid) || (!Gql::canQueryItem('sitesAll') && !Gql::canQueryItem('sites' . $site->uid))) {
                        return [['debugMessage' => 'The siteId ' . $siteId . ' is not allowed for this schema']];
                    }

                    $key = array_search('debugMessage', $fields);
                    if ($key !== false) {
                        unset($fields[$key]);
                    }
                    // Reindex the array
                    $fields = array_values($fields);

                    $result = Counter::$plugin->pages->top($dateRange, $siteId, $limit);
                    return $result;
                },
                'description' => 'This query is used to query top pages.',
            ],
        ];
    }
}

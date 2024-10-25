<?php

namespace vnali\counter\gql\queries;

use Craft;
use craft\gql\base\Query;
use GraphQL\Type\Definition\Type;
use vnali\counter\Counter;
use vnali\counter\gql\arguments\TrendingPagesArguments;
use vnali\counter\gql\helpers\Gql;
use vnali\counter\gql\types\TrendingPagesType;

class TrendingPagesQuery extends Query
{
    // Public Methods
    // =========================================================================
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !Gql::canQueryItem('trendingPages')) {
            return [];
        }
        return [
            'trendingPages' => [
                'type' => Type::listOf(TrendingPagesType::getType()),
                'args' => TrendingPagesArguments::getArguments(),
                'resolve' => function($root, $args, $context, $info) {
                    $fields = Gql::getTopLevelFieldNames($info);

                    $dateRange = null;
                    if (isset($args['dateRange'])) {
                        $dateRange = $args['dateRange'];
                    }

                    if (isset($args['limit'])) {
                        $limit = $args['limit'];
                    } else {
                        $limit = null;
                    }

                    if (isset($args['growthType'])) {
                        $growthType = $args['growthType'];
                    } else {
                        $growthType = null;
                    }

                    if (isset($args['ignoreNewPages'])) {
                        $ignoreNewPages = $args['ignoreNewPages'];
                    } else {
                        $ignoreNewPages = null;
                    }

                    // Check access to dateRange
                    if (!Gql::canQueryItem('trendingPages' . ucfirst($dateRange))) {
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
                    // Reindex the array (optional)
                    $fields = array_values($fields);

                    $result = Counter::$plugin->pages->trending($dateRange, $siteId, $growthType, $ignoreNewPages, $limit);
                    return $result;
                },
                'description' => 'This query is used to query trending pages.',
            ],
        ];
    }
}

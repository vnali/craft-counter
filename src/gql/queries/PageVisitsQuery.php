<?php

namespace vnali\counter\gql\queries;

use Craft;
use craft\gql\base\Query;
use vnali\counter\Counter;
use vnali\counter\gql\arguments\PageVisitsArguments;
use vnali\counter\gql\helpers\Gql;
use vnali\counter\gql\types\PageVisitsType;

class PageVisitsQuery extends Query
{
    // Public Methods
    // =========================================================================
    public static function getQueries(bool $checkToken = true): array
    {
        if ($checkToken && !Gql::canQueryItem('pageVisits')) {
            return [];
        }
        return [
            'pageVisits' => [
                'type' => PageVisitsType::getType(),
                'args' => PageVisitsArguments::getArguments(),
                'resolve' => function($root, $args, $context, $info) {
                    $fields = Gql::getTopLevelFieldNames($info);

                    if (isset($args['page'])) {
                        $page = $args['page'];
                    } else {
                        return ['debugMessage' => 'Page is required'];
                    }

                    // Check access to dateRange
                    foreach ($fields as $field) {
                        if ($field != 'debugMessage' && !Gql::canQueryItem('pageVisits' . ucfirst($field))) {
                            return ['debugMessage' => 'Selected dateRange is not allowed for ' . $field];
                        }
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
                            return ['debugMessage' => 'The site * is not allowed for this schema'];
                        }
                    } elseif (!isset($site->uid) || (!Gql::canQueryItem('sitesAll') && !Gql::canQueryItem('sites' . $site->uid))) {
                        return ['debugMessage' => 'The siteId ' . $siteId . ' is not allowed for this schema'];
                    }

                    $key = array_search('debugMessage', $fields);
                    if ($key !== false) {
                        unset($fields[$key]);
                    }
                    // Reindex the array
                    $fields = array_values($fields);

                    $result = Counter::$plugin->pages->visits($page, $siteId, $fields);
                    return $result;
                },
                'description' => 'This query is used to query page visits.',
            ],
        ];
    }
}

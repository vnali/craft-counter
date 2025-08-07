<?php

namespace vnali\counter\queue\jobs;

use Craft;
use craft\base\Batchable;
use craft\db\QueryBatcher;
use craft\queue\BaseBatchedJob;
use vnali\counter\records\PageVisitsRecord;

/**
 * Batch import from Views Work plugin
 */
class ViewsWorkImport extends BaseBatchedJob
{
    public mixed $recordsQuery;

    protected function loadData(): Batchable
    {
        return new QueryBatcher($this->recordsQuery);
    }

    protected function processItem(mixed $item): void
    {
        $recordId = $item->id;
        $siteId = $item->siteId;
        $site = Craft::$app->sites->getSiteById($siteId);
        if (!$site) {
            Craft::info("Site $siteId is not found");
        }
        $elementId = $item->elementId;
        $element = Craft::$app->elements->getElementById($elementId, null, $siteId);
        if (!$element) {
            Craft::info("element $elementId is not found for siteId $siteId");
        }
        if (!$element->getUrl()) {
            Craft::info("element $elementId has not a url for $siteId");
        }
        $url = $element->getUrl();
        /** @var PageVisitsRecord|null $pageVisitRecord */
        $pageVisitRecord = PageVisitsRecord::find()->where(['page' => $url])->one();
        if (!$pageVisitRecord) {
            $pageVisitRecord = new PageVisitsRecord();
            $pageVisitRecord->page = $url;
            $pageVisitRecord->siteId = $item->siteId;
            $pageVisitRecord->allTime = $item->viewsTotal;
            $pageVisitRecord->allTimeIgnoreInterval = $item->viewsTotal;
            $pageVisitRecord->today = $item->viewsToday;
            $pageVisitRecord->thisWeek = $item->viewsThisWeek;
            $pageVisitRecord->thisMonth = $item->viewsThisMonth;
            $pageVisitRecord->thisYear = $item->viewsTotal;
            $pageVisitRecord->dateCreated = $item->dateCreated;
            $pageVisitRecord->dateUpdated = $item->dateUpdated;
            $pageVisitRecord->lastVisit = $item->dateUpdated;
            if (!$pageVisitRecord->save()) {
                Craft::info("Not imported $recordId");
            }
        } else {
            craft::info("The page generated for record $recordId in views recording table has already a page visits record id $pageVisitRecord->id");
            $pageVisitRecord->allTime = $pageVisitRecord->allTime + $item->viewsTotal;
            $pageVisitRecord->allTimeIgnoreInterval = $pageVisitRecord->allTimeIgnoreInterval + $item->viewsTotal;
            $pageVisitRecord->today = $pageVisitRecord->today + $item->viewsToday;
            $pageVisitRecord->thisWeek = $pageVisitRecord->thisWeek + $item->viewsThisWeek;
            $pageVisitRecord->thisMonth = $pageVisitRecord->thisMonth + $item->viewsThisMonth;
            $pageVisitRecord->thisYear = $pageVisitRecord->thisYear + $item->viewsTotal;
            $pageVisitRecord->dateCreated = ($item->dateCreated >= $pageVisitRecord->dateCreated) ? $pageVisitRecord->dateCreated : $item->dateCreated;
            $pageVisitRecord->dateUpdated = ($item->dateUpdated >= $pageVisitRecord->dateUpdated) ? $item->dateUpdated : $pageVisitRecord->dateUpdated;
            $pageVisitRecord->lastVisit = ($item->dateUpdated >= $pageVisitRecord->dateUpdated) ? $item->dateUpdated : $pageVisitRecord->dateUpdated;
            if (!$pageVisitRecord->save()) {
                Craft::info("Not imported $recordId");
            }
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): ?string
    {
        return craft::t('counter', 'Importing from Views Work plugin');
    }
}

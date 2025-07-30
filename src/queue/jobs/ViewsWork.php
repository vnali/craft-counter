<?php

namespace vnali\counter\queue\jobs;

use Craft;
use craft\queue\BaseJob;
use vnali\counter\records\PageVisitsRecord;

class ViewsWork extends BaseJob
{
    public array $records;

    /**
     * @inheritDoc
     */
    public function execute($queue): void
    {
        $message = null;
        $total = count($this->records);
        foreach ($this->records as $i => $record) {
            $recordId = $record->id;
            $siteId = $record->siteId;
            $site = Craft::$app->sites->getSiteById($siteId);
            if (!$site) {
                Craft::info("Site $siteId is not found");
                continue;
            }
            $elementId = $record->elementId;
            $element = Craft::$app->elements->getElementById($elementId, null, $siteId);
            if (!$element) {
                Craft::info("element $elementId is not found for siteId $siteId");
                continue;
            }
            if (!$element->getUrl()) {
                Craft::info("element $elementId has not a url for $siteId");
                continue;
            }
            $url = $element->getUrl();
            $pageVisitRecord = new PageVisitsRecord();
            $pageVisitRecord->page = $url;
            $pageVisitRecord->siteId = $record->siteId;
            $pageVisitRecord->allTime = $record->viewsTotal;
            $pageVisitRecord->allTimeIgnoreInterval = $record->viewsTotal;
            $pageVisitRecord->today = $record->viewsToday;
            $pageVisitRecord->thisWeek = $record->viewsThisWeek;
            $pageVisitRecord->thisMonth = $record->viewsThisMonth;
            $pageVisitRecord->thisYear = $record->viewsTotal;
            $pageVisitRecord->dateCreated = $record->dateCreated;
            $pageVisitRecord->dateUpdated = $record->dateUpdated;
            $pageVisitRecord->lastVisit = $record->dateUpdated;
            if ($pageVisitRecord->save()) {
                $message = "Imported $recordId";
            } else {
                $message = "Not imported $recordId";
                Craft::info("Not imported $recordId");
            }
            $this->setProgress(
                $queue,
                $i / $total,
                Craft::t('app', '{step, number} of {total, number} {message}', [
                    'step' => $i + 1,
                    'total' => $total,
                    'message' => $message,
                ])
            );
        }
    }

    /**
     * @inheritDoc
     *
     */
    protected function defaultDescription(): ?string
    {
        return 'Import statistics from Views Work';
    }
}

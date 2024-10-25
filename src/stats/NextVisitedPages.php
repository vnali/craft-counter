<?php

namespace vnali\counter\stats;

use Craft;
use craft\db\Query;
use craft\helpers\Db;
use DateTime;
use vnali\counter\base\Date;
use vnali\counter\records\PageVisitsRecord;
use yii\db\Expression;

class NextVisitedPages extends Date
{
    public ?int $pageId = null;

    public ?int $difference = null;

    public ?int $nextPagesLimit = null;

    public ?string $type = null;

    /**
     * @inheritDoc
     */
    public function __construct(string $dateRange = null, DateTime|false|null $startDate = null, DateTime|false|null $endDate = null, ?int $pageId, ?int $difference, ?int $nextPagesLimit, ?string $type)
    {
        $this->pageId = $pageId;
        $this->difference = $difference;
        $this->nextPagesLimit = $nextPagesLimit;
        $this->type = $type;
        parent::__construct($dateRange, $startDate, $endDate);
    }


    /**
     * @inheritDoc
     */
    public function getData(): string|int|bool|array|null
    {
        list(, $startDate, $endDate) = $this->_baseData();
        /** @var PageVisitsRecord|null $pageRecord */
        $pageRecord = PageVisitsRecord::find()->where(['id' => $this->pageId])->one();
        if (!$pageRecord) {
            return null;
        }
        $page = $pageRecord->page;

        // No next page or next page not in time threshold
        $subQuery = (new Query())
            ->select(new Expression('MIN(t3.id)'))
            ->from('craft_counter_visitors t3')
            ->where('t1.visitor = t3.visitor')
            ->andWhere('t1.id < t3.id')
            ->andWhere(['t3.skip' => false]);

        // Create the main query
        $query = (new Query())
            ->select([
                't2.page AS next_page',
                't2.dateCreated AS next_visit_time',
            ])
            ->from('craft_counter_visitors t1')
            ->leftJoin('craft_counter_visitors t2', 't1.visitor = t2.visitor and t2.skip=false and t2.id = (' . $subQuery->createCommand()->getRawSql() . ')')
            ->andWhere(['t1.page' => $page])
            ->andWhere(['t1.skip' => false]);

        if (Craft::$app->getDb()->getIsPgsql()) {
            $query->andWhere([
                'or',
                ['t2.page' => null],
                new \yii\db\Expression('EXTRACT(EPOCH FROM t2."dateCreated" - t1."dateCreated") > :difference', [
                    ':difference' => $this->difference,
                ]),
            ])
            ->andWhere(['>=', 't1."dateCreated"',  Db::prepareDateForDb($startDate)])
            ->andWhere(['<=', 't1."dateCreated"', Db::prepareDateForDb($endDate)]);
        } else {
            $where = ['<', 't1.dateCreated', new \yii\db\Expression('t2.dateCreated - INTERVAL :difference SECOND', [
                ':difference' => $this->difference,
            ])];
            $query->andWhere([
                'or',
                ['t2.page' => null],
                $where,
            ])
            ->andWhere(['>=', 't1.dateCreated',  Db::prepareDateForDb($startDate)])
            ->andWhere(['<=', 't1.dateCreated', Db::prepareDateForDb($endDate)]);
        }
        $results1 = $query->all();

        // next page visited by user in threshold
        $subQuery = (new Query())
            ->select(new Expression('MIN(t3.id)'))
            ->from('craft_counter_visitors t3')
            ->where('t1.visitor = t3.visitor')
            ->andWhere('t1.id < t3.id')
            ->andWhere(['t3.skip' => false]);

        $query = (new Query())
            ->select([
                't2.page AS next_page',
                'COUNT(*) AS next_page_count',
            ])
            ->from('craft_counter_visitors t1')
            ->innerJoin('craft_counter_visitors t2', 't1.visitor = t2.visitor and t2.skip=false')
            ->andWhere(['t1.page' => $page])
            ->andWhere(['t1.skip' => false])
            ->andWhere(new Expression('t2.id = (' . $subQuery->createCommand()->getRawSql() . ')'));
        if (Craft::$app->getDb()->getIsPgsql()) {
            $query->andWhere(new \yii\db\Expression('EXTRACT(EPOCH FROM t2."dateCreated" - t1."dateCreated") <= :difference', [
                ':difference' => $this->difference,
            ]))
                ->andWhere(['>=', 't1."dateCreated"',  Db::prepareDateForDb($startDate)])
                ->andWhere(['<=', 't1."dateCreated"', Db::prepareDateForDb($endDate)]);
        } else {
            $query->andWhere(['>=', 't1.dateCreated', new \yii\db\Expression('t2.dateCreated- INTERVAL :difference SECOND', [
                ':difference' => $this->difference,
            ])])
                ->andWhere(['>=', 't1.dateCreated',  Db::prepareDateForDb($startDate)])
                ->andWhere(['<=', 't1.dateCreated', Db::prepareDateForDb($endDate)]);
        }
        $query->groupBy('t2.page');
        $results2 = $query->all();

        $parts = [];
        if (count($results1)) {
            $parts[htmlspecialchars(craft::t('counter', 'No next visits'))] = (count($results1));
        }
        $count = 0;

        foreach ($results2 as $result2) {
            if ($this->nextPagesLimit === null || $count < $this->nextPagesLimit) {
                $count++;
                $parts[$result2['next_page']] = $result2['next_page_count'];
            } else {
                if (!isset($parts[htmlspecialchars(craft::t('counter', 'Other'))])) {
                    $parts[htmlspecialchars(craft::t('counter', 'Other'))] = 0;
                }
                $parts[htmlspecialchars(craft::t('counter', 'Other'))] = $parts[htmlspecialchars(craft::t('counter', 'Other'))] + $result2['next_page_count'];
            }
        }
        arsort($parts);

        return $parts;
    }
}

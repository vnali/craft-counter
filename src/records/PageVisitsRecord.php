<?php
/**
 * @copyright Copyright © vnali
 */

namespace vnali\counter\records;

use craft\db\ActiveRecord;
use yii\db\Expression;

/**
 * Page visits record.
 *
 * @property int $id
 * @property string $page
 * @property int|Expression $allTimeIgnoreInterval
 * @property int|Expression $allTime
 * @property int|Expression $thisYear
 * @property int|Expression $thisMonth
 * @property int|Expression $thisWeek
 * @property int|Expression $today
 * @property int $previousYear
 * @property int $previousMonth
 * @property int $previousWeek
 * @property int $yesterday
 * @property int|null $siteId
 * @property mixed $dateCreated
 * @property mixed $dateUpdated
 * @property mixed $lastVisit
 */
class PageVisitsRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%counter_page_visits}}';
    }
}

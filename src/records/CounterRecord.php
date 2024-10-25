<?php
/**
 * @copyright Copyright © vnali
 */

namespace vnali\counter\records;

use craft\db\ActiveRecord;
use yii\db\Expression;

/**
 * Counter record.
 *
 * @property int $id
 * @property int $year
 * @property int $month
 * @property int $day
 * @property int $hour
 * @property int $quarter
 * @property int|Expression $visits
 * @property int|Expression $visitsIgnoreInterval
 * @property int|Expression $visitors
 * @property int|Expression $newVisitors
 * @property int $maxOnline
 * @property mixed $maxOnlineDate
 * @property int|null $siteId
 * @property mixed $dateCreated
 * @property mixed $dateUpdated
 */
class CounterRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%counter_counter}}';
    }
}

<?php
/**
 * @copyright Copyright © vnali
 */

namespace vnali\counter\records;

use craft\db\ActiveRecord;

/**
 * Visitors record.
 *
 * @property int $id
 * @property string $visitor
 * @property string $page
 * @property int|null $siteId
 * @property bool $skip
 * @property mixed $dateCreated
 * @property mixed $dateUpdated
 */
class VisitorsRecord extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%counter_visitors}}';
    }
}

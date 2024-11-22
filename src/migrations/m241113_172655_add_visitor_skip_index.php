<?php

namespace vnali\counter\migrations;

use craft\db\Migration;

/**
 * m241113_172655_add_visitor_skip_index migration.
 */
class m241113_172655_add_visitor_skip_index extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        // Place migration code here...
        $this->createIndex(null, '{{%counter_visitors}}', ['visitor', 'skip'], false);
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m241113_172655_add_visitor_skip_index cannot be reverted.\n";
        return false;
    }
}

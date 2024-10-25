<?php

/**
 * @copyright Copyright (c) vnali
 */

namespace vnali\counter\migrations;

use Craft;
use craft\db\Migration;
use craft\records\Widget;
use vnali\counter\widgets\AverageVisitors;
use vnali\counter\widgets\DecliningPages;
use vnali\counter\widgets\MaxOnline;
use vnali\counter\widgets\NextVisitedPages;
use vnali\counter\widgets\NotVisitedPages;
use vnali\counter\widgets\Online;
use vnali\counter\widgets\PageStatistics;
use vnali\counter\widgets\TopPages;
use vnali\counter\widgets\TrendingPages;
use vnali\counter\widgets\Visitors;
use vnali\counter\widgets\Visits;
use vnali\counter\widgets\VisitsRecent;
use Yii;

/**
 * Install migration.
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        $this->deleteTables();
        $this->createTables();
        Craft::$app->db->schema->refresh();
        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->deleteWidgets();
        $this->deleteTables();
        return true;
    }

    /**
     * Creates the tables needed for the plugin.
     *
     * @return void
     */
    private function createTables(): void
    {
        // Create counter table
        if (!$this->tableExists('{{%counter_counter}}')) {
            $this->createTable('{{%counter_counter}}', [
                'id' => $this->primaryKey(),
                'year' => $this->smallInteger(),
                'month' => $this->tinyInteger(),
                'day' => $this->tinyInteger(),
                'hour' => $this->tinyInteger(),
                'quarter' => $this->tinyInteger(),
                'visits' => $this->integer()->defaultValue(0),
                'visitsIgnoreInterval' => $this->integer()->defaultValue(0),
                'visitors' => $this->integer()->defaultValue(0),
                'newVisitors' => $this->integer()->defaultValue(0),
                'maxOnline' => $this->integer()->defaultValue(0),
                'maxOnlineDate' => $this->dateTime(),
                'siteId' => $this->integer(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);

            $this->createIndex(null, '{{%counter_counter}}', ['year', 'month', 'day', 'hour', 'quarter', 'siteId'], false);
            $this->createIndex(null, '{{%counter_counter}}', ['dateCreated'], false);
            $this->createIndex(null, '{{%counter_counter}}', ['dateUpdated'], false);
            $this->createIndex(null, '{{%counter_counter}}', ['siteId'], false);
            $this->addForeignKey(null, '{{%counter_counter}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        }

        // Create visitors table
        if (!$this->tableExists('{{%counter_visitors}}')) {
            $this->createTable('{{%counter_visitors}}', [
                'id' => $this->primaryKey(),
                'visitor' => $this->string(64),
                'page' => $this->string(2048),
                'siteId' => $this->integer(),
                'skip' => $this->boolean()->defaultValue(false),
                'dateCreated' => $this->dateTime()->notNull(),
            ]);

            if ($this->db->getIsPgsql()) {
                $this->createIndex(null, '{{%counter_visitors}}', ['page'], false);
            } else {
                $this->createIndex(null, '{{%counter_visitors}}', ['page(768)'], false);
            }

            $this->createIndex(null, '{{%counter_visitors}}', ['visitor'], false);
            $this->createIndex(null, '{{%counter_visitors}}', ['siteId'], false);
            $this->createIndex(null, '{{%counter_visitors}}', ['dateCreated'], false);
            $this->createIndex(null, '{{%counter_visitors}}', ['dateCreated', 'siteId'], false);
            $this->createIndex(null, '{{%counter_visitors}}', ['skip'], false);
            $this->addForeignKey(null, '{{%counter_visitors}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        }

        // Create page visits table
        if (!$this->tableExists('{{%counter_page_visits}}')) {
            $this->createTable('{{%counter_page_visits}}', [
                'id' => $this->primaryKey(),
                'page' => $this->string(2048),
                'siteId' => $this->integer(),
                'allTime' => $this->integer()->defaultValue(0),
                'allTimeIgnoreInterval' => $this->integer()->defaultValue(0),
                'thisYear' => $this->integer()->defaultValue(0),
                'thisMonth' => $this->integer()->defaultValue(0),
                'thisWeek' => $this->integer()->defaultValue(0),
                'today' => $this->integer()->defaultValue(0),
                'previousYear' => $this->integer()->defaultValue(0),
                'previousMonth' => $this->integer()->defaultValue(0),
                'previousWeek' => $this->integer()->defaultValue(0),
                'yesterday' => $this->integer()->defaultValue(0),
                'lastVisit' => $this->dateTime(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
            ]);
            if ($this->db->getIsPgsql()) {
                $this->createIndex(null, '{{%counter_page_visits}}', ['page'], false);
            } else {
                $this->createIndex(null, '{{%counter_page_visits}}', ['page(768)'], false);
            }
            $this->createIndex(null, '{{%counter_page_visits}}', ['siteId'], false);
            $this->createIndex(null, '{{%counter_page_visits}}', ['dateUpdated'], false);
            $this->createIndex(null, '{{%counter_page_visits}}', ['lastVisit'], false);
            $this->addForeignKey(null, '{{%counter_page_visits}}', ['siteId'], '{{%sites}}', ['id'], 'CASCADE', 'CASCADE');
        }
    }

    /**
     * Delete the plugin's tables.
     *
     * @return void
     */
    protected function deleteTables(): void
    {
        $this->dropTableIfExists('{{%counter_counter}}');
        $this->dropTableIfExists('{{%counter_visitors}}');
        $this->dropTableIfExists('{{%counter_page_visits}}');
    }

    /**
     * Delete widgets
     *
     * @return void
     */
    protected function deleteWidgets(): void
    {
        $condition = [
            'or',
            ['type' => AverageVisitors::class],
            ['type' => DecliningPages::class],
            ['type' => MaxOnline::class],
            ['type' => NextVisitedPages::class],
            ['type' => NotVisitedPages::class],
            ['type' => Online::class],
            ['type' => PageStatistics::class],
            ['type' => TopPages::class],
            ['type' => TrendingPages::class],
            ['type' => Visitors::class],
            ['type' => Visits::class],
            ['type' => VisitsRecent::class],
        ];
        Widget::deleteAll($condition);
    }

    /**
     * Check if a table exists.
     *
     * @param string $table
     * @return boolean
     */
    private function tableExists($table): bool
    {
        return (Yii::$app->db->schema->getTableSchema($table) !== null);
    }
}

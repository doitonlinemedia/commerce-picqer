<?php

namespace white\commerce\picqer\migrations;

use Craft;
use craft\db\Migration;

/**
 * m220811_100158_add_purchaseinvoices_table migration.
 */
class m220811_100158_add_purchaseorders_table extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp()
    {
        // Place migration code here...
        if (!$this->db->tableExists('{{%commercepicqer_purchaseorders}}')) {
            $this->createTable('{{%commercepicqer_purchaseorders}}', [
                'id'              => $this->bigPrimaryKey(),
                'idPurchaseOrder' => $this->bigInteger(254)->notNull(),
                'purchaseOrderId' => $this->string(254)->notNull(),
                'supplierOrderId' => $this->string()->null(),
                'exactGuid'       => $this->uid(),
                'dateCreated'     => $this->dateTime()->notNull(),
                'dateUpdated'     => $this->dateTime()->notNull(),
                'uid'             => $this->uid(),
            ]);

            $this->createIndex(null, '{{%commercepicqer_purchaseorders}}', ['idPurchaseOrder'], true);
        }
    }

    /**
     * @inheritdoc
     */
    public function safeDown()
    {
        $this->dropTableIfExists('{{%commercepicqer_purchaseorders}}');
    }
}

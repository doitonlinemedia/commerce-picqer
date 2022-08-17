<?php

namespace white\commerce\picqer\jobs;

use Craft;
use craft\commerce\elements\Order;
use white\commerce\picqer\CommercePicqerPlugin;
use yii\queue\RetryableJobInterface;
use craft\commerce\elements\Variant;


class SyncProductsJob extends \craft\queue\BaseJob implements RetryableJobInterface
{

    public int $orderId;

    public function getTtr()
    {
        return 600;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        //get all products
        $products = Variant::find()
            ->status(Variant::STATUS_ENABLED)
            ->get();
        $logger   = CommercePicqerPlugin::getInstance()->log;
        $logger->log('starting bulk creating and updating products in picqer');
        foreach ($products as $product) {
            $picqerId = CommercePicqerPlugin::getInstance()->api->createMissingProduct($product);
            $logger->log('product updated/created with id:' . $picqerId);
        }
        $logger->log('finished bulk create or update of product');
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return \Craft::t('app', 'Syncing products with Picqer');
    }

    public function canRetry($attempt, $error)
    {
        return $attempt < 4;
    }
}

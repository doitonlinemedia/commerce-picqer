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
            ->all();
        $logger   = CommercePicqerPlugin::getInstance()->log;
        $logger->log('starting bulk creating and updating products in picqer');
        $total = count($products);
        foreach ($products as $i => $product) {
            $picqerId = CommercePicqerPlugin::getInstance()->api->createMissingProduct($product);
            $logger->log('product updated/created with id:' . $picqerId);
            $this->setProgress(
                $queue,
                $i / $total,
                \Craft::t('app', '{step, number} of {total, number}', [
                    'step'  => $i + 1,
                    'total' => $total,
                ])
            );
        }
        $logger->log('finished bulk create or update of product');
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return \Craft::t('commerce-picqer', 'Syncing products with Picqer');
    }

    public function canRetry($attempt, $error)
    {
        return $attempt < 4;
    }
}

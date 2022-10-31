<?php

namespace white\commerce\picqer\jobs;

use Craft;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\models\OrderSyncStatus;
use yii\queue\RetryableJobInterface;
use craft\commerce\elements\Order;


class ChangeStatusOfOrderJob extends \craft\queue\BaseJob implements RetryableJobInterface
{

    public int    $orderId;
    public string $picqerStatus;
    public int    $newStatusId;

    public function getTtr()
    {
        return 600;
    }

    /**
     * @inheritdoc
     */
    public function execute($queue): void
    {
        //Sleep 5 for status completed. picQer calls [picklists.shipments.created AND orders.status_changed] at the same time when picklist is done in 1 go.
        if ($this->picqerStatus === 'completed') {
            sleep(5);
        }
        $logger               = CommercePicqerPlugin::getInstance()->log;
        $order                = Order::find()
            ->id($this->orderId)
            ->one();
        $order->orderStatusId = $this->newStatusId;
        $order->message       = \Craft::t(
            'commerce-picqer',
            "[Picqer] Status updated via webhook ({status})",
            ['status' => $this->picqerStatus]
        );
        if (!\Craft::$app->getElements()->saveElement($order)) {
            throw new \Exception("Could not update order status. " . json_encode($order->getFirstErrors()));
        } else {
            $logger->log(
                "Order status changed to '{$order->orderStatusId}' for order '{$order->reference}'."
            );
        }
    }

    /**
     * @inheritdoc
     */
    protected function defaultDescription(): string
    {
        return \Craft::t('commerce-picqer', 'Change status from webhook');
    }

    public function canRetry($attempt, $error)
    {
        return $attempt < 4;
    }
}

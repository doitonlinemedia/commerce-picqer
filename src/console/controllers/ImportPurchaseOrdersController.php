<?php


namespace white\commerce\picqer\console\controllers;

use craft\helpers\App;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\services\PurchaseOrderService;

class ImportPurchaseOrdersController extends \yii\console\Controller
{

    /**
     * @var \white\commerce\picqer\services\PicqerApi
     */
    private $picqerApi;

    /**
     * @var PurchaseOrderService
     */
    private $purchaseOrderService;

    /**
     * @var \white\commerce\picqer\services\Log
     */
    private $log;

    public function init()
    {
        parent::init();

        $this->picqerApi            = CommercePicqerPlugin::getInstance()->api;
        $this->purchaseOrderService = CommercePicqerPlugin::getInstance()->purchaseOrderService;
        $this->log                  = CommercePicqerPlugin::getInstance()->log;
    }

    public function actionImport()
    {

        $this->log->log("Importing purchase orders from Picqer.");

        $count = 0;
        foreach ($this->picqerApi->getClient()->getPurchaseOrders(['status' => 'purchased'])['data'] as $purchaseOrder) {

            try {
                $this->purchaseOrderService->updateOrCreatePurchaseOrder($purchaseOrder);
                $count++;
            } catch (\Exception $e) {
                $this->log->error("Cound not process a purchase order.", $e);


                throw $e;

            }
        }

        $this->log->log("Purchase order import finished. Total orders processed: {$count}.");
        return 0;
    }
}

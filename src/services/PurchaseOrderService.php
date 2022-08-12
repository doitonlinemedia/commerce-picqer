<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use Solspace\Freeform\Models\FormModel;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\jobs\ImportPurchaseOrdersJob;
use white\commerce\picqer\records\PurchaseOrder as PurchaseOrderRecord;

use yii\base\Event;

class PurchaseOrderService extends Component
{
    /** @var bool */
    private static bool $allpurchaseInvoicesLoaded;

    private static array $allPurchaseInvoices;

    /** @var PicqerApi */
    private $picqerApi;

    /** @var Log */
    private $log;

    public function init()
    {
        parent::init();

        $this->picqerApi = CommercePicqerPlugin::getInstance()->api;
        $this->log       = CommercePicqerPlugin::getInstance()->log;

    }

    public function getAllPurchaseOrders($orderByName = false): array
    {
        $records = PurchaseOrderRecord::find()->all();

        self::$allPurchaseInvoices = [];
        foreach ($records as $result) {
            self::$allPurchaseInvoices[$result->id] = $result;
        }

        self::$allpurchaseInvoicesLoaded = true;


        return self::$allPurchaseInvoices;
    }

    public function updateOrCreatePurchaseOrder($purchaseOrder)
    {
        $record = PurchaseOrderRecord::findOne([
            'idPurchaseOrder' => $purchaseOrder['idpurchaseorder'],
        ]);
        if (!$record) {
            $record = new PurchaseOrderRecord([
                'idPurchaseOrder' => $purchaseOrder['idpurchaseorder'],
                'purchaseOrderId' => $purchaseOrder['purchaseorderid'],
                'supplierOrderId' => $purchaseOrder['idsupplier'],
            ]);
        } else {
            return false;
        }

        $record->save();

        return true;

    }
}

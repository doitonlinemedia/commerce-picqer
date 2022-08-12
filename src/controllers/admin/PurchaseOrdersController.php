<?php


namespace white\commerce\picqer\controllers\admin;

use Craft;
use craft\web\Controller;
use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Resources\Bundles\CreateFormModalBundle;
use Solspace\Freeform\Resources\Bundles\FormIndexBundle;
use Solspace\Freeform\Services\FormsService;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\services\PurchaseOrderService;

class PurchaseOrdersController extends Controller
{
    public function init()
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');
    }

    public function actionIndex()
    {
        $purchaseInvoiceService = $this->getPurchaseInvoiceService();
        $purchaseInvoices       = $purchaseInvoiceService->getAllPurchaseOrders();


        return $this->renderTemplate(
            'commerce-picqer/purchase-orders/index',
            [
                'purchaseOrders' => $purchaseInvoices,
            ]
        );
    }

    private function getPurchaseInvoiceService(): PurchaseOrderService
    {
        return CommercePicqerPlugin::getInstance()->purchaseOrderService;
    }

}

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

class ProductsController extends Controller
{
    public function init()
    {
        parent::init();

        $this->requirePermission('accessPlugin-commerce-picqer');
    }

    public function actionSyncProducts()
    {
        CommercePicqerPlugin::getInstance()->productSync->syncProducts();
        \Craft::$app->session->setFlash(
            'message',
            \Craft::t('commerce-picqer', 'Picqer product sync job added to queue.')
        );
        return $this->redirect('commerce-picqer/settings');
    }

}

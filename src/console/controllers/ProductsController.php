<?php


namespace white\commerce\picqer\console\controllers;

use Craft;
use craft\console\Controller;
use Solspace\Commons\Helpers\PermissionHelper;
use Solspace\Freeform\Freeform;
use Solspace\Freeform\Resources\Bundles\CreateFormModalBundle;
use Solspace\Freeform\Resources\Bundles\FormIndexBundle;
use Solspace\Freeform\Services\FormsService;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\services\PurchaseOrderService;

class ProductsController extends Controller
{
    public function syncProducts()
    {
        CommercePicqerPlugin::getInstance()->productSync->syncProducts();
    }

}

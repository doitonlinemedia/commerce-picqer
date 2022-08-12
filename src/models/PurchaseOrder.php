<?php


namespace white\commerce\picqer\models;


use craft\base\Model;

class PurchaseOrder extends Model
{
    /** @var integer */
    public $id;

    /** @var integer */
    public $idPurchaseOrder;

    /** @var integer */
    public $purchaseOrderId;

    /** @var integer */
    public $supplierOrderId;

    /** @var string|null */

    public $dateCreated;
    public $dateUpdated;
    public $exactGuid;
    public $uid;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type', 'idPurchaseOrder'], 'required'],
        ];
    }
}

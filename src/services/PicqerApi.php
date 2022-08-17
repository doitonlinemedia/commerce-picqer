<?php


namespace white\commerce\picqer\services;

use craft\base\Component;
use craft\commerce\base\Purchasable;
use craft\commerce\base\PurchasableInterface;
use craft\commerce\elements\Order;
use craft\commerce\elements\Product;
use craft\commerce\elements\Variant;
use craft\commerce\models\Address;
use Picqer\Api\Client as PicqerApiClient;
use white\commerce\picqer\CommercePicqerPlugin;
use white\commerce\picqer\errors\PicqerApiException;
use white\commerce\picqer\models\Settings;
use yii\base\InvalidConfigException;

class PicqerApi extends Component
{
    /** @var Settings */
    private $settings;

    /** @var \Picqer\Api\Client */
    private $client;

    /** @var Log */
    private $log;


    public function init()
    {
        parent::init();

        $this->log = CommercePicqerPlugin::getInstance()->log;
        if ($this->settings === null) {
            $this->settings = CommercePicqerPlugin::getInstance()->getSettings();
        }
    }

    public function getClient()
    {
        if ($this->client === null) {
            $apiClient = new \Picqer\Api\Client($this->settings->getApiDomain(), $this->settings->getApiKey());
            $apiClient->enableRetryOnRateLimitHit();
            $apiClient->setUseragent(
                CommercePicqerPlugin::getInstance()->description . ' (' . CommercePicqerPlugin::getInstance(
                )->developerUrl . ')'
            );

            $this->client = $apiClient;
        }

        return $this->client;
    }

    /**
     * @param array $filters
     * @return \Generator
     * @throws \Picqer\Api\Exception
     */
    public function getProducts(array $filters = [])
    {
        return $this->getClient()->getResultGenerator('product', $filters);
    }

    /**
     * @param string $productCode
     * @return array
     */
    public function getProductByProductCode($productCode)
    {
        return $this->getClient()->getProductByProductcode($productCode);
    }

    /**
     * @param Purchasable $purchasable
     * @return string
     * @throws InvalidConfigException
     */
    public function checkProductType(Purchasable $purchasable): string
    {
        $product            = $this->getCraftProductBySku($purchasable->getSKU());
        $virtualProductType = $this->settings->virtualProductType;
        return $product->getType()->handle === $virtualProductType ? 'virtual_composition' : 'normal';
    }

    public function getCraftProductBySku($sku)
    {
        if ($variant = Variant::find()->where(['sku' => $sku])->one()) {
            return $variant->getProduct();
        }
        return null;
    }

    /**
     * @param Purchasable $purchasable
     * @return mixed
     * @throws InvalidConfigException
     */
    public function createMissingProduct(Purchasable $purchasable)
    {
        $result      = $this->getClient()->getProducts(['productcode' => $purchasable->getSku()]);
        $productData = [
            'productcode' => $purchasable->getSku(),
            'name'        => $purchasable->getDescription(),
            'price'       => $purchasable->getPrice(),
            'type'        => $this->checkProductType($purchasable),
        ];
        if (empty($result['data'])) {
            //if product for the first time added, add stock to picqer
            $response = $this->getClient()->addProduct($productData);
        } else {
            $response = $this->getClient()->updateProduct($result['data'][0]['idproduct'], $productData);
        }
        if ($productData['type'] === 'virtual_composition') {
            $this->log->log('product is of virtual_composition with SKU:' . $productData['productcode']);
            $this->createOrUpdateVirtualParts($response['data']['idproduct'], $purchasable);
        }

        return $response['data']['idproduct'];
    }

    public function createOrUpdateVirtualParts(int $productId, Purchasable $purchasable)
    {
        $craftParts        = $purchasable->getProduct()->bundles;
        $craftSkus         = [];
        $craftPartProducts = [];
        //gather all SKU's from craft Parts and gather all amounts from craft Parts
        foreach ($craftParts as $craftPart) {
            $craftSku                     = $craftPart->bundleProduct[0]->getSKU();
            $craftSkus[$craftSku]         = $craftPart->bundleCount;
            $craftPartProducts[$craftSku] = $craftPart->bundleProduct[0];
        }
        $response = $this->getClient()->getProductParts($productId);

        if (!empty($response['data'])) {
            $this->log->log(count($response['data']) . ' products found for updating virtual composition.');
            foreach ($response['data'] as $productPart) {
                //if part is found in bundle parts
                if ($key = array_search($productPart['productcode'], $craftSkus)) {
                    $this->log->log('picqer part found in bundle in craft with SKU:' . $key);

                    //product found, is amount the same?
                    if ($productPart['amount'] !== $craftSkus[$productPart['productcode']]) {
                        $this->log->log(
                            'amount for part is different, update amount from:' . $productPart['amount'] . ' to:' . $craftSkus[$productPart['productcode']]
                        );

                        $partResponse = $this->getClient()->updateProductPartAmount(
                            $productId,
                            $productPart['idproduct_part'],
                            $craftSkus[$productPart['productcode']]
                        );
                        $this->log->log('updated product part:' . json_encode($partResponse['data']));
                    }
                    unset($craftSkus[$key]);
                } else {
                    $this->log->log(
                        'part with SKU: ' . $productPart['productcode'] . ' not found in craft so delete from picqer'
                    );
                    //part not found in craft, we need to remove it in picqer
                    $partResponse = $this->getClient()->deleteProductPart($productId, $productPart['idproduct_part']);
                    $this->log->log('removed product part from PicQer:' . json_encode($partResponse['data']));
                }
            }
        }
        //now only the SKU's remain that are need to be added
        foreach ($craftSkus as $sku => $amount) {
            $this->log->log(
                'part not found in picqer but found in craft, adding part with SKU:' . $sku . ' and  amount:' . $amount
            );
            $partResponse = $this->getClient()->addProductPart($productId, [
                'idproduct' => $this->createMissingProduct($craftPartProducts[$sku]),
                'amount'    => $amount
            ]);
            $this->log->log('added product part to picqer:' . json_encode($partResponse['data']));
        }
    }

    public function pushOrder(Order $order, $createMissingProducts = false)
    {
        $data             = $this->buildOrderData($order);
        $data['products'] = [];
        foreach ($order->getLineItems() as $lineItem) {
            if ($createMissingProducts) {
                $this->log->log('updating or creating product in picqer');
                $this->createMissingProduct($lineItem->purchasable);
            }
            $lineData = [
                'productcode' => (string)$lineItem->getSku(),
                'amount'      => $lineItem->qty,
                'remarks'     => (string)$lineItem->note,
            ];

            if ($this->settings->pushPrices) {
                $lineData['price'] = $lineItem->getSalePrice();
            }

            $data['products'][] = $lineData;
        }

        $response = $this->getClient()->addOrder($data);
        if (!$response['success'] || !isset($response['data']['idorder'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function updateOrder($picqerOrderId, Order $order, $createMissingProducts = false)
    {
        $response = $this->getClient()->getOrder($picqerOrderId);
        if (!$response['success'] || empty($response['data']['idorder'])) {
            throw new PicqerApiException($response);
        }
        $picqerOrder = $response['data'];

        // Update order data
        $data                = $this->buildOrderData($order);
        $orderUpdateResponse = $response = $this->getClient()->updateOrder($picqerOrderId, $data);
        if (!$response['success'] || !isset($response['data']['idorder'])) {
            throw new PicqerApiException($response);
        }

        // Check if any products have stock allocated
        $allocated = false;
        $response  = $this->getClient()->getOrderProductStatus($picqerOrderId);
        if (!$response['success'] || empty($response['data']['products'])) {
            throw new PicqerApiException($response);
        }
        foreach ($response['data']['products'] as $picqerProduct) {
            if ($picqerProduct['allocated']) {
                $allocated = true;
                break;
            }
        }

        // Delete old products
        foreach ($picqerOrder['products'] as $picqerProduct) {
            $response = $this->getClient()->sendRequest(
                '/orders/' . $picqerOrderId . '/products/' . $picqerProduct['idorder_product'],
                [],
                PicqerApiClient::METHOD_DELETE
            );
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }
        }

        // Push new products
        foreach ($order->getLineItems() as $lineItem) {
            $response = $this->getClient()->getProducts(['productcode' => $lineItem->getSku()]);
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }

            $lineData = [
                'idproduct' => $this->createMissingProduct($lineItem->purchasable),
                'amount'    => $lineItem->qty,
                'remarks'   => (string)$lineItem->note,
            ];

            if ($this->settings->pushPrices) {
                $lineData['price'] = $lineItem->getSalePrice();
            }

            $response = $this->getClient()->sendRequest(
                '/orders/' . $picqerOrderId . '/products',
                $lineData,
                PicqerApiClient::METHOD_POST
            );
            if (!$response['success']) {
                throw new PicqerApiException($response);
            }
        }

        if ($allocated) {
            $this->allocateStockForOrder($picqerOrderId);
        }

        return $orderUpdateResponse['data'];
    }

    public function findPicqerOrders(Order $order)
    {
        $response = $this->getClient()->getOrders(['reference' => $this->composeOrderReference($order)]);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function allocateStockForOrder($picqerOrderId)
    {
        $response = $this->getClient()->allocateStockForOrder($picqerOrderId);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function processOrder($picqerOrderId)
    {
        $response = $this->getClient()->processOrder($picqerOrderId);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function createHook($data)
    {
        $response = $this->getClient()->addHook($data);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function getHook($id)
    {
        $response = $this->getClient()->getHook($id);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    public function deleteHook($id)
    {
        $response = $this->getClient()->deleteHook($id);
        if (!$response['success'] || !isset($response['data'])) {
            throw new PicqerApiException($response);
        }

        return $response['data'];
    }

    protected function buildOrderData(Order $order)
    {
        return [
            'reference'    => $this->composeOrderReference($order),
            'emailaddress' => $order->email,

            'deliveryname'        => $this->composeAddressName($order->shippingAddress),
            'deliverycontactname' => $this->composeAddressContactName($order->shippingAddress),
            'deliveryaddress'     => $order->shippingAddress->address1,
            'deliveryaddress2'    => $order->shippingAddress->address2,
            'deliveryzipcode'     => $order->shippingAddress->zipCode,
            'deliverycity'        => $order->shippingAddress->city,
            'deliveryregion'      => $order->shippingAddress->stateText,
            'deliverycountry'     => $order->shippingAddress->countryIso,

            'invoicename'        => $this->composeAddressName($order->billingAddress),
            'invoicecontactname' => $this->composeAddressContactName($order->billingAddress),
            'invoiceaddress'     => $order->billingAddress->address1,
            'invoiceaddress2'    => $order->billingAddress->address2,
            'invoicezipcode'     => $order->billingAddress->zipCode,
            'invoicecity'        => $order->billingAddress->city,
            'invoiceregion'      => $order->billingAddress->stateText,
            'invoicecountry'     => $order->billingAddress->countryIso,
        ];
    }

    protected function composeOrderReference(Order $order)
    {
        return $order->reference ?: $order->number;
    }

    protected function composeAddressName(Address $address)
    {
        if ($address->businessName) {
            return $address->businessName;
        }

        if ($address->fullName) {
            return $address->fullName;
        }

        if ($address->firstName || $address->lastName) {
            return trim(sprintf('%s %s', $address->firstName, $address->lastName));
        }

        return $address->id;
    }

    protected function composeAddressContactName(Address $address)
    {
        if ($address->businessName) {
            if ($address->fullName) {
                return $address->fullName;
            }

            if ($address->firstName || $address->lastName) {
                return trim(sprintf('%s %s', $address->firstName, $address->lastName));
            }
        }

        return '';
    }
}

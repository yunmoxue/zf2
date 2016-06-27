<?php

namespace BirdSystem\Utility;

use BirdSystem\Db\TableGateway\Client;
use BirdSystem\Db\TableGateway\DirectionalSharedProduct;
use BirdSystem\Db\TableGateway\Product;
use BirdSystem\Traits\AuthenticationTrait;
use BirdSystem\Traits\LoggerAwareTrait;
use Psr\Log\LoggerAwareInterface;
use Zend\Db\Sql\Select;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class TempConsignmentInfoProductInfo implements LoggerAwareInterface
{
    use AuthenticationTrait, ServiceLocatorAwareTrait, LoggerAwareTrait;

    private static $cache;

    const CACHE_PRODUCTS = 'products';

    const CACHE_CONFIG = 'Config';

    const CACHE_BULK_ORDER_REFERENCE_FIELD = 'BoldOrderReferenceField';

    const CACHE_CLIENT_ID = 'ClientId';

    const CACHE_COMPANY_ID = 'CompanyId';

    const REFERENCE_ID = 'id';

    const REFERENCE_CLIENT_REF = 'client_ref';

    const REFERENCE_COMPANY_REF = 'company_ref';

    const SHARED_PRODUCT_SIGN = '#';

    const DIRECTIONAL_SHARED_PRODUCT_SIGN = 'D';

    /**
     * Parse product id string and format into standard format
     *
     * @param array $productIdString
     * @param int   $quantity
     *
     * @return string
     */
    public function normalizeProductIdString($productIdString, $quantity = 1)
    {
        $productIds = $this->parseProductIdString($productIdString, $quantity);

        $returnString = '';
        foreach ($productIds as $productId => $quantity) {
            $returnString .= $productId . '*' . $quantity . '+';
        }

        $returnString = substr($returnString, 0, -1);

        return $returnString;
    }

    /**
     * ProductIds string should be able to take care format of 'ClientId-ProductIdxQuantity' For example 2-97*2 means
     * ClientId = 2, ProductId = 97, Quantity = 2
     * ClientId & Quantity are optional
     *
     * @param $productIdString string
     * @param $quantity        int
     *
     * @return array
     */
    public function parseProductIdString($productIdString, $quantity = 1)
    {
        $productIdString = str_replace('.', '', $productIdString);
        $referenceType   = self::_getCache(self::CACHE_BULK_ORDER_REFERENCE_FIELD);
        $productIds      = [];

        $items = explode('+', str_replace(' ', '', $productIdString));

        foreach ($items as $item) {
            // @codeCoverageIgnoreStart
            if (strlen($item) == 0) {
                continue;
            }
            // @codeCoverageIgnoreEnd

            $item = explode('*', $item);
            //If system use SKU(client_ref or company_ref), then parse it to product ID
            if ($referenceType) {
                $item[0] = $this->getProductIdByReference($item[0], $referenceType);
            } else {
                // @codeCoverageIgnoreStart
                // Remove Client Id prefix if exists
                $itemWithClientIdPrefix = explode('-', $item[0]);
                $item[0]                =
                    isset($itemWithClientIdPrefix[1]) ? $itemWithClientIdPrefix[1] : $itemWithClientIdPrefix[0];
                // @codeCoverageIgnoreEnd
            }
            // Set quantity to be 1 if not exists
            $item[1] = isset($item[1]) ? $item[1] : 1;

            // Do math if there's more than one quantity number
            if (isset($item[1]) && count($item) > 2) {
                // @codeCoverageIgnoreStart
                // do math with rest of item
                for ($i = 2; $i < count($item); $i++) {
                    if (is_numeric($item[$i])) {
                        $item[1] *= $item[$i];
                    }
                }
                // @codeCoverageIgnoreEnd
            }

            // Update quantity if there's only one item and client ordered more than one in ebay
            $item[1] = $item[1] * $quantity;
            if (isset($productIds[$item[0]])) {
                $productIds[$item[0]] += (int)$item[1];
            } else {
                $productIds[$item[0]] = (int)$item[1];
            }
        }

        return $productIds;
    }

    /**
     * Shared product is represented as ClientId#ProductId
     * Directional Shared product is represented as ClientId#ProductId#D
     *
     * @param string $productReferenceString
     * @param string $referenceType (id|client_ref|company_ref)
     *
     * @return string
     */
    public function getProductIdByReference($productReferenceString, $referenceType)
    {
        $productReferenceString = str_replace(' ', '', $productReferenceString);
        $productReferenceString = str_replace('.', '', $productReferenceString);
        $productReferenceInfo   = explode(self::SHARED_PRODUCT_SIGN, $productReferenceString, 3);

        if (count($productReferenceInfo) > 1) {
            $sharedProductClientId = $productReferenceInfo[0];
            $productReference      = $productReferenceInfo[1];
        } else {
            $productReference = $productReferenceInfo[0];
        }

        $referenceType = strtolower(trim($referenceType));

        $ProductTG = $this->serviceLocator->get(Product::class);
        $products  = self::_getCache(self::CACHE_PRODUCTS);

        if (!isset($products[$productReference])) {
            $isDirectionalSharedProduct = false;
            if (isset($sharedProductClientId)) {
                if (isset($productReferenceInfo[2]) &&
                    $productReferenceInfo[2] == self::DIRECTIONAL_SHARED_PRODUCT_SIGN
                ) {
                    $DirectionalSharedProductTG = $this->serviceLocator->get(DirectionalSharedProduct::class);
                    $select                     = $DirectionalSharedProductTG->getSql()->select()
                        ->join('product', 'product.id = directional_shared_product.product_id', [],
                            Select::JOIN_LEFT)
                        ->where([
                            'directional_shared_product.product_id' => $productReference,
                            'directional_shared_product.client_id'  => self::_getCache(self::CACHE_CLIENT_ID),
                            'directional_shared_product.company_id' => self::_getCache(self::CACHE_COMPANY_ID),
                            'product.status'                        => Product::STATUS_ACTIVE,
                        ]);
                    $product                    = $DirectionalSharedProductTG->fetchRow($select);
                    $isDirectionalSharedProduct = true;
                } else {
                    $select  = $ProductTG->getSql()->select()
                        ->where([
                            'product.id'        => $productReference,
                            'product.client_id' => $sharedProductClientId,
                            'product.status'    => Product::STATUS_ACTIVE,
                        ]);
                    $product = $ProductTG->fetchRow($select);
                }
            } else {
                $select = $ProductTG->getSql()->select()
                    ->where([
                        'product.client_id'      => self::_getCache(self::CACHE_CLIENT_ID),
                        "product.$referenceType" => $productReference,
                        'product.status'         => Product::STATUS_ACTIVE,
                    ]);
                if (!($product = $ProductTG->fetchRow($select))) {
                    $select  = $ProductTG->getSql()->select()
                        ->join('product_secondary_sku', 'product_secondary_sku.product_id = product.id', [])
                        ->where([
                            'product_secondary_sku.client_id' => self::_getCache(self::CACHE_CLIENT_ID),
                            'product_secondary_sku.sku'       => $productReference,
                            'product.status'                  => Product::STATUS_ACTIVE,
                        ]);
                    $product = $ProductTG->fetchRow($select);
                }
            }

            if ($product) {
                if ($isDirectionalSharedProduct) {
                    $products[$productReferenceString] =
                        self::DIRECTIONAL_SHARED_PRODUCT_SIGN . $product->getProductId();
                } else {
                    $products[$productReferenceString] = $product->getId();
                }
            } else {
                $products[$productReferenceString] = $productReferenceString;
            }
        }

        return $products[$productReferenceString];
    }

    private function _getCache($cachedItem = null, $defaultValue = null)
    {
        if (!self::$cache) {
            self::$cache = [];
        }

        switch ($cachedItem) {
            case null:
                return self::$cache;
                break;
            case self::CACHE_PRODUCTS:
                if (!isset(self::$cache[$cachedItem])) {
                    self::$cache[$cachedItem] = [];
                }
                break;
            case self::CACHE_BULK_ORDER_REFERENCE_FIELD:
                if (!isset(self::$cache[$cachedItem])) {
                    $ClientTG                 = $this->serviceLocator->get(Client::class);
                    $client                   = $ClientTG->get(self::_getCache(self::CACHE_CLIENT_ID));
                    self::$cache[$cachedItem] = $client->getBulkOrderReferenceField();
                }
                break;
            case self::CACHE_CLIENT_ID:
                if (!isset(self::$cache[$cachedItem])) {
                    $UserInfo                 = $this->getUserInfo();
                    self::$cache[$cachedItem] = $UserInfo->getClientId();
                }
                break;
            case self::CACHE_COMPANY_ID:
                if (!isset(self::$cache[$cachedItem])) {
                    $UserInfo                 = $this->getUserInfo();
                    self::$cache[$cachedItem] = $UserInfo->getCompanyId();
                }
                break;
            default:
                // @codeCoverageIgnoreStart
                if (!isset(self::$cache[$cachedItem])) {
                    self::$cache[$cachedItem] = $defaultValue;
                }
                break;
                // @codeCoverageIgnoreEnd
        }

        return self::$cache[$cachedItem];
    }
}

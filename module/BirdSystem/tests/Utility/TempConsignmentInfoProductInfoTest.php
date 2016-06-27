<?php

namespace BirdSystem\Tests\Utility;

use Admin\Tests\Traits\AuthenticationTrait as AdminAuthenticationTrait;
use BirdSystem\Db\TableGateway\Product;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\Db\TableGateway\ClientTest;
use BirdSystem\Tests\Db\TableGateway\DirectionalSharedProductTest;
use BirdSystem\Tests\Db\TableGateway\ProductSecondarySkuTest;
use BirdSystem\Tests\Db\TableGateway\ProductTest;
use BirdSystem\Utility\TempConsignmentInfoProductInfo;
use Client\Tests\Traits\AuthenticationTrait as ClientAuthenticationTrait;

class TempConsignmentInfoProductInfoTest extends AbstractTestCase
{

    use AdminAuthenticationTrait, ClientAuthenticationTrait {
        AdminAuthenticationTrait::authenticate insteadof ClientAuthenticationTrait;
        ClientAuthenticationTrait::authenticate insteadof AdminAuthenticationTrait;
        AdminAuthenticationTrait::authenticate as adminAuthenticate;
        ClientAuthenticationTrait::authenticate as clientAuthenticate;
    }

    public function testNormalizeProductIdString()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => 1,
            'status'    => Product::STATUS_ACTIVE
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->normalizeProductIdString(
            $product->getId() . '+' . $product->getId());
        $this->assertEquals($product->getId() . '*2', $result);
    }

    public function testParseProductIdStringWithPlusSameProduct()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => 1,
            'status'    => Product::STATUS_ACTIVE
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->parseProductIdString(
            $product->getId() . '+' . $product->getId());
        $this->assertEquals(2, $result[$product->getId()]);
    }

    /*
    public function testGetProductIdByReferenceNoReferenceType()
    {
        $this->clientAuthenticate(true);

        $mockedTempConsignmentInfoProductInfoUtility =
            $this->getMock(TempConsignmentInfoProductInfo::class, ['__getCache']);
        $mockedTempConsignmentInfoProductInfoUtility->expects($this->any())->method('__getCache')->willReturn(null);
        $mockedTempConsignmentInfoProductInfoUtility->setServiceLocator($this->getApplicationServiceLocator());

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => 1,
            'status'    => Product::STATUS_ACTIVE
        ]);

        $result = $mockedTempConsignmentInfoProductInfoUtility->parseProductIdString(
            '1-' . $product->getId() . '*1');
        $this->assertEquals(1, $result[$product->getId()]);
    } */


    public function testGetProductIdByReferenceWithClientOwnedProductId()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => 1,
            'status'    => Product::STATUS_ACTIVE
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->getProductIdByReference(
            $product->getId(),
            TempConsignmentInfoProductInfo::REFERENCE_ID);
        $this->assertEquals($product->getId(), $result);
    }

    public function testGetProductIdByReferenceWithSharedProductId()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ClientTest = new ClientTest();
        $ClientTest->setServiceLocator($this->getApplicationServiceLocator());
        $client = $ClientTest->getModelInstance([
            'id' => 2
        ]);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => $client->getId(),
            'status'    => Product::STATUS_ACTIVE
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->getProductIdByReference(
            $client->getId() . TempConsignmentInfoProductInfo::SHARED_PRODUCT_SIGN . $product->getId(),
            TempConsignmentInfoProductInfo::REFERENCE_ID);
        $this->assertEquals($product->getId(), $result);
    }

    public function testGetProductIdByReferenceWithDirectionalSharedProductId()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ClientTest = new ClientTest();
        $ClientTest->setServiceLocator($this->getApplicationServiceLocator());
        $client = $ClientTest->getModelInstance([
            'id' => 2
        ]);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id' => $client->getId(),
            'status'    => Product::STATUS_ACTIVE
        ]);

        $DirectionalSharedProductTest = new DirectionalSharedProductTest();
        $DirectionalSharedProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $DirectionalSharedProductTest->getModelInstance([
            'product_id' => $product->getId(),
            'client_id'  => 1,
            'company_id' => 1,
            'quantity'   => 10
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->getProductIdByReference(
            $client->getId() . TempConsignmentInfoProductInfo::SHARED_PRODUCT_SIGN . $product->getId() .
            TempConsignmentInfoProductInfo::SHARED_PRODUCT_SIGN .
            TempConsignmentInfoProductInfo::DIRECTIONAL_SHARED_PRODUCT_SIGN,
            TempConsignmentInfoProductInfo::REFERENCE_ID);

        $this->assertEquals(TempConsignmentInfoProductInfo::DIRECTIONAL_SHARED_PRODUCT_SIGN . $product->getId(),
            $result);
    }

    public function testGetProductIdByReferenceWithSharedProductSecondarySKU()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $ClientTest = new ClientTest();
        $ClientTest->setServiceLocator($this->getApplicationServiceLocator());
        $client = $ClientTest->getModelInstance([
            'id' => 1
        ]);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $product = $ProductTest->getModelInstance([
            'client_id'  => $client->getId(),
            'status'     => Product::STATUS_ACTIVE,
            'client_ref' => 'TEST'
        ]);

        $productSku              = md5(time());
        $ProductSecondarySkuTest = new ProductSecondarySkuTest();
        $ProductSecondarySkuTest->setServiceLocator($this->getApplicationServiceLocator());
        $ProductSecondarySkuTest->getModelInstance([
            'product_id' => $product->getId(),
            'client_id'  => $client->getId(),
            'sku'        => $productSku
        ]);

        $result = $TempConsignmentInfoProductInfoUtility->getProductIdByReference(
            $productSku,
            TempConsignmentInfoProductInfo::REFERENCE_CLIENT_REF);
        $this->assertEquals($product->getId(), $result);
    }

    public function testGetProductIdByReferenceNotFind()
    {
        $this->clientAuthenticate(true);
        $TempConsignmentInfoProductInfoUtility =
            $this->getApplicationServiceLocator()->get(TempConsignmentInfoProductInfo::class);

        $testId = uniqid();
        $result = $TempConsignmentInfoProductInfoUtility->getProductIdByReference(
            $testId, TempConsignmentInfoProductInfo::REFERENCE_ID);
        $this->assertEquals($testId, $result);
    }
}

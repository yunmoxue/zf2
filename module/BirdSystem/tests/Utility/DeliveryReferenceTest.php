<?php


namespace BirdSystem\Tests\Utility;

use Admin\Db\TableGateway\LaposteBarcode;
use Admin\Tests\Traits\AuthenticationTrait as AdminAuthenticationTrait;
use Admin\Db\TableGateway\RoyalmailBarcode;
use BirdSystem\Service\Fedex\ClientManager as FedexClientManager;
use BirdSystem\Service\Stamps\ClientManager as StampsClientManager;
use BirdSystem\Service\UPS\ClientManager as UpsClientManager;
use BirdSystem\Service\UPSFreight\ClientManager as UpsFreightClientManager;
use BirdSystem\Service\GLS\ClientManager as GlsClientManager;
use BirdSystem\Service\Parcelforce\ClientManager as ParcelforceClientManager;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Tests\Db\TableGateway\ConsignmentTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryPackageSizeTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceCountryTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceTest;
use BirdSystem\Utility\DeliveryReference;
use BirdSystem\Utility\Fly;
use BirdSystem\Utility\Utility;


class DeliveryReferenceTest extends AbstractTestCase
{
    use AdminAuthenticationTrait;

    protected $DeliveryService, $DeliveryPackageSize, $DeliveryServiceCountry, $Consignment;

    protected static $config = [
        'royalmailTracked-enabled' => 0,
        'laposte-enabled'          => 0,
        'auspost-enabled'          => 0,
        'fedex-enabled'            => 0,
        'stamps-enabled'           => 0,
        'gls-enabled'              => 0,
        'ups-enabled'              => 0,
        'parcel_force-enabled'     => 0
    ];

    function setUp()
    {
        parent::setUp();
        $this->authenticate();

        return $this;
    }

    protected function setUpMockedCompanyConfig($config)
    {
        $config              = array_merge(self::$config, $config);
        $mockedCompanyConfig =
            $this->getMock(CompanyConfig::class,
                ['getByCompanyId'],
                [],
                '',
                false);
        $mockedCompanyConfig->expects($this->any())->method('getByCompanyId')->willReturn($config);
        /* @var CompanyConfig $mockedCompanyConfig */
        $mockedCompanyConfig->setServiceLocator($this->getApplicationServiceLocator());
        $serviceManager = $this->getApplicationServiceLocator();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService(CompanyConfig::class, $mockedCompanyConfig);
    }

    public function setUpConsignmentTestData($specialType, $serviceCode = '')
    {
        $DeliveryServiceTest        = new DeliveryServiceTest();
        $DeliveryPackageSizeTest    = new DeliveryPackageSizeTest();
        $DeliveryServiceCountryTest = new DeliveryServiceCountryTest();
        $ConsignmentTest            = new ConsignmentTest();
        $DeliveryServiceCountryTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryPackageSizeTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $UserInfo              = $DeliveryServiceTest->getTableGateway()->getUserInfo();
        $DeliveryServiceTG     = $DeliveryServiceTest->getTableGateway();
        $DeliveryPackageSizeTG = $DeliveryPackageSizeTest->getTableGateway();
        $faker                 = $DeliveryServiceTest->getFaker();;
        $this->DeliveryService        = $DeliveryServiceTest->getModelInstance(
            [
                'code'          => $serviceCode,
                'external_code' => 'USPS FCPI-2',
                'special_type'  => $specialType,
                'is_tracking'   => 1,
                'update_time'   => date('Y-m-d H:i:s'),
                'company_id'    => $UserInfo->getCompanyId(),
                'status'        => $DeliveryServiceTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryPackageSize    = $DeliveryPackageSizeTest->getModelInstance(
            [
                'code'       => 'Package',
                'company_id' => $UserInfo->getCompanyId(),
                'status'     => $DeliveryPackageSizeTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryServiceCountry = $DeliveryServiceCountryTest->getModelInstance(
            [
                'delivery_service_id' => $this->DeliveryService->getId(),
            ], true);

        $ConsignmentTG = $ConsignmentTest->getTableGateway();
        $time1         = $faker->dateTimeBetween('-1days')->format('Y-m-d H:i:s');

        return $ConsignmentTest->getModelInstance(
            [
                'delivery_service_id'               => $this->DeliveryService->getId(),
                'delivery_service_id_internal'      => $this->DeliveryService->getId(),
                'delivery_package_size_id'          => $this->DeliveryPackageSize->getId(),
                'delivery_package_size_id_internal' => $this->DeliveryPackageSize->getId(),
                'company_id'                        => $UserInfo->getCompanyId(),
                'country_iso'                       => $this->DeliveryServiceCountry->getCountryIso(),
                'update_time'                       => $time1,
                'create_time'                       => $time1,
                'post_code'                         => '2250',
                'status'                            => $ConsignmentTG::STATUS_PROCESSING,

            ], true);

    }

    protected function setUpMockedClientManager($className, $return, $method = 'generateShippingLabel')
    {
        $mockedClientManager =
            $this->getMock($className,
                [$method],
                array());

        $mockedClientManager->expects($this->any())
            ->method($method)->willReturn($return);
        /* @var FedexClientManager $mockedClientManager */
        $mockedClientManager->setServiceLocator($this->getApplicationServiceLocator());
        $mockedClientManager->initialize();
        $serviceManager = $this->getApplicationServiceLocator();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService($className, $mockedClientManager);
    }


    protected function setUpMockedRoyalmailBarcode($className, $return)
    {
        $mockedClientManager =
            $this->getMock($className,
                ['getNextAvailableBarcodeForConsignment'],
                [$this->getApplicationServiceLocator()->get('db')]);

        $mockedClientManager->expects($this->any())
            ->method('getNextAvailableBarcodeForConsignment')->willReturn($return);
        /* @var FedexClientManager $mockedClientManager */
        $mockedClientManager->setServiceLocator($this->getApplicationServiceLocator());
        $mockedClientManager->initialize();
        $serviceManager = $this->getApplicationServiceLocator();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService($className, $mockedClientManager);
    }

    public function testRoalmailConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['royalmailTracked-enabled' => 1]);
        $Consignment = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_RM2);
        $this->setUpMockedRoyalmailBarcode(RoyalmailBarcode::class, new Fly([
            'getBarcode' => function () {
                return '123456789';
            }
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), '123456789', 'assert tracking no.');
    }


    public function testLaposteConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['laposte-enabled' => 1]);
        $Consignment = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_LAPOSTE);
        $this->setUpMockedRoyalmailBarcode(LaposteBarcode::class, new Fly([
            'getBarcode' => function () {
                return '123456789';
            }
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), '123456789', 'assert tracking no.');
    }


    public function testAuspostConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig([
            'auspost-enabled'                  => 1,
            'auspost-applicationId'            => '91',
            'auspost-dataMatrixApplicationId1' => '420',
            'auspost-dataMatrixApplicationId2' => '92',
            'auspost-dataMatrixApplicationId3' => '8008',
            'auspost-globalTradeItemNumber'    => '0199312650999998',
            'auspost-sourceReference'          => 'SHW',
            'auspost-trackingRangeEndId'       => 7999999,
            'auspost-trackingRangeStartId'     => 7000001
        ]);
        $Consignment              = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_EPARCEL);
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals(strlen($Consignment->getDeliveryReference()), '21',
            'eparcel delivery reference should be 21 in length');
    }


    public function testFedexConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['fedex-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_FEDEX);
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(FedexClientManager::class, Utility::arrayToObject([
            'CompletedShipmentDetail' => [
                'CompletedPackageDetails' => [
                    'TrackingIds' => [
                        'TrackingNumber'
                        => $trackingNumber
                    ]
                ]
            ]
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }


    public function testFedexConsignment2()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['fedex-enabled' => 1]);
        $Consignment                                                           =
            $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_FEDEX);
        $trackingNumber                                                        = $this->getFaker()->randomNumber(6);
        $return                                                                = Utility::arrayToObject([
            'CompletedShipmentDetail' => [
                'CompletedPackageDetails' => [
                ]
            ]
        ]);
        $return->CompletedShipmentDetail->CompletedPackageDetails->TrackingIds = [
            Utility::arrayToObject([
                'TrackingNumber' => $trackingNumber,
                'TrackingIdType' => 'USPS'
            ])
        ];
        $this->setUpMockedClientManager(FedexClientManager::class, $return);
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }


    public function testStampsConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['stamps-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_STAMPS);
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $url            = $this->getFaker()->url;
        $this->setUpMockedClientManager(StampsClientManager::class, Utility::arrayToObject([
            'TrackingNumber' => $trackingNumber,
            'URL'            => $url,
            'StampsTxID'     => $this->getFaker()->uuid
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
        $this->assertEquals($Consignment->getLabelUrl(), $url, 'assert tracking no.');
    }

    public function testUpsConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['ups-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_UPS);
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(UpsClientManager::class, Utility::arrayToObject([
            'PackageResults' => [
                'TrackingNumber' => $trackingNumber
            ]
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }


    public function testUpsFreightConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['ups-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_UPSFREIGHT);
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(UpsFreightClientManager::class, Utility::arrayToObject([
            'ShipmentResults' => [
                'ShipmentNumber' => $trackingNumber
            ]
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }

    public function testGlsConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();
        $this->setUpMockedCompanyConfig(['gls-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_GLS);
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(GlsClientManager::class, [
            'T8913' => $trackingNumber
        ]);
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);
        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }

    public function testParcelforceConsignment()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();

        $this->setUpMockedCompanyConfig(['parcel_force-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_PARCELFORCE, 'S09');
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(ParcelforceClientManager::class, Utility::arrayToObject([
            'CompletedShipmentInfo' => [
                'CompletedShipments' => [
                    'CompletedShipment' => [
                        'ShipmentNumber' =>
                            $trackingNumber
                    ]
                ]
            ]
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);

        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }


    public function testParcelforceConsignment2()
    {
        $ConsignmentTest     = new ConsignmentTest();
        $DeliveryServiceTest = new DeliveryServiceTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $DeliveryServiceTG = $DeliveryServiceTest->getTableGateway();

        $this->setUpMockedCompanyConfig(['parcel_force-enabled' => 1]);
        $Consignment    = $this->setUpConsignmentTestData($DeliveryServiceTG::SPECIAL_TYPE_PARCELFORCE, 'GBD');
        $trackingNumber = $this->getFaker()->randomNumber(6);
        $this->setUpMockedClientManager(ParcelforceClientManager::class, Utility::arrayToObject([
            'CompletedShipmentInfo' => [
                'CompletedShipments' => [
                    'CompletedShipment' => [
                        'ShipmentNumber' =>
                            $trackingNumber
                    ]
                ]
            ]
        ]));
        $onlyProceedConsignmentId = null;
        $DeliveryReference        = $this->getApplicationServiceLocator()->get(DeliveryReference::class);
        $DeliveryReference->processConsignment([$Consignment->getId()], $onlyProceedConsignmentId);

        $this->assertEquals($Consignment->getId(), $onlyProceedConsignmentId);
        $Consignment = $ConsignmentTG->get($Consignment->getId());
        $this->assertEquals($Consignment->getDeliveryReference(), $trackingNumber, 'assert tracking no.');
    }
}
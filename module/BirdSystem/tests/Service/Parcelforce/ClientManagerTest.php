<?php
namespace BirdSystem\tests\Service\Parcelforce;

/**
 * Created by PhpStorm.
 * User: shawn
 * Date: 29/01/16
 * Time: 11:51
 */

use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Service\Parcelforce\ClientManager;
use BirdSystem\Service\Parcelforce\Soap\Client;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\Controller\Traits\AuthenticationTrait;
use BirdSystem\Tests\Db\TableGateway\CompanyTest;
use BirdSystem\Tests\Db\TableGateway\ConsignmentTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryPackageSizeTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceCountryTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceTest;
use BirdSystem\Utility\Utility;

class ClientManagerTest extends AbstractTestCase
{
    use AuthenticationTrait;

    protected static $config = [
        'parcel_force-enabled'           => 1,
        'parcel_force-uri'               => ClientManager::WEB_SERVICE_URI,
        'parcel_force-username'          => 'test',
        'parcel_force-password'          => 'passoword123',
        'parcel_force-contractNumber'    => 'anything',
        'parcel_force-departmentId'      => '1234',
        'parcel_force-logfile'           => '',
        'parcel_force-emailNotification' => '0',
        'parcel_force-smsNotification'   => '0',
    ];

    protected static $returnConfig = [
        'enabled'           => 1,
        'uri'               => ClientManager::WEB_SERVICE_URI,
        'username'          => 'test',
        'password'          => 'passoword123',
        'contractNumber'    => 'anything',
        'departmentId'      => '1234',
        'logfile'           => '',
        'emailNotification' => '0',
        'smsNotification'   => '0',
    ];

    /**
     * @var \BirdSystem\Db\Model\Consignment $Consignment
     */
    protected $DeliveryService, $DeliveryPackageSize, $DeliveryServiceCountry, $Consignment;

    public function setUpConsignmentTestData()
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
                'code'          => 'US-FCI',
                'addon'         => 'SC-A-HP',
                'external_code' => 'USPS FCPI-2',
                'special_type'  => $DeliveryServiceTG::SPECIAL_TYPE_PARCELFORCE,
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

        $ConsignmentTG     = $ConsignmentTest->getTableGateway();
        $time1             = $faker->dateTimeBetween('-1days')->format('Y-m-d H:i:s');
        $Consignment       = $ConsignmentTest->getModelInstance(
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
                'status'                            => $ConsignmentTG::STATUS_FINISHED,

            ], true);
        $this->Consignment = $ConsignmentTG->fetchRow(
            $ConsignmentTG->getSql()
                ->select()
                ->join('delivery_service', 'delivery_service.id = consignment.delivery_service_id_internal',
                    [
                        'delivery_service_external_code'    => 'external_code',
                        'delivery_service_external_code1'   => 'external_code1',
                        'delivery_service_external_code2'   => 'external_code2',
                        'delivery_service_is_international' => 'is_international',
                        'delivery_service_is_signature'     => 'is_signature',
                        'delivery_service_code'             => 'code',
                    ])
                ->join('delivery_package_size',
                    'delivery_package_size.id = consignment.delivery_package_size_id_internal',
                    [
                        'delivery_package_size_code' => 'code',
                    ])
                ->where(['consignment.id = ?' => $Consignment->getId()]));
    }

    public function clearConsignmentTestData()
    {
        $DeliveryServiceCountryTest = new DeliveryServiceCountryTest();
        $DeliveryServiceTest        = new DeliveryServiceTest();
        $DeliveryPackageSizeTest    = new DeliveryPackageSizeTest();
        $DeliveryServiceCountryTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryPackageSizeTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceCountryTest->getTableGateway()->destroy($this->DeliveryServiceCountry);
        $DeliveryServiceTest->getTableGateway()->destroy($this->DeliveryService);
        $DeliveryPackageSizeTest->getTableGateway()->destroy($this->DeliveryPackageSize);
    }

    /**
     * @var array $fromAddress
     */
    protected $fromAddress;

    public function setUpFromAddressTestData()
    {
        $CompanyTest = new CompanyTest();
        $CompanyTest->setServiceLocator($this->getApplicationServiceLocator());
        $Company                       =
            $CompanyTest->getTableGateway()->get($CompanyTest->getTableGateway()->getUserInfo()->getCompanyId());
        $this->fromAddress             = [];
        $this->fromAddress['FullName'] = $Company->getReturnName();
        $this->fromAddress['Address1'] = $Company->getReturnAddressLine1();
        $this->fromAddress['Address2'] = $Company->getReturnAddressLine2();
        $this->fromAddress['Address3'] = $Company->getReturnAddressLine3();
        $this->fromAddress['City']     = $Company->getReturnCity();
        $this->fromAddress['State']    = $Company->getReturnCounty();
        $this->fromAddress['ZIPCode']  = $Company->getReturnPostCode();
        $this->fromAddress['Country']  = $Company->getReturnCountryIso();
    }

    function setUp()
    {
        parent::setUp();
        $this->authenticate();
        $this->setUpCompany();
        $this->setUpMockedCompanyConfig();

        return $this;
    }

    protected function setUpCompany()
    {
        $CompanyTest = new CompanyTest();
        $CompanyTest->setServiceLocator($this->getApplicationServiceLocator());
        $Company = $CompanyTest->initModelInstance(
            [
                'id'                 => $CompanyTest->getTableGateway()->getUserInfo()->getCompanyId(),
                'country_iso'        => 'GB',
                'return_country_iso' => 'GB',
            ]);
        $CompanyTest->getTableGateway()->save($Company);
        $Cache = $this->getApplicationServiceLocator()->get('cache');
        $Cache->removeItem(ClientManager::getSenderAddressCacheKey());
    }

    protected function setUpMockedCompanyConfig($config = null)
    {
        if (!is_array($config)) {
            $config = self::$config;
        }
        $mockedCompanyConfig =
            $this->getMock(CompanyConfig::class,
                ['getByCompanyId'],
                [],
                '',
                false);
        $mockedCompanyConfig->expects($this->any())->method('getByCompanyId')->willReturn($config);
        $mockedCompanyConfig->setServiceLocator($this->getApplicationServiceLocator());
        $serviceManager = $this->getApplicationServiceLocator();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService(CompanyConfig::class, $mockedCompanyConfig);
    }

    public function setUpMockedClientManager()
    {
        $mockedClient =
            $this->getMock(Client::class,
                ['parentSoapCall'],
                [
                    __DIR__ . '/' . ClientManager::SERVICE_WSDL_URI,
                    ['location' => ClientManager::WEB_SERVICE_URI],
                ]);
        $args         = func_get_args();
        foreach ($args as $index => $response) {
            $mockedClient->expects($this->at($index))->method('parentSoapCall')->willReturn(Utility::arrayToObject($response));
        }
        $mockedClient->setServiceLocator($this->getApplicationServiceLocator());
        $mockedClient->initialize();
        $mockedClientManager =
            $this->getMock(ClientManager::class,
                ['getSoapClient'],
                []);
        $mockedClientManager->expects($this->any())->method('getSoapClient')->willReturn($mockedClient);
        $mockedClientManager->setServiceLocator($this->getApplicationServiceLocator());
        $mockedClientManager->initialize();
        $serviceManager = $this->getApplicationServiceLocator();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService(ClientManager::class, $mockedClientManager);
    }

    public function testBasic()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('parcelforce-service');

        $this->assertInstanceOf(ClientManager::class, $service);
    }

    public function testConfig()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('parcelforce-service');

        $this->assertEquals(asort(array_keys(self::$returnConfig)), asort(array_keys(Utility::objectToArray
        ($service->getConfiguration()))));
    }

    public function testGetSenderAddress()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('fedex-service');
        $address = $service->getSenderAddress();
        $this->assertTrue(is_array($address), 'ClientManager.getSenderAddress should return array');
    }

    public function testGenerateShippingLabel()
    {
        $this->setUpFromAddressTestData();
        $this->setUpConsignmentTestData();
        /**
         * @var ClientManager $service
         */
        $responseShipping =
            ['CompletedShipmentInfo' => ['CompletedShipments' => ['CompletedShipment' => ['ShipmentNumber' => $this->getFaker()->randomDigit]]]];
        $this->setUpMockedClientManager($responseShipping);
        $service  = $this->getApplicationServiceLocator()->get('parcelforce-service');
        $response = $service->generateShippingLabel($this->Consignment, []);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }

    public function testcreateManifest()
    {
        /**
         * @var ClientManager $service
         */
        $response = [];
        $this->setUpMockedClientManager($response);
        $service  = $this->getApplicationServiceLocator()->get('parcelforce-service');
        $response = $service->createManifest();
        $this->assertNotNull($response);
    }

    public function testCancelShipment()
    {
        $this->setUpFromAddressTestData();
        $this->setUpConsignmentTestData();
        $responseAddress = [];
        $this->setUpMockedClientManager($responseAddress);
        /*
         * @var ClientManager $service
         */
        $service  = $this->getApplicationServiceLocator()->get('parcelforce-service');
        $response = $service->cancelShipment($this->Consignment);
        $this->assertNotNull($response);
    }

    public function testPrintLabel()
    {
        /**
         * @var ClientManager $service
         */
        $response = [];
        $this->setUpMockedClientManager($response);
        $service  = $this->getApplicationServiceLocator()->get('parcelforce-service');
        $response = $service->printLabel(1);
        $this->assertNotNull($response);
    }

    public function testPrintManifest()
    {
        /**
         * @var ClientManager $service
         */
        $response = [];
        $this->setUpMockedClientManager($response);
        $service  = $this->getApplicationServiceLocator()->get('parcelforce-service');
        $response = $service->printManifest(1);
        $this->assertNotNull($response);
    }

}
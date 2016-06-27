<?php
namespace BirdSystem\tests\Service\Stamps;

/**
 * Created by PhpStorm.
 * User: shawn
 * Date: 22/01/16
 * Time: 14:51
 */

use BirdSystem\Controller\Exception\AppException;
use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Service\Stamps\ClientManager;
use BirdSystem\Service\Stamps\Soap\Client;
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
        'stamps-enabled'           => 1,
        'stamps-wsdl'              => 'https://swsim.stamps.com/swsim/swsimv42.asmx',
        'stamps-username'          => 'lilylichina',
        'stamps-password'          => 'Birdsystem2014',
        'stamps-costCodeId'        => '',
        'stamps-integrationId'     => '05f9ce88-5221-4bbb-8731-fef96fcd7235',
        'stamps-logfile'           => '',
        'stamps-printInstructions' => '1',
        'stamps-sampleOnly'        => '1',
    ];

    protected static $returnConfig = [
        'enabled'           => 1,
        'wsdl'              => 'https://swsim.stamps.com/swsim/swsimv42.asmx',
        'username'          => 'lilylichina',
        'password'          => 'Birdsystem2014',
        'costCodeId'        => '',
        'integrationId'     => '05f9ce88-5221-4bbb-8731-fef96fcd7235',
        'logfile'           => '',
        'printInstructions' => '1',
        'sampleOnly'        => '1',
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
                'special_type'  => $DeliveryServiceTG::SPECIAL_TYPE_STAMPS,
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
                'country_iso'         => 'US',
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
                'country_iso'                       => 'US',
                'update_time'                       => $time1,
                'create_time'                       => $time1,
                'post_code'                         => '22150',
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
                        'total_delivery_cost'               => new \Zend\Db\Sql\Expression('2.6'),
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
                'country_iso'        => 'US',
                'return_country_iso' => 'US',
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
                    __DIR__ . '/swsimv42.wsdl',
                    ['location' => 'https://swsim.testing.stamps.com/swsim/swsimv35.asmx'],
                ]);
//        $mockedClient->expects($this->any())->method('parentSoapCall')->willReturn(Utility::arrayToObject($response1));
        $mockedClient->expects($this->at(0))->method('parentSoapCall')->willReturn(Utility::arrayToObject(['Authenticator' => $this->getFaker()->uuid]));
        $args = func_get_args();
        foreach ($args as $index => $response) {
            $mockedClient->expects($this->at($index +
                                             1))->method('parentSoapCall')->willReturn(Utility::arrayToObject($response));
        }
        //$mockedClient->expects($this->at(2))->method('parentSoapCall')->willReturn(Utility::arrayToObject($response2));
        //$mockedClient->expects($this->at(3))->method('parentSoapCall')->willReturn(Utility::arrayToObject($response3));
        //$mockedClient->setServiceLocator($this->getApplicationServiceLocator());
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
        $service = $this->getApplicationServiceLocator()->get('stamps-service');

        $this->assertInstanceOf(ClientManager::class, $service);
    }

    public function testConfig()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('stamps-service');

        $this->assertEquals(self::$returnConfig, Utility::objectToArray($service->getConfiguration()));
    }

    public function testGetSenderAddress()
    {
        /**
         * @var ClientManager $service
         */
        $this->setUpFromAddressTestData();
        $response = [
            'AddressMatch' => true,
            'Address'      => $this->fromAddress,
        ];
        $this->setUpMockedClientManager($response);
        $service = $this->getApplicationServiceLocator()->get('stamps-service');
        $address = $service->getSenderAddress();
        $this->assertTrue(is_array($address), 'ClientManager.getSenderAddress should return array');
        $this->assertEquals($this->fromAddress, $address);
    }

    public function testGenerateShippingLabel()
    {
        $this->setUpFromAddressTestData();
        $this->setUpConsignmentTestData();
        /**
         * @var ClientManager $service
         */
        $responseFrom     = [
            'AddressMatch' => true,
            'Address'      => $this->fromAddress,
        ];
        $responseTo       = [
            'AddressMatch' => true,
            'Address'      => [
                'State' => 'Washington',
            ],
        ];
        $responseRate     = [
            'Rates' => [
                'Rate' => [
                    'FromZIPCode'  => '20849',
                    'ToZIPCode'    => '77020',
                    'Amount'       => '2.6',
                    'MaxAmount'    => '2.60',
                    'ServiceType'  => 'US-FC',
                    'DeliverDays'  => '3',
                    'WeightOz'     => '5.82',
                    'PackageType'  => 'Large Package',
                    'ShipDate'     => '2016-01-26',
                    'DeliveryDate' => '2016-01-29',
                    'DimWeighting' => 'N',
                ],
            ],
        ];
        $responseShipping = ['URL' => 'www.anything.com'];
        $this->setUpMockedClientManager($responseFrom, $responseTo, $responseRate, $responseShipping);
        $service  = $this->getApplicationServiceLocator()->get('stamps-service');
        $response = $service->generateShippingLabel($this->Consignment, []);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }

    public function testPurchasePostage()
    {
        /**
         * @var ClientManager $service
         */
        $responseGetAccountInfo  =
            ['AccountInfo' => ['PostageBalance' => ['ControlTotal' => $this->getFaker()->randomDigit]]];
        $responsePurchasePostage = ['URL' => 'www.anything.com'];
        $this->setUpMockedClientManager($responseGetAccountInfo, $responsePurchasePostage);
        $service  = $this->getApplicationServiceLocator()->get('stamps-service');
        $response = $service->purchasePostage($this->getFaker()->randomDigit);
        $this->assertNotNull($response);
    }

    public function testCheckAddress()
    {
        $this->setUpFromAddressTestData();
        $this->setUpConsignmentTestData();
        $responseAddress = [
            'AddressMatch' => true,
            'Address'      => $this->Consignment,
        ];
        $this->setUpMockedClientManager($responseAddress);
        /*
         * @var ClientManager $service
         */
        $service  = $this->getApplicationServiceLocator()->get('stamps-service');
        $response = $service->checkAddress($this->Consignment);
    }

    public function testCheckAddress_returnException()
    {
        $this->setExpectedException(AppException::class);
        $this->setUpFromAddressTestData();
        $this->setUpConsignmentTestData();
        $responseAddress = [
            'AddressMatch' => true,
            'Address'      => ['ZIPCode' => '0_' . $this->Consignment->getPostcode()],
        ];
        $this->setUpMockedClientManager($responseAddress);
        /*
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('stamps-service');
        $service->checkAddress($this->Consignment);
    }
}
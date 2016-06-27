<?php
namespace BirdSystem\tests\Service\UPSFreight;

/**
 * Created by PhpStorm.
 * User: shawn
 * Date: 28/01/16
 * Time: 14:51
 */

use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Service\UPSFreight\ClientManager;
use BirdSystem\Service\UPSFreight\Soap\Client;
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
        'ups-enabled'        => 1,
        'ups-accessKey'      => 'CD0880ECF7BE3CF5',
        'ups-userId'         => 'lilylichina',
        'ups-password'       => 'Fedex2014',
        'ups-accountNumber'  => 'W787E2',
        'ups-useIntegration' => 1,
        'ups-logfile'        => ''
    ];

    protected static $returnConfig = [
        'enabled'        => 1,
        'accessKey'      => 'CD0880ECF7BE3CF5',
        'userId'         => 'lilylichina',
        'password'       => 'Fedex2014',
        'accountNumber'  => 'W787E2',
        'useIntegration' => 1,
        'logfile'        => ''
    ];


    /**
     * @var Consignment
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
                'code'          => '308',
                'external_code' => 'USPS FCPI-2',
                'special_type'  => $DeliveryServiceTG::SPECIAL_TYPE_UPSFREIGHT,
                'update_time'   => date('Y-m-d H:i:s'),
                'company_id'    => $UserInfo->getCompanyId(),
                'status'        => $DeliveryServiceTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryPackageSize    = $DeliveryPackageSizeTest->getModelInstance(
            [
                'code'       => 'PLT',
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
                'country_iso'                       => $this->DeliveryServiceCountry->getCountryIso(),
                'update_time'                       => $time1,
                'create_time'                       => $time1,
                'address_line1'                     => '401 south main street',
                'address_line2'                     => '',
                'address_line3'                     => '',
                'post_code'                         => '21801',
                'city'                              => 'Salisbury',
                'county'                            => 'Maryland',
                'telephone'                         => '4437352110',
                'status'                            => $ConsignmentTG::STATUS_FINISHED
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
                [__DIR__ . '/wsdl/FreightShip.wsdl', []]);
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
        $serviceManager = $this->getApplicationServiceLocator();
        $mockedClientManager->setServiceLocator($serviceManager);
        $mockedClientManager->initialize();
        $serviceManager->setAllowOverride(true);
        $serviceManager->setService(ClientManager::class, $mockedClientManager);
    }

    public function testBasic()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('ups-freight-service');

        $this->assertInstanceOf(ClientManager::class, $service);
    }

    public function testConfig()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('ups-freight-service');

        $this->assertEquals(self::$returnConfig, Utility::objectToArray($service->getConfiguration()));
    }

    public function testGetSenderAddress()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('ups-freight-service');
        $address = $service->getShipFromAddress();
        $this->assertTrue(is_array($address), 'ClientManager.getSenderAddress should return array');
    }

    public function testGenerateShippingLabel()
    {
        $this->setUpConsignmentTestData();
        /**
         * @var ClientManager $service
         */
        $responseShipping = [
            'ShipmentResults' => [
                'Documents' => [
                    'Image' => [
                        'Type'         => 30,
                        'GraphicImage' => base64_encode($this->getFaker()->uuid),
                        'Format'       => [
                            'Code' => 'GIF'
                        ]
                    ]
                ]
            ]
        ];
        $this->setUpMockedClientManager($responseShipping);
        $service  = $this->getApplicationServiceLocator()->get('ups-freight-service');
        $response = $service->generateShippingLabel($this->Consignment,
            [
                [
                    'product_id'     => 1,
                    'product_length' => 10,
                    'product_width'  => 10,
                    'product_height' => 10,
                    'quantity'       => 1
                ]
            ]);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }

}
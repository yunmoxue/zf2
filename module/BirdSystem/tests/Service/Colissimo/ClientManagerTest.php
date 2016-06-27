<?php
namespace BirdSystem\tests\Service\Colissimo;

/**
 * Created by PhpStorm.
 * User: shawn
 * Date: 28/01/16
 * Time: 14:51
 */

use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Service\Colissimo\ClientManager;
use BirdSystem\Service\Colissimo\Soap\Client;
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
        'colissimo-enabled'        => 1,
        'colissimo-uri'            => ClientManager::COLISSIMO_URI,
        'colissimo-contractNumber' => '820643',
        'colissimo-password'       => 'Xkw2015',
        'colissimo-logfile'        => '',
    ];

    protected static $returnConfig = [
        'enabled'        => 1,
        'uri'            => ClientManager::COLISSIMO_URI,
        'contractNumber' => '820643',
        'password'       => 'Xkw2015',
        'logfile'        => '',
    ];

    /**
     * @var Consignment
     */
    protected $DeliveryService,
        $DeliveryPackageSize,
        $DeliveryServiceCountry,
        $Consignment;
    protected $DeliveryService2,
        $DeliveryPackageSize2,
        $DeliveryServiceCountry2,
        $Consignment2;

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
                'code'           => 'COLD',
                'external_code'  => '',
                'external_code1' => '',
                'special_type'   => $DeliveryServiceTG::SPECIAL_TYPE_COLISSIMO,
                'update_time'    => date('Y-m-d H:i:s'),
                'company_id'     => $UserInfo->getCompanyId(),
                'status'         => $DeliveryServiceTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryPackageSize    = $DeliveryPackageSizeTest->getModelInstance(
            [
                'code'       => 'YOUR_PACKAGING',
                'company_id' => $UserInfo->getCompanyId(),
                'status'     => $DeliveryPackageSizeTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryServiceCountry = $DeliveryServiceCountryTest->getModelInstance(
            [
                'country_iso'         => 'FR',
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
                'address_line1'                     => '30 la hourcade',
                'address_line2'                     => '',
                'address_line3'                     => '',
                'county'                            => 'lieu dit',
                'city'                              => 'paris',
                'post_code'                         => '75060',
                'telephone'                         => '4437352110',
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
                        'total_weight'                      => new \Zend\Db\Sql\Expression('1500')
                    ])
                ->join('delivery_package_size',
                    'delivery_package_size.id = consignment.delivery_package_size_id_internal',
                    [
                        'delivery_package_size_code' => 'code',
                    ])
                ->where(['consignment.id = ?' => $Consignment->getId()]));
    }

    public function setUpConsignmentTestData4Internaltional()
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
        $this->DeliveryService2        = $DeliveryServiceTest->getModelInstance(
            [
                'code'             => 'COLI',
                'external_code'    => '',
                'external_code1'   => '',
                'is_international' => 1,
                'special_type'     => $DeliveryServiceTG::SPECIAL_TYPE_COLISSIMO,
                'update_time'      => date('Y-m-d H:i:s'),
                'company_id'       => $UserInfo->getCompanyId(),
                'status'           => $DeliveryServiceTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryPackageSize2    = $DeliveryPackageSizeTest->getModelInstance(
            [
                'code'       => '',
                'company_id' => $UserInfo->getCompanyId(),
                'status'     => $DeliveryPackageSizeTG::STATUS_ACTIVE,
            ], true);
        $this->DeliveryServiceCountry2 = $DeliveryServiceCountryTest->getModelInstance(
            [
                'country_iso'         => 'DE',
                'delivery_service_id' => $this->DeliveryService2->getId(),
            ], true);

        $ConsignmentTG      = $ConsignmentTest->getTableGateway();
        $time1              = $faker->dateTimeBetween('-1days')->format('Y-m-d H:i:s');
        $Consignment2       = $ConsignmentTest->getModelInstance(
            [
                'delivery_service_id'               => $this->DeliveryService2->getId(),
                'delivery_service_id_internal'      => $this->DeliveryService2->getId(),
                'delivery_package_size_id'          => $this->DeliveryPackageSize2->getId(),
                'delivery_package_size_id_internal' => $this->DeliveryPackageSize2->getId(),
                'company_id'                        => $UserInfo->getCompanyId(),
                'country_iso'                       => $this->DeliveryServiceCountry2->getCountryIso(),
                'update_time'                       => $time1,
                'create_time'                       => $time1,
                'address_line1'                     => 'Buchenstr. 4',
                'address_line2'                     => '',
                'address_line3'                     => '',
                'county'                            => 'Nordrhein-Westfalen',
                'city'                              => 'Gladbeck',
                'post_code'                         => '45964',
                'telephone'                         => '0204342535',
                'status'                            => $ConsignmentTG::STATUS_FINISHED,

            ], true);
        $this->Consignment2 = $ConsignmentTG->fetchRow(
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
                        'total_weight'                      => new \Zend\Db\Sql\Expression('1500')
                    ])
                ->join('delivery_package_size',
                    'delivery_package_size.id = consignment.delivery_package_size_id_internal',
                    [
                        'delivery_package_size_code' => 'code',
                    ])
                ->where(['consignment.id = ?' => $Consignment2->getId()]));
    }

    public function clearConsignmentTestData()
    {
        $DeliveryServiceCountryTest = new DeliveryServiceCountryTest();
        $DeliveryServiceTest        = new DeliveryServiceTest();
        $DeliveryPackageSizeTest    = new DeliveryPackageSizeTest();
        $DeliveryServiceCountryTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryServiceTest->setServiceLocator($this->getApplicationServiceLocator());
        $DeliveryPackageSizeTest->setServiceLocator($this->getApplicationServiceLocator());
        if ($this->DeliveryServiceCountry) {
            $DeliveryServiceCountryTest->getTableGateway()->destroy($this->DeliveryServiceCountry);
        }
        if ($this->DeliveryService) {
            $DeliveryServiceTest->getTableGateway()->destroy($this->DeliveryService);
        }
        if ($this->DeliveryPackageSize) {
            $DeliveryPackageSizeTest->getTableGateway()->destroy($this->DeliveryPackageSize);
        }
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
                'id'                   => $CompanyTest->getTableGateway()->getUserInfo()->getCompanyId(),
                'country_iso'          => 'FR',
                'return_address_line1' => '27 RUE des Freres Lumiere',
                'return_address_line2' => '',
                'return_address_line3' => '',
                'return_county'        => 'lieu dit',
                'return_city'          => 'CLERMONT FERRAND',
                'return_postcode'      => '63100',
                'return_country_iso'   => 'FR',
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
                [__DIR__ . '/' . ClientManager::SERVICE_WSDL_URI, ['location' => ClientManager::COLISSIMO_URI]]);
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
        $service = $this->getApplicationServiceLocator()->get('colissimo-service');

        $this->assertInstanceOf(ClientManager::class, $service);
    }

    public function testConfig()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('colissimo-service');

        $this->assertEquals(self::$returnConfig, Utility::objectToArray($service->getConfiguration()));
    }

    public function testGetSenderAddress()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('colissimo-service');
        $address = $service->getSenderAddress();
        $this->assertTrue(is_array($address), 'ClientManager.getSenderAddress should return array');
    }

    public function testGenerateShippingLabel()
    {
        $this->setUpConsignmentTestData();
        /**
         * @var ClientManager $service
         */
        $responseShipping = [
            'return' => [
                'labelResponse' =>
                    [
                        'pdfUrl'       => $this->getFaker()->url,
                        'label'        => $this->getFaker()->uuid,
                        'parcelNumber' => $this->getFaker()->uuid
                    ]
            ]
        ];
        $this->setUpMockedClientManager($responseShipping);
        $service  = $this->getApplicationServiceLocator()->get('colissimo-service');
        $response = $service->generateShippingLabel($this->Consignment,
            [
                [
                    'product_id'     => 100001,
                    'product_length' => 10,
                    'product_width'  => 10,
                    'product_height' => 10,
                    'product_weight' => '1500',
                    'product_price'  => 5.5,
                    'quantity'       => -1
                ],
                [
                    'product_id'     => 100002,
                    'product_length' => 10,
                    'product_width'  => 10,
                    'product_height' => 10,
                    'product_weight' => '1200',
                    'product_price'  => 2.5,
                    'quantity'       => -2
                ]
            ]);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }

    public function testGenerateShippingLabel_International()
    {
        $this->setUpConsignmentTestData4Internaltional();
        /**
         * @var ClientManager $service
         */
        $responseShipping = [
            'return' => [
                'labelResponse' =>
                    [
                        'pdfUrl'              => $this->getFaker()->url,
                        'label'               => $this->getFaker()->uuid,
                        'parcelNumber'        => $this->getFaker()->uuid,
                        'parcelNumberPartner' => $this->getFaker()->uuid
                    ]
            ]
        ];
        $this->setUpMockedClientManager($responseShipping);
        $service  = $this->getApplicationServiceLocator()->get('colissimo-service');
        $response = $service->generateShippingLabel($this->Consignment2,
            [
                [
                    'product_id'     => 100001,
                    'product_length' => 10,
                    'product_width'  => 10,
                    'product_height' => 10,
                    'product_weight' => '1500',
                    'product_price'  => 5.5,
                    'quantity'       => -1
                ],
                [
                    'product_id'     => 100002,
                    'product_length' => 10,
                    'product_width'  => 10,
                    'product_height' => 10,
                    'product_weight' => '1200',
                    'product_price'  => 2.5,
                    'quantity'       => -2
                ]
            ]);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }
}
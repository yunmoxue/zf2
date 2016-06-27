<?php
namespace BirdSystem\tests\Service\GLS;

/**
 * Created by PhpStorm.
 * User: shawn
 * Date: 29/01/16
 * Time: 11:51
 */

use BirdSystem\Db\TableGateway\CompanyConfig;
use BirdSystem\Service\GLS\ClientManager;
use BirdSystem\Service\GLS\Http\Client;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\Controller\Traits\AuthenticationTrait;
use BirdSystem\Tests\Db\TableGateway\CompanyTest;
use BirdSystem\Tests\Db\TableGateway\ConsignmentTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryPackageSizeTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceCountryTest;
use BirdSystem\Tests\Db\TableGateway\DeliveryServiceTest;
use BirdSystem\Utility\Utility;
use Zend\Http\Response;

class ClientManagerTest extends AbstractTestCase
{
    use AuthenticationTrait;

    protected static $config = [
        'gls-enabled'       => 1,
        'gls-uri'           => ClientManager::WEB_SERVICE_URI,
        'gls-contactId'     => 'test123',
        'gls-customerId'    => 'test123',
        'gls-outboundDepot' => 'FR0063',
    ];

    protected static $returnConfig = [
        'enabled'       => 1,
        'uri'           => ClientManager::WEB_SERVICE_URI,
        'contactId'     => 'test123',
        'customerId'    => 'test123',
        'outboundDepot' => 'FR0063',
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
                'special_type'  => $DeliveryServiceTG::SPECIAL_TYPE_GLS,
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
                ['send'],
                [ClientManager::WEB_SERVICE_URI]);
        $args         = func_get_args();
        foreach ($args as $index => $response) {
            $mockedClient->expects($this->at($index))->method('send')->willReturn($response);
        }
        $mockedClient->setServiceLocator($this->getApplicationServiceLocator());
        $mockedClient->initialize();
        $mockedClientManager =
            $this->getMock(ClientManager::class,
                ['getHttpClient'],
                []);
        $mockedClientManager->expects($this->any())->method('getHttpClient')->willReturn($mockedClient);
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
        $service = $this->getApplicationServiceLocator()->get('gls-service');

        $this->assertInstanceOf(ClientManager::class, $service);
    }

    public function testConfig()
    {
        /**
         * @var ClientManager $service
         */
        $service = $this->getApplicationServiceLocator()->get('gls-service');

        $this->assertEquals(self::$returnConfig, Utility::objectToArray($service->getConfiguration()));
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
        $responseShipping = 'HTTP/1.1 200
Content-Type: text/html; charset=UTF-8
Content-Length: 320
\\\\\GLS\\\\\T860:jordi maccio|T863:38 rue du docteur rollet|T330:69100|T864:villeurbanne|T871:0625635537|T859:1601290180020097|T810:Colisio|T820:27 RUE des Freres Lumiere|T821:FR|T822:63100|T823:CLERMONT FERRAND|T8700:FR0063|T8915:2500033857|T8914:2504662001|T8904:001|T8973:001|T8905:001|T8702:001|T8975:0200000092010000FR|T082:UNIQUENO|T090:NOSAVE|T080:V81_8_0007|T520:28012016|T510:bw|T500:FR0063|T103:FR0063|T560:FR01|T8797:IBOXCUS|T540:29.01.2016|T541:13:55|T854:146125*1|T100:FR|CTRA2:FR|T210:|ARTNO:Standard|T530:0.55|T206:BP|ALTZIP:69100|FLOCCODE:FR0069|TOURNO:3376|T320:3376|TOURTYPE:21102|SORT1:1|T310:1|T331:69100|T890:9250|ROUTENO:90844|ROUTE1:LYN|T110:LYN|FLOCNO:126|T101:0069|T105:FR|T300:25096919|T805:|NDI:|T400:005548794434|T8970:A|T8971:A|T8980:AA|T8974:|T8916:005548794434|T8950:Tour|T8951:ZipCode|T8952:Your GLS Track ID|T8953:Product|T8954:Service Code|T8955:Delivery address|T8956:Contact|T8958:Contact|T8957:Customer ID|T8959:Phone|T8960:Note|T8961:Parcel|T8962:Weight|T8965:Contact ID|T8976:0200000092010000FR|T8913:0096CZPV|T8972:0096CZPV|T8902:AFR0063FR0069250003385725046620010096CZPVAA          1LYN337669100  000550010010200000092010000FR    0200000092010000FR    |T8903:A\7Cjordi maccio\7C38 rue du docteur rollet\7Cvilleurbanne\7C\7C146125*1\7C\7C                                                 |T102:FR0069|PRINTINFO:|PRINT1:|RESULT:E000:005548794434|PRINT0:frGLSintermecpf4i.int01|/////GLS/////';
        $this->setUpMockedClientManager(Response::fromString($responseShipping));
        $service  = $this->getApplicationServiceLocator()->get('gls-service');
        $response = $service->generateShippingLabel($this->Consignment, []);
        $this->assertNotNull($response);
        $this->clearConsignmentTestData();
    }

}
<?php


namespace BirdSystem\Tests\Utility;

use Admin\Tests\Traits\AuthenticationTrait as AdminAuthenticationTrait;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\Db\TableGateway\ClientConfigTest;
use BirdSystem\Utility\Measure;
use Client\Db\TableGateway\ClientConfig;
use Client\Tests\Traits\AuthenticationTrait as ClientAuthenticationTrait;

class MeasureTest extends AbstractTestCase
{
    protected $traceError = true;
    use AdminAuthenticationTrait, ClientAuthenticationTrait {
        AdminAuthenticationTrait::authenticate insteadof ClientAuthenticationTrait;
        ClientAuthenticationTrait::authenticate insteadof AdminAuthenticationTrait;
        AdminAuthenticationTrait::authenticate as adminAuthenticate;
        ClientAuthenticationTrait::authenticate as clientAuthenticate;
    }

    private function initClientConfig()
    {
        $ConfigTGT = (new ClientConfigTest())->setServiceLocator($this->getApplicationServiceLocator());
        $UserInfo  = $this->getApplicationServiceLocator()->get('AuthService')->getUserInfo();
        $ConfigTGT->getModelInstance([
            'client_id'   => $UserInfo->getClientId(),
            'company_id'  => $UserInfo->getCompanyId(),
            'config_code' => 'default-volume-unit',
            'value'       => 'CUBIC_MILLIMETER',
        ]);

        $ConfigTGT->getModelInstance([
            'client_id'   => $UserInfo->getClientId(),
            'company_id'  => $UserInfo->getCompanyId(),
            'config_code' => 'default-weight-unit',
            'value'       => 'KILOGRAM',
        ]);

        $ConfigTGT->getModelInstance([
            'client_id'   => $UserInfo->getClientId(),
            'company_id'  => $UserInfo->getCompanyId(),
            'config_code' => 'default-length-unit',
            'value'       => 'METER',
        ]);

        $Cache        = $this->getApplicationServiceLocator()->get('cache');
        $ClientConfig = $this->getApplicationServiceLocator()->get(ClientConfig::class);

        $Cache->removeItem($ClientConfig->getCacheKey($UserInfo->getClientId()));
    }

    public function testInitMeasureClient()
    {
        $this->clientAuthenticate(true);
        $this->assertEquals(true,
            $this->getApplicationServiceLocator()->get('AuthService')->getUserInfo()->isClientUserInfo());
        $this->initClientConfig();

        $service = $this->getApplicationServiceLocator()->get(Measure::class)->init();

        $this->assertInstanceOf(Measure::class, $service);
        $this->assertEquals('METER', $service->userLengthUnit);
        $this->assertEquals('KILOGRAM', $service->userWeightUnit);
        $this->assertEquals('CUBIC_MILLIMETER', $service->userVolumeUnit);
    }

    /**
     * @depends testInitMeasureClient
     */
    public function testHumanReadables()
    {
        $this->clientAuthenticate();
        $service = $this->getApplicationServiceLocator()->get(Measure::class)->init();
        $this->assertEquals('m', $service->getHumanLengthUnit());
        $this->assertEquals('mm^3', $service->getHumanVolumeUnit());
        $this->assertEquals('kg', $service->getHumanWeightUnit());
    }

    /**
     * @depends testInitMeasureClient
     */
    public function testConversions()
    {
        $this->clientAuthenticate();
        $service = $this->getApplicationServiceLocator()->get(Measure::class)->init();

        $this->assertEquals(0.001, $service->convertUserLength(1));
        $this->assertEquals(1, $service->convertUserVolume(1));
        $this->assertEquals(0.001, $service->convertUserWeight(1));

        $this->assertEquals(0.001, $service::convertLength(1, 'METER'));
        $this->assertEquals(0, $service::convertVolume(1, 'CUBIC_METER'));
        $this->assertEquals(1, $service::convertWeight(1, 'GRAM'));

        $this->setExpectedExceptionRegExp('\InvalidArgumentException');
        $service->mapUnitName('TEWSETS');
    }

    /**
     * @depends testInitMeasureClient
     */
    public function testGetConvertList()
    {
        $this->clientAuthenticate();
        $service                    = $this->getApplicationServiceLocator()->get(Measure::class)->init();
        $service->lengthColumnNames = ['length_check'];
        $service->volumeColumnNames = ['volume_check'];
        $service->weightColumnNames = ['weight_check'];

        $testData  = [
            'length_check' => 1,
            'volume_check' => 1,
            'weight_check' => 1,
        ];
        $testDatas = [$testData];

        $expectData         = [
            'length_check' => 0.001,
            'volume_check' => 1,
            'weight_check' => 0.001,
        ];
        $expectDataWithUnit = [
            'length_check' => '0.0010 m',
            'volume_check' => '1.0000 mm^3',
            'weight_check' => '0.0010 kg',
        ];
        $expectDatas        = [$expectData];

        $this->assertEquals($expectData, $service->getConvertList($testData));
        $this->assertEquals($expectDataWithUnit, $service->getConvertList($testData, true));
        $this->assertEquals($expectDatas, $service->getConvertList($testDatas));
    }

    /**
     * @depends testInitMeasureClient
     */
    public function testSaveConvertArray()
    {
        $this->clientAuthenticate();
        $service                    = $this->getApplicationServiceLocator()->get(Measure::class)->init();
        $service->lengthColumnNames = ['length_check'];
        $service->volumeColumnNames = ['volume_check'];
        $service->weightColumnNames = ['weight_check'];

        $expectData = [
            'length_check' => 1,
            'volume_check' => 1,
            'weight_check' => 1,
        ];

        $testData = [
            'length_check' => 0.001,
            'volume_check' => 1,
            'weight_check' => 0.001,
        ];

        $this->assertEquals($expectData, $service->saveConvertArray($testData));
    }

    public function testInitMeasureAdmin()
    {
        $this->adminAuthenticate(true);
        $this->assertEquals(false,
            $this->getApplicationServiceLocator()->get('AuthService')->getUserInfo()->isClientUserInfo());

        $service = $this->getApplicationServiceLocator()->get(Measure::class)->init();

        $this->assertInstanceOf(Measure::class, $service);
        $this->assertNotNull($service->userLengthUnit);
        $this->assertNotNull($service->userWeightUnit);
        $this->assertNotNull($service->userVolumeUnit);
    }
}

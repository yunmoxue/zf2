<?php


namespace BirdSystem\Tests\Utility;

use Admin\Db\TableGateway\MonthlyTurnoverRateFee;
use Admin\Tests\Traits\AuthenticationTrait as AdminAuthenticationTrait;
use BirdSystem\Db\TableGateway\ClientCompany;
use BirdSystem\Db\TableGateway\ClientStatement;
use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\Db\TableGateway\ClientCompanyTest;
use BirdSystem\Tests\Db\TableGateway\ClientStatementTest;
use BirdSystem\Tests\Db\TableGateway\CompanyProductTest;
use BirdSystem\Tests\Db\TableGateway\CompanyTest;
use BirdSystem\Tests\Db\TableGateway\ConsignmentProductTest;
use BirdSystem\Tests\Db\TableGateway\ConsignmentTest;
use BirdSystem\Tests\Db\TableGateway\MonthlyTurnoverRateFeeTest;
use BirdSystem\Tests\Db\TableGateway\MonthlyTurnoverRateStatementTest;
use BirdSystem\Tests\Db\TableGateway\ProductSalesStockMonthlyDataTest;
use BirdSystem\Tests\Db\TableGateway\ProductTest;
use BirdSystem\Tests\Db\TableGateway\WarehouseTest;
use BirdSystem\Tests\Db\TableGateway\ClientTest;
use BirdSystem\Utility\MonthlyStockFee;

class MonthlyStockFeeTest extends AbstractTestCase
{
    protected $traceError = true;
    use AdminAuthenticationTrait;

    public function testUpdateSalesData()
    {
        $this->authenticate(true);
        $date = date('Y-m-01');

        $ConsignmentTest = new ConsignmentTest();
        $ConsignmentTest->setServiceLocator($this->getApplicationServiceLocator());
        $Consignment = $ConsignmentTest->getModelInstance([], true);

        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $ProductOne = $ProductTest->getModelInstance([
            'width_internal'  => 100,
            'length_internal' => 100,
            'depth_internal'  => 100
        ], true);

        $ConsignmentProductTest = new ConsignmentProductTest();
        $ConsignmentProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $ConsignmentProductTest->getModelInstance([
            'consignment_id' => $Consignment->getId(),
            'product_id'     => $ProductOne->getId(),
            'quantity'       => 1
        ], true);
        $ConsignmentTest->getTableGateway()->update([
            'status' => 'FINISHED'
        ], [
            'id' => $Consignment->getId()
        ], true);

        $ProductSalesStockMonthlyDataTest = new ProductSalesStockMonthlyDataTest();
        $ProductSalesStockMonthlyDataTest->setServiceLocator($this->getApplicationServiceLocator());
        $ProductSalesStockMonthlyDataTest->getTableGateway()->delete([]);
        $ProductSalesStockMonthlyDataTest->getModelInstance([
            'company_id' => $Consignment->getCompanyId(),
            'client_id'  => $ProductOne->getClientId(),
            'product_id' => $ProductOne->getId(),
            'date'       => $date
        ], true);

        $MonthlyStockFee = $this->getApplicationServiceLocator()->get(MonthlyStockFee::class);
        $MonthlyStockFee->updateSalesData($date);
    }

    public function testCalculateFee()
    {
        $this->authenticate(true);
        $date                       = date('2016-m-01');
        $MonthlyTurnoverRateFeeTest = new MonthlyTurnoverRateFeeTest();
        $MonthlyTurnoverRateFeeTest->setServiceLocator($this->getApplicationServiceLocator());
        $MonthlyTurnoverRateFeeTest->getTableGateway()->delete([]);
        $MonthlyTurnoverRateFeeTest->getModelInstance([
            'monthly_turnover_rate' => 2,
            'fee'                   => 30,
            'is_use_product_type'   => 0,
            'type'                  => MonthlyTurnoverRateFee::TYPE_CLIENT
        ], true);
        $MonthlyTurnoverRateFeeTest->getModelInstance([
            'monthly_turnover_rate' => 9999,
            'fee'                   => 0,
            'is_use_product_type'   => 1,
            'type'                  => MonthlyTurnoverRateFee::TYPE_CLIENT
        ], true);
        $MonthlyTurnoverRateFeeTest->getModelInstance([
            'monthly_turnover_rate' => 9999,
            'fee'                   => 15,
            'is_use_product_type'   => 0,
            'type'                  => MonthlyTurnoverRateFee::TYPE_PRODUCT
        ], true);
        $ClientTest = new ClientTest();
        $ClientTest->setServiceLocator($this->getApplicationServiceLocator());
        $ClientIds   = $ClientTest->initBatchData(3);
        $ProductTest = new ProductTest();
        $ProductTest->setServiceLocator($this->getApplicationServiceLocator());
        $ProductOne   = $ProductTest->getModelInstance([
            'client_id'       => $ClientIds[0],
            'width_internal'  => 100,
            'length_internal' => 100,
            'depth_internal'  => 100
        ], true);
        $ProductTwo   = $ProductTest->getModelInstance([
            'client_id'       => $ClientIds[1],
            'width_internal'  => 100,
            'length_internal' => 100,
            'depth_internal'  => 100
        ], true);
        $ProductThree = $ProductTest->getModelInstance([
            'client_id'       => $ClientIds[2],
            'width_internal'  => 100,
            'length_internal' => 100,
            'depth_internal'  => 100
        ], true);

        $WarehouseTest = new WarehouseTest();
        $WarehouseTest->setServiceLocator($this->getApplicationServiceLocator());
        $Warehouse = $WarehouseTest->getModelInstance([], true);

        $ClientCompanyTest = new ClientCompanyTest();
        $ClientCompanyTest->setServiceLocator($this->getApplicationServiceLocator());
        $ClientCompanyTest->getModelInstance([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductOne->getClientId()
        ]);
        $ClientCompanyTest->getModelInstance([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductTwo->getClientId()
        ]);
        $ClientCompanyTest->getModelInstance([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductThree->getClientId()
        ]);

        $ProductSalesStockMonthlyDataTest = new ProductSalesStockMonthlyDataTest();
        $ProductSalesStockMonthlyDataTest->setServiceLocator($this->getApplicationServiceLocator());
        $ProductSalesStockMonthlyDataTest->getTableGateway()->delete([]);
        $ProductSalesStockMonthlyDataTest->getModelInstance([
            'company_id'     => $Warehouse->getCompanyId(),
            'client_id'      => $ProductOne->getClientId(),
            'product_id'     => $ProductOne->getId(),
            'stock_quantity' => 4,
            'stock_volume'   => $ProductOne->getWidthInternal() * $ProductOne->getLengthInternal() *
                                $ProductOne->getDepthInternal() * 4,
            'sales_quantity' => 2,
            'sales_volume'   => $ProductOne->getWidthInternal() * $ProductOne->getLengthInternal() *
                                $ProductOne->getDepthInternal() * 2,
            'date'           => date('Y-m-01', strtotime("$date -1 month"))
        ], true);
        $ProductSalesStockMonthlyDataTest->getModelInstance([
            'company_id'     => $Warehouse->getCompanyId(),
            'client_id'      => $ProductTwo->getClientId(),
            'product_id'     => $ProductTwo->getId(),
            'stock_quantity' => 10,
            'stock_volume'   => $ProductTwo->getWidthInternal() * $ProductTwo->getLengthInternal() *
                                $ProductTwo->getDepthInternal() * 10,
            'sales_quantity' => 2,
            'sales_volume'   => $ProductTwo->getWidthInternal() * $ProductTwo->getLengthInternal() *
                                $ProductTwo->getDepthInternal() * 2,
            'date'           => date('Y-m-01', strtotime("$date -1 month"))
        ], true);

        $ProductSalesStockMonthlyDataTest->getModelInstance([
            'company_id'     => $Warehouse->getCompanyId(),
            'client_id'      => $ProductThree->getClientId(),
            'product_id'     => $ProductThree->getId(),
            'stock_quantity' => 19999,
            'stock_volume'   => $ProductThree->getWidthInternal() * $ProductThree->getLengthInternal() *
                                $ProductThree->getDepthInternal() * 19999,
            'sales_quantity' => 1,
            'sales_volume'   => $ProductThree->getWidthInternal() * $ProductThree->getLengthInternal() *
                                $ProductThree->getDepthInternal() * 1,
            'date'           => date('Y-m-01', strtotime("$date -1 month"))
        ], true);

        $ClientStatementTest = new ClientStatementTest();
        $ClientStatementTest->setServiceLocator($this->getApplicationServiceLocator());
        $ClientStatementTest->getTableGateway()->update([
            'status' => ClientStatement::STATUS_INACTIVE
        ], [
            'company_id' => $Warehouse->getCompanyId(),
            'type'       => ClientStatement::TYPE_STORAGE_FEE
        ]);
        $MonthlyTurnoverRateStatementTest = new MonthlyTurnoverRateStatementTest();
        $MonthlyTurnoverRateStatementTest->setServiceLocator($this->getApplicationServiceLocator());
        $MonthlyTurnoverRateStatementTest->getTableGateway()->delete([]);

        // calculate fee
        $MonthlyStockFee = $this->getApplicationServiceLocator()->get(MonthlyStockFee::class);
        $MonthlyStockFee->setDate(date('Y-m-01', strtotime("$date -1 month")));
        $MonthlyStockFee->setCompanyId($Warehouse->getCompanyId());
        $MonthlyStockFee->calculateFee();

        $StatmentOne   = $MonthlyTurnoverRateStatementTest->getTableGateway()->fetchRow([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductOne->getClientId(),
        ]);
        $StatmentTwo   = $MonthlyTurnoverRateStatementTest->getTableGateway()->fetchRow([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductTwo->getClientId(),
        ]);
        $StatmentThree = $MonthlyTurnoverRateStatementTest->getTableGateway()->fetchRow([
            'company_id' => $Warehouse->getCompanyId(),
            'client_id'  => $ProductThree->getClientId(),
        ]);
        $this->assertNotNull($StatmentOne);
        $this->assertNotNull($StatmentTwo);
        $this->assertNotNull($StatmentThree);

        return [
            'date'          => date('Y-m-01', strtotime("$date -1 month")),
            'company_id'    => $Warehouse->getCompanyId(),
            'statement_ids' => [$StatmentOne->getId(), $StatmentTwo->getId(), $StatmentThree->getId()]
        ];
    }

    /**
     * @depends testCalculateFee
     *
     * @param $data
     *
     * @throws \BirdSystem\Controller\Exception\AppException
     */
    public function testCharge($data)
    {
        $ClientStatementTest = new ClientStatementTest();
        $ClientStatementTest->setServiceLocator($this->getApplicationServiceLocator());
        $ClientStatementTest->getTableGateway()->update([
            'status' => ClientStatement::STATUS_INACTIVE
        ], [
            'company_id' => $data['company_id'],
            'type'       => ClientStatement::TYPE_STORAGE_FEE
        ]);

        // calculate fee
        $MonthlyStockFee = $this->getApplicationServiceLocator()->get(MonthlyStockFee::class);
        $MonthlyStockFee->setDate($data['date']);
        $MonthlyStockFee->setCompanyId($data['company_id']);
        $MonthlyStockFee->charge();

        $ClientStatmentOne = $ClientStatementTest->getTableGateway()->fetchRow([
            'company_id' => $data['company_id'],
            'record_id'  => $data['statement_ids'][0],
            'type'       => ClientStatement::TYPE_STORAGE_FEE
        ]);
        $ClientStatmentTwo = $ClientStatementTest->getTableGateway()->fetchRow([
            'company_id' => $data['company_id'],
            'record_id'  => $data['statement_ids'][1],
            'type'       => ClientStatement::TYPE_STORAGE_FEE
        ]);
        $ClientStatmentTree = $ClientStatementTest->getTableGateway()->fetchRow([
            'company_id' => $data['company_id'],
            'record_id'  => $data['statement_ids'][2],
            'type'       => ClientStatement::TYPE_STORAGE_FEE
        ]);
        $this->assertNotNull($ClientStatmentOne);
        $this->assertNotNull($ClientStatmentTwo);
        $this->assertNotNull($ClientStatmentTree);
    }
    /*
        public function testInitClient()
        {
            $this->authenticate(true);

            $ProductSalesStockMonthlyDataTest = new ProductSalesStockMonthlyDataTest();
            $ProductSalesStockMonthlyDataTest->setServiceLocator($this->getApplicationServiceLocator());

            $ProductSalesStockMonthlyDataTG   = $ProductSalesStockMonthlyDataTest->getTableGateway();
            $Select                           = $ProductSalesStockMonthlyDataTG->getSql()->select()
                ->columns([
                    'company_id',
                    'client_id',
                ])
                ->where(['date' => '2016-03-01'])
                ->group(['company_id', 'client_id']);
            $ProductSalesStockMonthlyDataList = $ProductSalesStockMonthlyDataTG->selectWith($Select);

            $ClientTest = new ClientTest();
            $ClientTest->setServiceLocator($this->getApplicationServiceLocator());
            $CompanyTest = new CompanyTest();
            $CompanyTest->setServiceLocator($this->getApplicationServiceLocator());
            $ClientCompanyTest = new ClientCompanyTest();
            $ClientCompanyTest->setServiceLocator($this->getApplicationServiceLocator());

            foreach ($ProductSalesStockMonthlyDataList as $ProductSalesStockMonthlyData) {
                $CompanyTest->getModelInstance([
                    'id' => $ProductSalesStockMonthlyData->getCompanyId()
                ]);
                $ClientTest->getModelInstance([
                    'id' => $ProductSalesStockMonthlyData->getClientId()
                ]);
                $ClientCompanyTest->getModelInstance([
                    'company_id' => $ProductSalesStockMonthlyData->getCompanyId(),
                    'client_id'  => $ProductSalesStockMonthlyData->getClientId()
                ]);
            }
        }

        public function testCalculateLiveFee()
        {
            $this->authenticate(true);
            $ClientCompanyList = [1, 20];

            $ClientStatementTest = new ClientStatementTest();
            $ClientStatementTest->setServiceLocator($this->getApplicationServiceLocator());
            $ClientStatementTest->getTableGateway()->update([
                'status' => ClientStatement::STATUS_INACTIVE
            ], [
                'type' => ClientStatement::TYPE_STORAGE_FEE
            ]);
            $MonthlyTurnoverRateStatementTest = new MonthlyTurnoverRateStatementTest();
            $MonthlyTurnoverRateStatementTest->setServiceLocator($this->getApplicationServiceLocator());
            $MonthlyTurnoverRateStatementTest->getTableGateway()->delete([]);
            $MonthlyStockFee = $this->getApplicationServiceLocator()->get(MonthlyStockFee::class);
            $MonthlyStockFee->setDate('2016-03-01');
            $this->getApplicationServiceLocator()->get('db')->query('Set foreign_key_checks = 0;')->execute();
            foreach ($ClientCompanyList as $companyId) {
                // calculate fee
                $MonthlyStockFee->setCompanyId($companyId);
                $MonthlyStockFee->calculateFee();
            }
            $this->getApplicationServiceLocator()->get('db')->query('Set foreign_key_checks = 1;')->execute();
        }
        */
}

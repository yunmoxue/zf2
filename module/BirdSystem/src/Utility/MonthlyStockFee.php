<?php

namespace BirdSystem\Utility;

use BirdSystem\Db\TableGateway\ClientCompany;
use BirdSystem\Db\TableGateway\ClientStatement;
use BirdSystem\Db\TableGateway\Consignment;
use BirdSystem\Db\TableGateway\MonthlyTurnoverRateFee;
use BirdSystem\Db\TableGateway\MonthlyTurnoverRateStatement;
use BirdSystem\Db\TableGateway\Product;
use BirdSystem\Db\TableGateway\ProductSalesStockMonthlyData;
use BirdSystem\Traits\AuthenticationTrait;
use BirdSystem\Traits\LoggerAwareTrait;
use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Select;
use Zend\ServiceManager\ServiceLocatorAwareTrait;

class MonthlyStockFee
{
    use ServiceLocatorAwareTrait, AuthenticationTrait, LoggerAwareTrait;

    public $date;
    public $companyId;
    /**
     * @var \BirdSystem\Db\Model\MonthlyTurnoverRateFee[]|\Zend\Db\ResultSet\ResultSet $ProductMonthlyTurnoverRateFeeList
     */
    private $ProductMonthlyTurnoverRateFeeList;

    public function setDate($date)
    {
        $this->date = $date;
    }

    public function getDate()
    {
        if (is_null($this->date)) {
            $this->date = date('Y-m-01', strtotime('-1 month'));
        }

        return $this->date;
    }

    public function getMoth()
    {
        return substr($this->getDate(), 0, 7);
    }

    public function updateSalesData($date = null)
    {
        $date                           = is_null($date) ? date('Y-m-01') : $date;
        $ClientCompanyTG                = $this->serviceLocator->get(ClientCompany::class);
        $ProductTG                      = $this->serviceLocator->get(Product::class);
        $ProductSalesStockMonthlyDataTG = $this->serviceLocator->get(ProductSalesStockMonthlyData::class);

        $CompanyClients = $ClientCompanyTG->select([
            'status = ?' => $ClientCompanyTG::STATUS_ACTIVE
        ]);

        foreach ($CompanyClients as $CompanyClient) {
            // get last month sales quantity and sales volume
            $Select      = $ProductTG->getSql()->select()
                ->columns([
                    'company_id'         => new Expression('consignment.company_id'),
                    'client_id',
                    'id',
                    'sales_quantity'     => new Expression('SUM(consignment_product.quantity)'),
                    'volume'             => new Expression('product.length * product.width * product.depth'),
                    'consignment.status' => new Expression('consignment.status'),
                    'consignment.type'   => new Expression('consignment.type')
                ])
                ->join('consignment_product',
                    'consignment_product.product_id = product.id',
                    [],
                    Select::JOIN_INNER)
                ->join('consignment',
                    'consignment.id = consignment_product.consignment_id',
                    [],
                    Select::JOIN_INNER)
                ->where([
                    'consignment.company_id'       => $CompanyClient->getCompanyId(),
                    'consignment.client_id'        => $CompanyClient->getClientId(),
                    'consignment.type'             => Consignment::TYPE_LOCAL,
                    'consignment.status'           => Consignment::STATUS_FINISHED,
                    'consignment.finish_time >= ?' => date("Y-m-01 00:00:00", strtotime($date)),
                    'consignment.finish_time < ?'  => date("Y-m-01 00:00:00", strtotime("$date +1 month"))
                ])
                ->group(['consignment.company_id', 'consignment_product.product_id']);
            $ProductList = $ProductTG->selectWith($Select);
            foreach ($ProductList as $Product) {
                $data = [
                    'company_id'     => $Product->getCompanyId(),
                    'client_id'      => $Product->getClientId(),
                    'date'           => $date,
                    'product_id'     => $Product->getId(),
                    'sales_quantity' => $Product->getSalesQuantity(),
                    'sales_volume'   => $Product->getVolume() * $Product->getSalesQuantity(),
                    'stock_quantity' => new Expression('stock_quantity'),
                    'stock_volume'   => new Expression('stock_quantity * ' . $Product->getVolume())
                ];
                // update last month sales quantity and sales volume
                $ProductSalesStockMonthlyDataTG->save($ProductSalesStockMonthlyDataTG->getModel($data));
            }
        }
    }

    public function charge()
    {
        $MonthlyTurnoverRateStatementTG   = $this->serviceLocator->get(MonthlyTurnoverRateStatement::class);
        $ClientStatementTG                = $this->serviceLocator->get(ClientStatement::class);
        $Select                           = $MonthlyTurnoverRateStatementTG->getSql()->select()
            ->columns([
                'id',
                'client_id',
                'total_fee' => new Expression('IFNULL(
                IF(monthly_turnover_rate_statement.per_volume_fee=0,
                SUM(ROUND(p.per_volume_fee * (p.stock_volume - p.sales_volume)/1000/1000/1000,2)),
                ROUND(monthly_turnover_rate_statement.per_volume_fee * (monthly_turnover_rate_statement.stock_volume -
                    monthly_turnover_rate_statement.sales_volume)/1000/1000/1000,2)
                ),0)')
            ])
            ->join(['p' => 'monthly_turnover_rate_statement'],
                new Expression(
                    "monthly_turnover_rate_statement.company_id = p.company_id
                        AND monthly_turnover_rate_statement.client_id = p.client_id
                        AND monthly_turnover_rate_statement.date = p.date
                        AND p.type = 'PRODUCT'"),
                [], Select::JOIN_LEFT)
            ->where(['monthly_turnover_rate_statement.company_id' => $this->getCompanyId()])
            ->where(['monthly_turnover_rate_statement.date' => $this->getDate()])
            ->where(['monthly_turnover_rate_statement.type' => MonthlyTurnoverRateStatement::TYPE_CLIENT])
            ->group(['monthly_turnover_rate_statement.company_id', 'monthly_turnover_rate_statement.client_id']);
        $MonthlyTurnoverRateStatementList = $MonthlyTurnoverRateStatementTG->selectWith($Select);
        foreach ($MonthlyTurnoverRateStatementList as $MonthlyTurnoverRateStatement) {
            if ($MonthlyTurnoverRateStatement->getTotalFee() > 0) {
                $ClientStatementTG->insert([
                    'company_id' => $this->getCompanyId(),
                    'client_id'  => $MonthlyTurnoverRateStatement->getClientId(),
                    'record_id'  => $MonthlyTurnoverRateStatement->getId(),
                    'amount'     => $MonthlyTurnoverRateStatement->getTotalFee() * (-1),
                    'type'       => $ClientStatementTG::TYPE_STORAGE_FEE,
                    'note'       => $this->getMoth() . ' storage fee',
                ]);
            }
        }
    }

    public function calculateFee()
    {
        $MonthlyTurnoverRateFeeTG       = $this->serviceLocator->get(MonthlyTurnoverRateFee::class);
        $ProductSalesStockMonthlyDataTG = $this->serviceLocator->get(ProductSalesStockMonthlyData::class);
        $MonthlyTurnoverRateStatementTG = $this->serviceLocator->get(MonthlyTurnoverRateStatement::class);
        $ClientCompanyTG                = $this->serviceLocator->get(ClientCompany::class);
        $companyId                      = $this->getCompanyId();

        /**
         * @var \BirdSystem\Db\Model\MonthlyTurnoverRateFee[]|\Zend\Db\ResultSet\ResultSet $ClientMonthlyTurnoverRateFeeList
         */
        $ClientMonthlyTurnoverRateFeeList = $MonthlyTurnoverRateFeeTG->getListByType(
            $companyId,
            MonthlyTurnoverRateFee::TYPE_CLIENT)->buffer();

        $this->ProductMonthlyTurnoverRateFeeList = ($MonthlyTurnoverRateFeeTG->getListByType(
            $companyId,
            MonthlyTurnoverRateFee::TYPE_PRODUCT)->buffer());
        // get all client turnover rate
        $Select                           = $ProductSalesStockMonthlyDataTG->getSql()->select()
            ->columns([
                'client_id',
                'stock_volume' => new Expression('SUM(stock_volume)'),
                'sales_volume' => new Expression('SUM(sales_volume)')
            ])
            ->where(['company_id' => $companyId])
            ->where(['date' => $this->getDate()])
            ->group('client_id');
        $ProductSalesStockMonthlyDataList = $ProductSalesStockMonthlyDataTG->selectWith($Select);
        foreach ($ProductSalesStockMonthlyDataList as $Record) {
            $perVolumeFee = 0;
            if (is_null($ClientCompanyTG->fetchRow([
                'company_id' => $companyId,
                'client_id'  => $Record->getClientId(),
            ]))) {
                continue;
            }
            $turnoverRate = 9999;
            $RateFee      = null;
            if ($Record->getSalesVolume() != 0) {
                $turnoverRate = round($Record->getStockVolume() / $Record->getSalesVolume(), 4);
            }
            foreach ($ClientMonthlyTurnoverRateFeeList as $RateFee) {
                // find the client turnover rate and calculate
                if ($RateFee->getMonthlyTurnoverRate() >= $turnoverRate && $turnoverRate > 0) {
                    // calculate fee
                    if ($RateFee->getIsUseProductType() == 0) {
                        $perVolumeFee = $RateFee->getFee();
                    } else {
                        // calculate fee by product
                        $this->calculateFeeByClientProduct($Record->getClientId());
                    }
                    break;
                }
            }
            // if company already set turnover rate table, and can't find suitable, will use the most expensive fee.
            if (!is_null($RateFee) && $RateFee->getMonthlyTurnoverRate() < $turnoverRate && $turnoverRate > 0) {
                if ($RateFee->getIsUseProductType() == 0) {
                    $perVolumeFee = $RateFee->getFee();
                } else {
                    $this->calculateFeeByClientProduct($Record->getClientId());
                }
            }
            $model = $MonthlyTurnoverRateStatementTG->getModel([
                'company_id'     => $companyId,
                'client_id'      => $Record->getClientId(),
                'product_id'     => null,
                'date'           => $this->getDate(),
                'stock_volume'   => $Record->getStockVolume(),
                'sales_volume'   => $Record->getSalesVolume(),
                'per_volume_fee' => $perVolumeFee,
                'type'           => MonthlyTurnoverRateStatement::TYPE_CLIENT
            ]);
            $MonthlyTurnoverRateStatementTG->save($model);
        }
    }

    public function calculateFeeByClientProduct($clientId)
    {
        $ProductSalesStockMonthlyDataTG = $this->serviceLocator->get(ProductSalesStockMonthlyData::class);
        $MonthlyTurnoverRateStatementTG = $this->serviceLocator->get(MonthlyTurnoverRateStatement::class);
        $companyId                      = $this->getCompanyId();
        // get all product turnover rate by client
        $Select                           = $ProductSalesStockMonthlyDataTG->getSql()->select()
            ->columns(['product_id', 'client_id', 'stock_volume', 'sales_volume'])
            ->where(['company_id' => $companyId])
            ->where(['client_id' => $clientId])
            ->where(['date' => $this->getDate()]);
        $ProductSalesStockMonthlyDataList = $ProductSalesStockMonthlyDataTG->selectWith($Select);
        foreach ($ProductSalesStockMonthlyDataList as $Record) {
            $turnoverRate = 9999;
            $RateFee      = null;
            $tempFee      = null;
            if ($Record->getSalesVolume() != 0) {
                $turnoverRate = round($Record->getStockVolume() / $Record->getSalesVolume(), 4);
            }
            foreach ($this->ProductMonthlyTurnoverRateFeeList as $RateFee) {
                // find the product turnover rate fee
                if ($RateFee->getMonthlyTurnoverRate() >= $turnoverRate && $turnoverRate > 0) {
                    // calculate fee
                    $tempFee = round(($Record->getStockVolume() - $Record->getSalesVolume()) / 1000 / 1000 / 1000 *
                                     $RateFee->getFee(), 2);
                    break;
                }
            }
            // if company already set turnover rate table, and can't find suitable, will use the most expensive fee.
            if (!is_null($RateFee) && $RateFee->getMonthlyTurnoverRate() < $turnoverRate && $turnoverRate > 0) {
                // calculate fee
                $tempFee = round(($Record->getStockVolume() - $Record->getSalesVolume()) / 1000 / 1000 / 1000 *
                                 $RateFee->getFee(), 2);
            }
            if ($tempFee > 0) {
                $model = $MonthlyTurnoverRateStatementTG->getModel([
                    'company_id'     => $companyId,
                    'client_id'      => $Record->getClientId(),
                    'product_id'     => $Record->getProductId(),
                    'date'           => $this->getDate(),
                    'stock_volume'   => $Record->getStockVolume(),
                    'sales_volume'   => $Record->getSalesVolume(),
                    'per_volume_fee' => $RateFee->getFee(),
                    'type'           => MonthlyTurnoverRateStatement::TYPE_PRODUCT
                ]);
                $MonthlyTurnoverRateStatementTG->save($model);
            }
        }
    }

    /**
     * @return mixed
     */
    public function getCompanyId()
    {
        if (is_null($this->companyId)) {
            $this->companyId = $this->getUserInfo()->getCompanyId();
        }

        return $this->companyId;
    }

    /**
     * @param mixed $companyId
     *
     * @return $this
     */
    public function setCompanyId($companyId)
    {
        $this->companyId = $companyId;

        return $this;
    }

}

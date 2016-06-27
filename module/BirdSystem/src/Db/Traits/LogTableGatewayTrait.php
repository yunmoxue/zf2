<?php


namespace BirdSystem\Db\Traits;


use BirdSystem\Db\TableGateway\AbstractLog;
use BirdSystem\Traits\AuthenticationTrait;
use Zend\Json\Json;

trait LogTableGatewayTrait
{
    use AuthenticationTrait;

    public function getUserType()
    {
        $UserInfo = $this->getUserInfo();
        if (!$UserInfo) {
            return null;
        }

        if ($UserInfo->isClientUserInfo()) {
            return AbstractLog::USER_TYPE_CLIENT;
        } else {
            return AbstractLog::USER_TYPE_COMPANY;
        }
    }

    public function getUserId()
    {
        $UserInfo = $this->getUserInfo();
        if (!$UserInfo) {
            return null;
        }

        return $UserInfo->getId();
    }

    /**
     * Compare new and old values and only return different values
     *
     * @param array $newValue
     * @param array $oldValue
     * @param array $filterFields
     *
     * @return array
     */
    public function getValueDifference($newValue, $oldValue, $filterFields = [])
    {

        $difference = [AbstractLog::NEW_VALUE => [], AbstractLog::OLD_VALUE => []];

        $newValue = $this->filterData($newValue, $filterFields);
        $oldValue = $this->filterData($oldValue, $filterFields);

        foreach ($newValue as $key => $value) {
            if (@!is_object($newValue[$key])
                && isset($newValue[$key]) && isset($oldValue[$key])
                && $newValue[$key] != $oldValue[$key]
            ) {
                @$difference[AbstractLog::NEW_VALUE][$key] = $newValue[$key];
                @$difference[AbstractLog::OLD_VALUE][$key] = $oldValue[$key];
            }
        }

        return $difference;
    }

    protected function filterData($data, $filterFields = [])
    {
        foreach ($filterFields as $key => $value) {
            if (isset($data[$key])) {
                unset($data[$key]);
            }
        }

        return $data;
    }

    public function processData($data)
    {
        if (@is_array($data[AbstractLog::DETAIL_FIELD][AbstractLog::NEW_VALUE]) &&
            0 == count($data[AbstractLog::DETAIL_FIELD][AbstractLog::NEW_VALUE])
        ) {
            return [];
        } else {
            if (isset($data[AbstractLog::DETAIL_FIELD])) {
                $data[AbstractLog::DETAIL_FIELD] = Json::encode($data[AbstractLog::DETAIL_FIELD]);
            }

        }

        return $data;
    }
}
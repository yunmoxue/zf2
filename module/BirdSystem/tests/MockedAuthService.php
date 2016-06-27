<?php


namespace BirdSystem\Tests;


use BirdSystem\Authentication\Storage\Session;
use BirdSystem\Db\Model\UserInfo;

class MockedAuthService
{
    static protected $UserInfo;
    /**
     * @var Session
     */
    static protected $Storage;

    /**
     * @return UserInfo
     */
    public function getUserInfo()
    {
        if (!static::$UserInfo) {
            static::$UserInfo = new UserInfo([
                'id'         => 1,
                'company_id' => 1,
                'username'   => 'company',
                'password'   => 'company',
            ]);
        }

        return static::$UserInfo;
    }

    public function hasIdentity()
    {
        return true;
    }

    public function getStorage()
    {
        if (!static::$Storage) {
            static::$Storage = new Session();
            static::$Storage->write($this->getUserInfo()->getArrayCopy());
        }
        return static::$Storage;
    }
}
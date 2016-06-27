<?php
namespace BirdSystem\Traits;

use BirdSystem\Authentication\AuthenticationService;
use BirdSystem\Authentication\AuthenticationServiceNotAvailableException;
use BirdSystem\Controller\AbstractRestfulController;
use BirdSystem\Db\TableGateway;
use Zend\Mvc\MvcEvent;

/**
 * Class AuthenticationTrait
 *
 * @package BirdSystem\Traits
 * @codeCoverageIgnore
 */
trait AuthenticationTrait
{
    /**
     * @var AuthenticationService
     */
    protected $AuthenticationService;

    protected $requireLogin = true;


    /**
     * @param bool $value
     */
    public function setRequireLogin($value = true)
    {
        $this->requireLogin = $value;
    }

    /**
     * @return AuthenticationService
     * @throws AuthenticationServiceNotAvailableException
     */
    public function getAuthenticationService()
    {
        /** @var AbstractRestfulController $this */
        if (!$this->serviceLocator->has('AuthService')) {
            throw new AuthenticationServiceNotAvailableException;
        }

        return $this->serviceLocator->get('AuthService');
    }

}

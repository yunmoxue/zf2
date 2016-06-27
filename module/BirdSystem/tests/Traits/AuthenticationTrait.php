<?php
namespace BirdSystem\Tests\Traits;

use BirdSystem\Tests\AbstractTestCase;
use BirdSystem\Tests\MockedAuthService;

trait AuthenticationTrait
{
    /**
     * @param bool $forceAuthenticate
     *
     * @return $this
     */
    protected function authenticate($forceAuthenticate = false)
    {
        /**
         * @var AbstractTestCase $this
         */
        if (!$this->getApplicationServiceLocator()->has('AuthService') || $forceAuthenticate) {

            $this->getApplicationServiceLocator()->setAllowOverride(true);
            $this->getApplicationServiceLocator()->setService('AuthService', new MockedAuthService());
        }

        return $this;
    }
}

<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 17/12/2015
 * Time: 23:16
 */

namespace BirdSystem\Tests\Authentication;

use BirdSystem\Authentication\AuthenticationServiceFactory;
use BirdSystem\Exception;
use BirdSystem\Tests\AbstractTestCase;

class AuthenticationServiceFactoryTest extends AbstractTestCase
{
    public function testCreateWithWrongModule()
    {
        $this->setExpectedExceptionRegExp(Exception::class, '/Wrong module given/');

        (new AuthenticationServiceFactory())->create('NEVER EXISTED MODULE');
    }

    public function testGetAuthAdapterWithWrongModule()
    {
        $this->setExpectedExceptionRegExp(Exception::class, '/No correct module given/');

        (new AuthenticationServiceFactory())->getAuthAdapter('NEVER EXISTED MODULE');
    }
}

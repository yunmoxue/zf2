<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 28/12/2015
 * Time: 15:43
 */

namespace BirdSystem\Tests\Service\Paypal;


use BirdSystem\Tests\AbstractTestCase;

class ExpressCheckoutTest extends AbstractTestCase
{
    public function testDemoServiceManager()
    {
        $service = $this->getApplicationServiceLocator()->get('Service\Paypal');
        $this->assertInstanceOf(\Birdsystem\Service\Paypal\ExpressCheckout::class, $service);
    }
}

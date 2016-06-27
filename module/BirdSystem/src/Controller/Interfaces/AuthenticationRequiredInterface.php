<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 21/12/2015
 * Time: 18:52
 */

namespace BirdSystem\Controller\Interfaces;

use Zend\Authentication\AuthenticationService;

interface AuthenticationRequiredInterface
{

    /**
     * @return AuthenticationService
     */
    public function getAuthenticationService();

}

<?php

namespace BirdSystem\Controller\Exception;

class AppException extends \Exception
{
    public function __construct($message = '')
    {
        parent::__construct($message);
    }
}

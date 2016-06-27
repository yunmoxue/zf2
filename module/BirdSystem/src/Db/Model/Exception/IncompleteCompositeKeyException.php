<?php

namespace BirdSystem\Db\Model\Exception;

use BirdSystem\Exception;

class IncompleteCompositeKeyException extends Exception
{
    protected $message = 'Composite Key information is not complete';
}

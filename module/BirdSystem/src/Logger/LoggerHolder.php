<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 06/01/2016
 * Time: 16:55
 */

namespace BirdSystem\Logger;

use Psr\Log\LoggerInterface;

/**
 * Class LoggerHolder
 * Use this Holder class to hold a static Logger in order to optimize memory usage
 *
 * @package BirdSystem\Logger
 */
class LoggerHolder
{
    /**
     * @var LoggerInterface
     */
    public static $logger;
}
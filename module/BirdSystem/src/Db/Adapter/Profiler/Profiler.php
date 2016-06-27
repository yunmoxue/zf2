<?php
/**
 * User: Allan Sun (allan.sun@bricre.com)
 * Date: 30/12/2015
 * Time: 18:51
 */

namespace BirdSystem\Db\Adapter\Profiler;

use Zend\Db\Adapter\Driver\Pdo\Statement;
use Zend\Db\Adapter\Exception\InvalidArgumentException;
use Zend\Db\Adapter\Profiler\Profiler as Base;
use Zend\Db\Adapter\StatementContainerInterface;

class Profiler extends Base
{
    use ProfilerTraits;

    /**
     * @inheritdoc
     *
     * @param Statement $target
     */
    public function profilerStart($target)
    {
        $profileInformation = [
            'sql'        => '',
            'parameters' => null,
            'start'      => microtime(true),
            'end'        => null,
            'elapse'     => null,
        ];
        if ($target instanceof StatementContainerInterface) {
            $profileInformation['sql']        =
                $this->interpolateQuery($target->getSql(), $target->getParameterContainer()->getNamedArray());
            $profileInformation['parameters'] = clone $target->getParameterContainer();
        } elseif (is_string($target)) {
            $profileInformation['sql'] = $target;
        } else {
            throw new InvalidArgumentException(__FUNCTION__ . ' takes either a
            StatementContainer or a
            string');
        }

        $this->profiles[$this->currentIndex] = $profileInformation;

        return $this;
    }

}
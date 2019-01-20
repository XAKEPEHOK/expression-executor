<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 0:47
 */

namespace XAKEPEHOK\ExpressionExecutor;


use XAKEPEHOK\ExpressionExecutor\Exceptions\FunctionException;

interface FunctionInterface
{

    public function getName(): string;

    /**
     * @param array $arguments
     * @return mixed
     * Use @see FunctionException in needed
     */
    public function execute(array $arguments);

}
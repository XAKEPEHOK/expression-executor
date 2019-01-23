<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 4:41
 */

namespace XAKEPEHOK\ExpressionExecutor;


use XAKEPEHOK\ExpressionExecutor\Exceptions\OperatorException;

interface OperatorInterface
{

    public function operator(): string;

    /**
     * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
     * @return int
     */
    public function priority(): int;

    /**
     * @param $leftOperand
     * @param $rightOperand
     * @param array $context - any common data, that passed to @see \XAKEPEHOK\ExpressionExecutor\Executor::execute()
     * @return mixed
     * Use @see OperatorException if needed
     */
    public function execute($leftOperand, $rightOperand, array $context);

}
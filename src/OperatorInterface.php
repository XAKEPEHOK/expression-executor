<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 4:41
 */

namespace XAKEPEHOK\ExpressionExecutor;


interface OperatorInterface
{

    public function operator(): string;

    /**
     * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
     * @return int
     */
    public function priority(): int;

    public function execute($leftOperand, $rightOperand);

}
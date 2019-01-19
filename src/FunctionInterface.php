<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 0:47
 */

namespace XAKEPEHOK\ExpressionExecutor;


interface FunctionInterface
{

    public function getName(): string;

    public function execute(array $arguments);

}
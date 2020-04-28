<?php
/**
 * Created for expression-executor
 * Date: 28.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionFloat implements ExpressionComponentInterface
{

    /** @var float */
    private $value;

    public function __construct(float $value)
    {
        $this->value = $value;
    }

    /**
     * @return float
     */
    public function getValue(): float
    {
        return $this->value;
    }

}
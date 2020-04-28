<?php
/**
 * Created for expression-executor
 * Date: 28.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionString implements ExpressionComponentInterface
{

    /** @var string */
    private $value;

    public function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return $this->value;
    }

}
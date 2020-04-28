<?php
/**
 * Created for expression-executor
 * Date: 28.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionOperation implements ExpressionComponentInterface
{

    /** @var string */
    private $operation;

    /** @var mixed */
    private $leftOperand;

    /** @var mixed */
    private $rightOperand;

    /** @var mixed */
    private $value;

    public function __construct(string $operation, $leftOperand, $rightOperand, $value)
    {
        $this->operation = $operation;
        $this->leftOperand = trim($leftOperand, '`');
        $this->rightOperand = trim($rightOperand, '`');
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getOperation(): string
    {
        return $this->operation;
    }

    /**
     * @return mixed
     */
    public function getLeftOperand()
    {
        return $this->leftOperand;
    }

    /**
     * @return mixed
     */
    public function getRightOperand()
    {
        return $this->rightOperand;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

}
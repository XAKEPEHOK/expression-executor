<?php
/**
 * Created for expression-executor
 * Date: 28.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionFunction implements ExpressionComponentInterface
{

    /** @var string */
    private $name;

    /** @var array */
    private $arguments;

    /** @var mixed */
    private $value;

    public function __construct(string $name, array $arguments, $value)
    {
        $this->name = $name;
        $this->arguments = array_map(function ($value) {
            return trim($value, '`');
        }, $arguments);
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getArguments(): array
    {
        return $this->arguments;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

}
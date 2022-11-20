<?php
/**
 * Created for expression-executor
 * Date: 28.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionArray implements ExpressionComponentInterface
{

    /** @var array */
    private $array;

    /** @var array */
    private $value;

    public function __construct(array $array, array $value)
    {
        $this->array = array_map(function (string $hash) {
            return trim($hash, '`');
        }, $array);
        $this->value = $value;
    }

    /**
     * @return array
     */
    public function getArray(): array
    {
        return $this->array;
    }

    /**
     * @return array
     */
    public function getValue(): array
    {
        return $this->value;
    }

}
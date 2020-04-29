<?php
/**
 * Created for expression-executor
 * Date: 29.04.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace XAKEPEHOK\ExpressionExecutor\Components;


class ExpressionBrackets implements ExpressionComponentInterface
{

    /**
     * @var ExpressionComponentInterface
     */
    private $component;

    public function __construct(ExpressionComponentInterface $component)
    {
        $this->component = $component;
    }

    public function getValue()
    {
        return $this->component;
    }
}
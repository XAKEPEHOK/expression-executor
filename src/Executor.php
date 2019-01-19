<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 0:43
 */

namespace XAKEPEHOK\ExpressionExecutor;


use XAKEPEHOK\ExpressionExecutor\Exceptions\ExecutorException;
use XAKEPEHOK\ExpressionExecutor\Exceptions\SyntaxException;

class Executor
{

    /**
     * @var FunctionInterface[]|callable[]
     */
    private $functions;
    /**
     * @var callable[]|mixed[]
     */
    private $variables;
    /**
     * @var OperatorInterface[]
     */
    private $operators;

    /** @var array */
    private $memory = [];

    /**
     * Executor constructor.
     * @param FunctionInterface[]|callable[] $functions
     * @param OperatorInterface[] $operators
     * @param array $variables
     * @throws ExecutorException
     */
    public function __construct(array $functions, array $operators, array $variables = [])
    {
        foreach ($functions as $name => $function) {
            if ($function instanceof FunctionInterface) {
                $name = $function->getName();
            }

            if (!preg_match('~^[a-z\d_]+$~i', $name)) {
                throw new ExecutorException("Invalid function name «{$name}»: name should be match [a-z\d_]+");
            }

        }
        $this->functions = $functions;

        foreach ($operators as $operator) {
            if (preg_match('~["\(\)]~', $operator->operator())) {
                throw new ExecutorException("Invalid operator «{$operator->operator()}»: operator should not contain brackets and double-quotes");
            }
        }
        $this->operators = $operators;


        usort($this->operators, function (OperatorInterface $operator_1, OperatorInterface $operator_2) {
            if ($operator_1->priority() == $operator_2->priority()) {
                return 0;
            }
            return ($operator_1->priority() < $operator_2->priority()) ? 1 : -1;
        });

        $this->variables = $variables;
    }

    /**
     * @param string $expression
     * @return null|string|string[]
     * @throws SyntaxException
     */
    public function execute(string $expression)
    {
        $this->guardToken($expression);
        $expression = $this->prepareArguments($expression);
        $expression = $this->prepareVariables($expression);
        $expression = preg_replace('/\s+/u', '', $expression);
        $expression = $this->calculate($expression);

        if (preg_match('~^`[a-f\d]{32}`$~', $expression)) {
            $result = $this->recall($expression);
            $this->memory = [];;
            return $result;
        }

        throw new SyntaxException('Syntax exception');
    }

    /**
     * @param string $expression
     * @return string
     */
    protected function calculate(string $expression): string
    {
        if (!preg_match('~^`[a-f\d]{32}`$~', $expression)) {
            $changed = true;
            while ($changed) {
                $before = $expression;
                $expression = $this->calcFunctions($expression);
                $expression = $this->calcOperations($expression);
                $changed = $before !== $expression;
            }
        }
        return $expression;
    }

    private function prepareVariables(string $expression): string
    {
        $matches = [];
        while (preg_match('~\{\{([^\}]+)\}\}~', $expression, $matches)) {

            $value = $this->variables[$matches[1]];
            if (is_callable($value)) {
                $value = $value($matches[1]);
            }

            $expression = str_replace(
                $matches[0],
                $this->remember($matches[0], $value),
                $expression
            );
        }
        return $expression;
    }

    /**
     * @see https://stackoverflow.com/a/41470813
     * @param string $expression
     * @return string
     */
    private function prepareArguments(string $expression): string
    {
        $matches = [];
        $regexp = '~(?<!\\\\)(?:\\\\{2})*"((?:(?<!\\\\)(?:\\\\{2})*\\\\"|[^"])+(?<!\\\\)(?:\\\\{2})*)"~';
        while (preg_match($regexp, $expression, $matches)) {
            $expression = str_replace(
                $matches[0],
                $this->remember($matches[0], str_replace('\"', '"', $matches[1])),
                $expression
            );
        }
        return $expression;
    }

    private function calcFunctions(string $expression): string
    {
        foreach ($this->functions as $name => $function) {
            if ($function instanceof FunctionInterface) {
                $name = $function->getName();
            }

            $exactlyFunctionName = '(?<![a-z\d_])' . preg_quote($name);
            $arrayArguments = '\(((?:`[a-f\d]{32}`){1}(?:,`[a-f\d]{32}`)*)\)';
            $namedArguments = '\(((?:[a-z\d_]+:`[a-f\d]{32}`)(?:,[a-z\d_]+:`[a-f\d]{32}`)*)\)';

            $regexp = "~{$exactlyFunctionName}(?:(?:{$arrayArguments})|(?:(?:{$namedArguments})))~ui";

            $matches = [];
            while (preg_match($regexp, $expression, $matches)) {

                if (!empty($matches[1])) {
                    $arguments = array_map(function ($token) {
                        return $this->recall($token);
                    }, explode(',', $matches[1]));
                } else {
                    $arguments = array_map(function ($token) {
                        list($key, $value) = explode(':', $token);
                        return [
                            'key' => $key,
                            'value' => $this->recall($value),
                        ];
                    }, explode(',', $matches[2]));

                    $arguments = array_combine(
                        array_column($arguments, 'key'),
                        array_column($arguments, 'value')
                    );
                }

                if ($function instanceof FunctionInterface) {
                    $value = $function->execute($arguments);
                } else {
                    $value = $function($arguments);
                }

                $expr = $matches[0];
                $expression = str_replace($expr, $this->remember($expr, $value), $expression);
            }
        }
        return $expression;
    }

    private function calcOperations(string $expression): string
    {
        $regexps = [
            ['~\((`[a-f\d]{32}`)', '(`[a-f\d]{32}`)\)~'],
            ['~(`[a-f\d]{32}`)', '(`[a-f\d]{32}`)~'],
        ];

        foreach ($regexps as $regexpPriority) {
            foreach ($this->operators as $operator) {
                $regexp = $regexpPriority[0] . preg_quote($operator->operator(), '~') . $regexpPriority[1];
                $matches = [];
                while (preg_match($regexp, $expression, $matches)) {
                    $leftOperand = $this->recall($matches[1]);
                    $rightOperand = $this->recall($matches[2]);
                    $result = $operator->execute($leftOperand, $rightOperand);
                    $token = $this->remember($matches[0], $result);
                    $expression = str_replace($matches[0], $token, $expression);
                }
            }
        }

        return $expression;
    }


    /**
     * @see https://stackoverflow.com/a/41470813
     * @param string $expression
     * @return string
     * @throws SyntaxException
     */
    private function guardToken(string $expression): string
    {
        $matches = [];
        $regexp = '~(?<!\\\\)(?:\\\\{2})*"((?:(?<!\\\\)(?:\\\\{2})*\\\\"|[^"])+(?<!\\\\)(?:\\\\{2})*)"~';
        while (preg_match($regexp, $expression, $matches)) {
            $expression = str_replace(
                $matches[0],
                '""',
                $expression
            );
        }

        $tokens = [];
        if (preg_match('~`[a-f\d]{32}`~', $expression, $tokens)) {
            throw new SyntaxException('Invalid token in expression: ' . $tokens[0]);
        }

        return $expression;
    }

    private function remember($expression, $value): string
    {
        $hash = md5($expression);
        $this->memory[$hash] = [
            'expr' => $expression,
            'value' => $value
        ];
        return '`' . $hash . '`';
    }

    private function recall($token)
    {
        $token = trim($token, '` ');
        return $this->memory[$token]['value'];
    }

}
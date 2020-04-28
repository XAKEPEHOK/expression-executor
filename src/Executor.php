<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 0:43
 */

namespace XAKEPEHOK\ExpressionExecutor;


use XAKEPEHOK\ExpressionExecutor\Components\ExpressionComponentInterface;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionConstant;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionFloat;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionFunction;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionInteger;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionOperation;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionString;
use XAKEPEHOK\ExpressionExecutor\Components\ExpressionVariable;
use XAKEPEHOK\ExpressionExecutor\Exceptions\ExecutorException;
use XAKEPEHOK\ExpressionExecutor\Exceptions\SyntaxException;

class Executor
{

    /** @var FunctionInterface[]|callable[] */
    private $functions;

    /** @var callable[]|mixed[] */
    private $variables;

    /** @var OperatorInterface[] */
    private $operators;

    /** @var array */
    private $constants;

    /** @var ExpressionComponentInterface[] */
    private $simplifications = [];

    /**
     * Executor constructor.
     * @param FunctionInterface[] $functions
     * @param OperatorInterface[] $operators
     * @param callable $variables
     * @param array $constants
     * @throws ExecutorException
     */
    public function __construct(
        array $functions,
        array $operators,
        callable $variables = null,
        array $constants = []
    )
    {
        foreach ($functions as $function) {
            if (!($function instanceof FunctionInterface)) {
                throw new ExecutorException('Function should be instance of ' . FunctionInterface::class, 1);
            }

            if (!preg_match('~^[a-z\d_]+$~i', $function->getName())) {
                throw new ExecutorException("Invalid function name «{$function->getName()}»: name should be match [a-z\d_]+", 11);
            }
        }
        $this->functions = $functions;

        foreach ($operators as $operator) {
            if (!($operator instanceof OperatorInterface)) {
                throw new ExecutorException('Operator should be instance of ' . OperatorInterface::class, 2);
            }

            if (preg_match('~["\(\),_]~', $operator->operator())) {
                throw new ExecutorException("Invalid operator «{$operator->operator()}»: operator should not contain commas, brackets, underscore and double-quotes", 22);
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

        foreach ($constants as $name => $value) {
            if (!preg_match('~^[a-z_]{1}[a-z\d_]*$~i', $name)) {
                throw new ExecutorException("Invalid constant name «{$name}»: name should be match ^[a-z_]{1}[a-z\d_]*$", 4);
            }
        }
        $this->constants = $constants;
    }

    /**
     * Execute expression and return its result
     * @param string $expression
     * @param array $context - any data,that will be passed to variable callable as second argument
     * @return mixed
     * @throws SyntaxException
     */
    public function execute(string $expression, array $context = [])
    {
        $this->simplifications = [];
        $this->guardToken($expression);
        $expression = $this->prepareStrings($expression);
        $expression = $this->prepareVariables($expression, $context);
        $expression = $this->prepareConstants($expression);
        $expression = $this->prepareNumbers($expression);
        $expression = preg_replace('/\s+/u', '', $expression);
        $expression = $this->calculate($expression, $context);

        if (preg_match('~^`[a-f\d]{32}`$~', $expression)) {
            return $this->recall($expression);
        }

        throw new SyntaxException('Unexpected execution exception');
    }

    /**
     * @param string $expression
     * @param array $context
     * @return array
     * @throws SyntaxException
     */
    public function explain(string $expression, array $context = []): array
    {
        $this->execute($expression, $context);
        end($this->simplifications);
        return $this->buildTree(key($this->simplifications));
    }

    public function buildTree(string $hash): array
    {
        /** @var ExpressionComponentInterface $component */
        $component = $this->simplifications[$hash]['component'];

        $result = [];

        if ($component instanceof ExpressionConstant) {
            $result = [
                'type' => 'CONSTANT',
                'name' => $component->getName(),
                'calculated' => $component->getValue(),
            ];
        }

        if ($component instanceof ExpressionFloat) {
            $result = [
                'type' => 'FLOAT',
                'value' => $component->getValue(),
                'calculated' => $component->getValue(),
            ];
        }

        if ($component instanceof ExpressionFunction) {
            $result = [
                'type' => 'FUNCTION',
                'name' => $component->getName(),
                'arguments' => [],
                'calculated' => $component->getValue(),
            ];

            foreach ($component->getArguments() as $argument => $value) {
                $result['arguments'][$argument] = $this->buildTree($value);
            }
        }

        if ($component instanceof ExpressionInteger) {
            $result = [
                'type' => 'INTEGER',
                'value' => $component->getValue(),
                'calculated' => $component->getValue(),
            ];
        }

        if ($component instanceof ExpressionOperation) {
            $result = [
                'type' => 'OPERATION',
                'operation' => $component->getOperation(),
                'leftOperand' => $this->buildTree($component->getLeftOperand()),
                'rightOperand' => $this->buildTree($component->getRightOperand()),
                'calculated' => $component->getValue(),
            ];
        }

        if ($component instanceof ExpressionString) {
            $result = [
                'type' => 'STRING',
                'value' => $component->getValue(),
                'calculated' => $component->getValue(),
            ];
        }

        if ($component instanceof ExpressionVariable) {
            $result = [
                'type' => 'VARIABLE',
                'name' => $component->getName(),
                'calculated' => $component->getValue(),
            ];
        }

        return $result;
    }

    private function getComponentByHash(string $hash): ExpressionComponentInterface
    {
        return $this->simplifications[$hash]['component'];
    }

    /**
     * @param string $expression
     * @param array $context
     * @return string
     */
    private function calculate(string $expression, array $context): string
    {
        if (!preg_match('~^`[a-f\d]{32}`$~', $expression)) {
            $changed = true;
            while ($changed) {
                $before = $expression;
                $expression = $this->calcFunctions($expression, $context);
                $expression = $this->calcOperations($expression, $context);
                $changed = $before !== $expression;
            }
        }
        return $expression;
    }

    /**
     * @param string $expression
     * @param array $context
     * @return string
     */
    private function prepareVariables(string $expression, array $context): string
    {
        if ($this->variables === null) {
            return $expression;
        }

        $matches = [];
        while (preg_match('~\{\{([^\}]+)\}\}~', $expression, $matches)) {

            $value = ($this->variables)($matches[1], $context);

            $expression = str_replace(
                $matches[0],
                $this->simplify($matches[0], new ExpressionVariable($matches[0], $value)),
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
    private function prepareStrings(string $expression): string
    {
        $matches = [];
        $regexp = '~(?<!\\\\)(?:\\\\{2})*"((?:(?<!\\\\)(?:\\\\{2})*\\\\"|[^"])+(?<!\\\\)(?:\\\\{2})*)"~';
        while (preg_match($regexp, $expression, $matches)) {
            $value = str_replace('\"', '"', $matches[1]);
            $expression = str_replace(
                $matches[0],
                $this->simplify($matches[0], new ExpressionString($value)),
                $expression
            );
        }

        $expression = str_replace(
            '""',
            $this->simplify('""', new ExpressionString('')),
            $expression
        );

        return $expression;
    }

    /**
     * @see https://stackoverflow.com/a/41470813
     * @param string $expression
     * @return string
     */
    private function prepareNumbers(string $expression): string
    {
        //float negative
        $expression = preg_replace_callback(
            '~(?<![\da-z_])(?:\(-)((?:(?:0\.)|(?:[1-9]\d*\.))\d+)(?:\))(?![\da-z_])~i',
            function ($matches) {
                $value = ((float) $matches[1]) * -1;
                return $this->simplify($matches[0], new ExpressionFloat($value));
            },
            $expression
        );

        //float positive
        $expression = preg_replace_callback(
            '~(?<![\da-z_])(((0\.)|([1-9]\d*\.))\d+)(?![\da-z_])~i',
            function ($matches) {
                $value = (float) $matches[0];
                return $this->simplify($matches[0], new ExpressionFloat($value));
            },
            $expression
        );

        //integer negative
        $expression = preg_replace_callback(
            '~(?<![\da-z_])(?:\(-)((?:0)|(?:[1-9]\d*))(?![\d])(?:\))(?![\da-z_])~i',
            function ($matches) {
                $value = ((int) $matches[1]) * -1;
                return $this->simplify($matches[0], new ExpressionInteger($value));
            },
            $expression
        );

        //integer positive
        $expression = preg_replace_callback(
            '~(?<![\da-z_])((0)|([1-9]\d*))(?![\d])(?![\da-z_])~i',
            function ($matches) {
                $value = (int) $matches[0];
                return $this->simplify($matches[0], new ExpressionInteger($value));
            },
            $expression
        );

        return $expression;
    }


    private function prepareConstants(string $expression): string
    {
        foreach ($this->constants as $name => $value) {
            $regexp = '~(?<![a-z\d_])' . preg_quote($name) . '(?![a-z\d_])~i';
            $expression = preg_replace_callback(
                $regexp,
                function ($matches) use ($name, $value) {
                    return $this->simplify($matches[0], new ExpressionConstant($name, $value));
                },
                $expression
            );
        }
        return $expression;
    }

    private function calcFunctions(string $expression, array $context): string
    {
        foreach ($this->functions as $function) {
            $name = $function->getName();

            $exactlyFunctionName = '(?<![a-z\d_])' . preg_quote($name);
            $arrayArguments = '\(((?:`[a-f\d]{32}`){1}(?:,`[a-f\d]{32}`)*)\)';
            $namedArguments = '\(((?:[a-z\d_]+:`[a-f\d]{32}`)(?:,[a-z\d_]+:`[a-f\d]{32}`)*)\)';

            $regexp = "~{$exactlyFunctionName}(?:(?:{$arrayArguments})|(?:(?:{$namedArguments})))~ui";

            $matches = [];
            while (preg_match($regexp, $expression, $matches)) {

                if (!empty($matches[1])) {
                    $arguments = array_map(function ($token) {
                        return $token;
                    }, explode(',', $matches[1]));
                } else {
                    $arguments = array_map(function ($token) {
                        [$key, $value] = explode(':', $token);
                        return [
                            'key' => $key,
                            'value' => $value,
                        ];
                    }, explode(',', $matches[2]));

                    $arguments = array_combine(
                        array_column($arguments, 'key'),
                        array_column($arguments, 'value')
                    );
                }

                $expr = $matches[0];
                $value = $function->execute(array_map(function ($value) {
                    return $this->recall($value);
                }, $arguments), $context);
                $component = new ExpressionFunction($function->getName(), $arguments, $value);

                $expression = str_replace(
                    $expr,
                    $this->simplify($expr, $component),
                    $expression
                );
            }
        }
        return $expression;
    }

    private function calcOperations(string $expression, array $context): string
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
                    $value = $operator->execute($leftOperand, $rightOperand, $context);
                    $component = new ExpressionOperation($operator->operator(), $matches[1], $matches[2], $value);
                    $token = $this->simplify($matches[0], $component);
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

    private function simplify($expression, ExpressionComponentInterface $component): string
    {
        $hash = md5($expression);
        $this->simplifications[$hash] = $component;
        return '`' . $hash . '`';
    }

    private function recall($token)
    {
        $token = trim($token, '` ');
        return $this->simplifications[$token]->getValue();
    }

}
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
     * @var array
     */
    private $constants;

    /**
     * Executor constructor.
     * @param FunctionInterface[]|callable[] $functions
     * @param OperatorInterface[] $operators
     * @param array|callable $variables
     * @param array $constants
     * @throws ExecutorException
     */
    public function __construct(
        array $functions,
        array $operators,
        $variables = null,
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



        if (is_array($variables)) {
            foreach ($variables as $name => $value) {
                if (!preg_match('~^[a-z\d_\.]+$~i', $name)) {
                    throw new ExecutorException("Invalid variable name «{$name}»: name should be match [a-z\d_\.]+", 3);
                }
            }
        }
        $this->variables = $variables ?? [];


        foreach ($constants as $name => $value) {
            if (!preg_match('~^[a-z_]{1}[a-z\d_]*$~i', $name)) {
                throw new ExecutorException("Invalid constant name «{$name}»: name should be match ^[a-z_]{1}[a-z\d_]*$", 4);
            }
        }
        $this->constants = $constants;
    }

    /**
     * @param string $expression
     * @return null|string|string[]
     * @throws SyntaxException
     */
    public function execute(string $expression)
    {
        $this->guardToken($expression);
        $expression = $this->prepareStrings($expression);
        $expression = $this->prepareVariables($expression);
        $expression = $this->prepareConstants($expression);
        $expression = $this->prepareNumbers($expression);
        $expression = preg_replace('/\s+/u', '', $expression);
        $expression = $this->calculate($expression);

        if (preg_match('~^`[a-f\d]{32}`$~', $expression)) {
            $result = $this->recall($expression);
            $this->memory = [];
            return $result;
        }

        throw new SyntaxException('Unexpected execution exception');
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

            if (is_callable($this->variables)) {
                $value = ($this->variables)($matches[1]);
            } else {
                $value = $this->variables[$matches[1]];
                if (is_callable($value)) {
                    $value = $value($matches[1]);
                }
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
    private function prepareStrings(string $expression): string
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
                return $this->remember($matches[0], ((float) $matches[1]) * -1);
            },
            $expression
        );

        //float positive
        $expression = preg_replace_callback(
            '~(?<![\da-z_])(((0\.)|([1-9]\d*\.))\d+)(?![\da-z_])~i',
            function ($matches) {
                return $this->remember($matches[0], (float) $matches[0]);
            },
            $expression
        );

        //integer negative
        $expression = preg_replace_callback(
            '~(?<![\da-z_])(?:\(-)((?:0)|(?:[1-9]\d*))(?![\d])(?:\))(?![\da-z_])~i',
            function ($matches) {
                return $this->remember($matches[0], ((int) $matches[1]) * -1);
            },
            $expression
        );

        //integer positive
        $expression = preg_replace_callback(
            '~(?<![\da-z_])((0)|([1-9]\d*))(?![\d])(?![\da-z_])~i',
            function ($matches) {
                return $this->remember($matches[0], (int) $matches[0]);
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
                function ($matches) use ($value) {
                    return $this->remember($matches[0], $value);
                },
                $expression
            );
        }
        return $expression;
    }

    private function calcFunctions(string $expression): string
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

                $value = $function->execute($arguments);

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
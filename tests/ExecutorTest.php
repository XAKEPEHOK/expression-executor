<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 23:32
 */

namespace XAKEPEHOK\ExpressionExecutor;

use PHPUnit\Framework\TestCase;
use XAKEPEHOK\ExpressionExecutor\Exceptions\ExecutorException;
use XAKEPEHOK\ExpressionExecutor\Exceptions\SyntaxException;

class ExecutorTest extends TestCase
{

    /** @var Executor */
    private $executor;

    /** @var FunctionInterface */
    private $func_min;

    /** @var FunctionInterface */
    private $func_max;

    /** @var FunctionInterface */
    private $func_length;

    /** @var FunctionInterface[] */
    private $functions;

    /** @var OperatorInterface */
    private $operator_plus;

    /** @var OperatorInterface */
    private $operator_minus;

    /** @var OperatorInterface */
    private $operator_multiply;

    /** @var OperatorInterface */
    private $operator_divide;

    /** @var OperatorInterface */
    private $operator_divide_alt;

    /** @var OperatorInterface[] */
    private $operators;

    private $vars = [];

    private $constants = [];

    public function __construct(?string $name = null, array $data = [], string $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->setUp();
    }

    protected function setUp()
    {
        parent::setUp();

        $this->func_min = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'MIN';
            }

            public function execute(array $arguments)
            {
                return min($arguments);
            }
        };
        $this->func_max = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'MAX';
            }

            public function execute(array $arguments)
            {
                return max($arguments);
            }
        };
        $this->func_length = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'LENGTH';
            }

            public function execute(array $arguments)
            {
                if (isset($arguments['string'])) {
                    return mb_strlen($arguments['string']);
                }
                return mb_strlen($arguments[0]);
            }
        };
        $this->functions = [
            $this->func_min,
            $this->func_max,
            $this->func_length
        ];

        $this->operator_plus = new class implements OperatorInterface {

            public function operator(): string
            {
                return '+';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 1;
            }

            public function execute($leftOperand, $rightOperand)
            {
                return $leftOperand + $rightOperand;
            }
        };
        $this->operator_minus = new class implements OperatorInterface {

            public function operator(): string
            {
                return '-';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 1;
            }

            public function execute($leftOperand, $rightOperand)
            {
                return $leftOperand - $rightOperand;
            }
        };
        $this->operator_multiply = new class implements OperatorInterface {

            public function operator(): string
            {
                return '*';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 2;
            }

            public function execute($leftOperand, $rightOperand)
            {
                return $leftOperand * $rightOperand;
            }
        };
        $this->operator_divide = new class implements OperatorInterface {

            public function operator(): string
            {
                return '/';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 2;
            }

            public function execute($leftOperand, $rightOperand)
            {
                return $leftOperand / $rightOperand;
            }
        };
        $this->operator_divide_alt = new class implements OperatorInterface {

            public function operator(): string
            {
                return ':';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 2;
            }

            public function execute($leftOperand, $rightOperand)
            {
                return $leftOperand / $rightOperand;
            }
        };
        $this->operators = [
            $this->operator_plus,
            $this->operator_minus,
            $this->operator_multiply,
            $this->operator_divide,
            $this->operator_divide_alt,
        ];

        $this->vars = [
            'TEN' => 10,
            'FIVE' => 5,
            'STRING' => 'Awesome!',
        ];

        $this->constants = [
            'PI' => 3.14,
            'G' => 9.8,
        ];

        $this->executor = new Executor(
            $this->functions,
            $this->operators,
            $this->vars,
            $this->constants
        );
    }

    public function testConstructInvalidFunctionType()
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(1);
        new Executor([1, 2, 3], $this->operators);
    }

    public function testConstructInvalidFunctionName()
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(11);
        new Executor([
            new class implements FunctionInterface {

                public function getName(): string
                {
                    return 'MIN+';
                }

                public function execute(array $arguments)
                {
                    return min($arguments);
                }
            }
        ], $this->operators);
    }

    public function testConstructInvalidOperatorType()
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(2);
        new Executor($this->functions, [1, 2, 3]);
    }

    public function invalidOperatorNameProvider(): array
    {
        return [
            ['('],
            [')'],
            ['"'],
            [','],
            ['_'],
        ];
    }

    /**
     * @dataProvider invalidOperatorNameProvider
     * @param $operator
     * @throws ExecutorException
     */
    public function testConstructInvalidOperatorName($operator)
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(22);
        new Executor($this->functions, [
            new class($operator) implements OperatorInterface {

                private $operator;

                public function __construct(string $operator)
                {
                    $this->operator = $operator;
                }

                public function operator(): string
                {
                    return $this->operator;
                }

                /**
                 * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
                 * @return int
                 */
                public function priority(): int
                {
                    return 1;
                }

                public function execute($leftOperand, $rightOperand)
                {
                    return true;
                }
            }
        ]);
    }

    public function invalidVariableNameProvider(): array
    {
        return [
            [['hello-world' => 10]],
            [['hello world' => 10]],
            [['hello+world' => 10]],
        ];
    }

    /**
     * @dataProvider invalidVariableNameProvider
     * @param $variables
     * @throws ExecutorException
     */
    public function testConstructInvalidVariableName($variables)
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(3);
        new Executor($this->functions, $this->operators, $variables);
    }

    public function testConstructCallableVariables()
    {
        $executor = new Executor($this->functions, $this->operators, function ($variable) {
            return $variable;
        });

        $this->assertEquals($executor->execute('{{hello}}'), 'hello');
        $this->assertEquals($executor->execute('{{hello.world}}'), 'hello.world');
    }

    public function invalidConstantNameProvider(): array
    {
        return [
            [['hello-world' => 10]],
            [['hello world' => 10]],
            [['hello+world' => 10]],
            [['7HELLO' => 10]],
        ];
    }

    /**
     * @dataProvider invalidConstantNameProvider
     * @param $constants
     * @throws ExecutorException
     */
    public function testConstructInvalidConstantName($constants)
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(4);
        new Executor($this->functions, $this->operators, $this->vars, $constants);
    }

    public function validExpressionProvider(): array
    {
        return [
            ['"HELLO"', 'HELLO'],
            ['"\"HELLO\""', '"HELLO"'],
            ['"`e049f681893a971fb67a1be465808f82`"', '`e049f681893a971fb67a1be465808f82`'],
            ['"+"', '+'],
            ['"LENGTH({{STRING}})"', 'LENGTH({{STRING}})'],
            ['{{STRING}}', $this->vars['STRING']],


            ['LENGTH("HELLO")', mb_strlen('HELLO')],
            ['LENGTH("HELLO") + G * PI', mb_strlen('HELLO') + $this->constants['G'] * $this->constants['PI']],
            ['LENGTH("HELLO") + G * PI', mb_strlen('HELLO') + $this->constants['G'] * $this->constants['PI']],
            ['LENGTH(PI)', mb_strlen($this->constants['PI'])],
            ['LENGTH({{STRING}})', mb_strlen($this->vars['STRING'])],
            ['LENGTH("\"HELLO\"")', mb_strlen('"HELLO"')],
            ['LENGTH(string: "HELLO")', mb_strlen('HELLO')],
            ['LENGTH(string: {{STRING}})', mb_strlen($this->vars['STRING'])],

            ['MIN("1", "2")', min(["1", "2"])],
            ['MIN(value_1: "1", value_2: "2")', min(["1", "2"])],
            ['MIN(value_1: LENGTH({{STRING}}), value_2: {{TEN}})', min([
                mb_strlen($this->vars['STRING']),
                $this->vars['TEN']
            ])],

            ['MIN("2", "3", "4")', min(["2", "3", "4"])],
            ['MIN({{TEN}}, "3", LENGTH({{STRING}}))', min([
                $this->vars['TEN'],
                "3",
                mb_strlen($this->vars['STRING'])
            ])],

            [
                'MIN(MAX(MIN("1", "2"), "3"), MIN(MAX("5", {{TEN}}), "2"))',
                min(max(min("1", "2"), "3"), min(max("5", $this->vars['TEN']), "2"))
            ],
            ['MIN(value_1: "8" : "2", value_2: "5") : "2"', min(["8" / "2", "5"]) / "2"],

            ['"2" + "3"', 5],
            ['"2" + "3" * "2"', 8],
            ['("2" + "3") * "2"', 10],
            [
                '({{TEN}} + "3") * MAX("2" + "3", LENGTH(string: {{STRING}}))',
                ($this->vars['TEN'] + "3") * MAX(["2" + "3", mb_strlen($this->vars['STRING'])])
            ],
        ];
    }

    /**
     * @dataProvider validExpressionProvider
     * @param string $expression
     * @param $expected
     * @throws Exceptions\SyntaxException
     */
    public function testExecute(string $expression, $expected)
    {
        $actual = $this->executor->execute($expression);
        $this->assertEquals($expected, $actual);
    }

    public function invalidExpressionProvider(): array
    {
        return [
            ['+'],
            ['-'],
            ['*'],
            ['`e049f681893a971fb67a1be465808f82`'],
            ['"2" ~ "3"'],
            ['"2" + "3'],
            ['"2" + 3'],
            ['"2" + 3"'],
            ['UNKNOWN("10")'],
            ['("2" + "3"))'],
            ['(("2" + "3")'],
            ['(("2" + "3"))'],
            ['MIN()'],
            ['MIN("2", value_2: "3")'],
            ['"2" + "3"()'],
            ['"2" + "3" ()'],
            ['()'],
        ];
    }


    /**
     * @dataProvider invalidExpressionProvider
     * @param string $expression
     * @throws Exceptions\SyntaxException
     */
    public function testExecuteInvalid(string $expression)
    {
        $this->expectException(SyntaxException::class);
        $this->executor->execute($expression);
    }


}

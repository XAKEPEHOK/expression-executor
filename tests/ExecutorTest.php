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
    private Executor $executor;

    /** @var FunctionInterface */
    private $func_min;

    /** @var FunctionInterface */
    private $func_max;

    /** @var FunctionInterface */
    private $func_length;

    /** @var FunctionInterface */
    private $func_sqr;

    /** @var FunctionInterface */
    private $func_context;

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

    /** @var OperatorInterface */
    private $operator_context;

    /** @var OperatorInterface */
    private $operator_in;

    private $operator_equals;

    private $operator_great_than;

    private $operator_less_than;

    /** @var OperatorInterface[] */
    private $operators;

    /** @var callable */
    private $vars;

    private $constants = [];

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->setUp();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->func_min = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'MIN';
            }

            public function execute(array $arguments, array $context)
            {
                return min($arguments);
            }
        };
        $this->func_max = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'MAX';
            }

            public function execute(array $arguments, array $context)
            {
                return max($arguments);
            }
        };
        $this->func_length = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'LENGTH';
            }

            public function execute(array $arguments, array $context)
            {
                if (isset($arguments['string'])) {
                    return mb_strlen($arguments['string']);
                }
                return mb_strlen($arguments[0]);
            }
        };
        $this->func_sqr = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'SQR';
            }

            public function execute(array $arguments, array $context)
            {
                return $arguments[0] * $arguments[0];
            }
        };
        $this->func_sqrt = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'SQRT';
            }

            public function execute(array $arguments, array $context)
            {
                return intval(sqrt($arguments[0]));
            }
        };
        $this->func_context = new class implements FunctionInterface {

            public function getName(): string
            {
                return 'CONTEXT';
            }

            public function execute(array $arguments, array $context)
            {
                return $context[$arguments[0]];
            }
        };
        $this->functions = [
            $this->func_min,
            $this->func_max,
            $this->func_length,
            $this->func_sqr,
            $this->func_sqrt,
            $this->func_context,
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
                return 20;
            }

            public function execute($leftOperand, $rightOperand, array $context)
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
                return 20;
            }

            public function execute($leftOperand, $rightOperand, array $context)
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
                return 30;
            }

            public function execute($leftOperand, $rightOperand, array $context)
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
                return 30;
            }

            public function execute($leftOperand, $rightOperand, array $context)
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
                return 30;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand / $rightOperand;
            }
        };
        $this->operator_context = new class implements OperatorInterface {

            public function operator(): string
            {
                return '#';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 30;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $context[$leftOperand][$rightOperand];
            }
        };
        $this->operator_in = new class implements OperatorInterface {

            public function operator(): string
            {
                return 'IN';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 10;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return (int)in_array($leftOperand, $rightOperand, true);
            }
        };

        $this->operator_and = new class implements OperatorInterface {

            public function operator(): string
            {
                return 'AND';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 1;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand && $rightOperand;
            }
        };

        $this->operator_or = new class implements OperatorInterface {

            public function operator(): string
            {
                return 'OR';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 1;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand || $rightOperand;
            }
        };

        $this->operator_equals = new class implements OperatorInterface {

            public function operator(): string
            {
                return '==';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 5;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand === $rightOperand;
            }
        };

        $this->operator_great_than = new class implements OperatorInterface {

            public function operator(): string
            {
                return '>';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 5;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand > $rightOperand;
            }
        };

        $this->operator_less_than = new class implements OperatorInterface {

            public function operator(): string
            {
                return '<';
            }

            /**
             * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
             * @return int
             */
            public function priority(): int
            {
                return 5;
            }

            public function execute($leftOperand, $rightOperand, array $context)
            {
                return $leftOperand < $rightOperand;
            }
        };

        $this->operators = [
            $this->operator_plus,
            $this->operator_minus,
            $this->operator_multiply,
            $this->operator_divide,
            $this->operator_divide_alt,
            $this->operator_context,
            $this->operator_in,
            $this->operator_and,
            $this->operator_or,
            $this->operator_equals,
            $this->operator_great_than,
            $this->operator_less_than,
        ];

        $this->vars = function ($name, array $context = []) {
            $vars = [
                'TEN' => 10,
                'NINE' => 9,
                'FIVE' => 5,
                'STRING' => 'Awesome!',
                'STRING.EMPTY_1' => '',
                'STRING.EMPTY_2' => '',
                'STRING.VALUE_1' => '1',
                'STRING.VALUE_2' => '2',
                'STRING.QUOTES' => '"HELLO"',
                'CONTEXT.VALUE' => $context['VALUE'] ?? null
            ];
            return $vars[$name];
        };

        $this->constants = [
            'PI' => 3.14,
            'G' => 9.8,
            'TRUE' => true,
            'FALSE' => false,
        ];

        $this->executor = new Executor(
            $this->functions,
            $this->operators,
            $this->vars,
            $this->constants
        );
    }

    public function testConstructInvalidFunctionType(): void
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(1);
        new Executor([1, 2, 3], $this->operators);
    }

    public function testConstructInvalidFunctionName(): void
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(11);
        new Executor([
            new class implements FunctionInterface {

                public function getName(): string
                {
                    return 'MIN+';
                }

                public function execute(array $arguments, array $context)
                {
                    return min($arguments);
                }
            }
        ], $this->operators);
    }

    public function testConstructInvalidOperatorType(): void
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
    public function testConstructInvalidOperatorName($operator): void
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

                public function execute($leftOperand, $rightOperand, array $context)
                {
                    return true;
                }
            }
        ]);
    }

    public function invalidConstantNameProvider(): array
    {
        return [
            [['hello-world' => 10]],
            [['hello world' => 10]],
            [['hello+world' => 10]],
            [['7HELLO' => 10]],
            [['7.5HELLO' => 10]],
        ];
    }

    /**
     * @dataProvider invalidConstantNameProvider
     * @param $constants
     * @throws ExecutorException
     */
    public function testConstructInvalidConstantName($constants): void
    {
        $this->expectException(ExecutorException::class);
        $this->expectExceptionCode(4);
        new Executor($this->functions, $this->operators, $this->vars, $constants);
    }

    public function validExpressionProvider(): array
    {
        $examples = [
            ['"HELLO"', 'HELLO'],
            ['" HELLO "', ' HELLO '],
            ['"\"HELLO\""', '"HELLO"'],
            ['"\" HELLO \""', '" HELLO "'],
            ['"`e049f681893a971fb67a1be465808f82`"', '`e049f681893a971fb67a1be465808f82`'],
            ['"+"', '+'],
            ['"LENGTH({{STRING}})"', 'LENGTH({{STRING}})'],
            ['LENGTH("\"{{TEN}}\"")', 9],
            ['LENGTH("LENGTH(\"{{TEN}}\")")', 17],
            ['{{STRING}}', ($this->vars)('STRING')],
            ['""', ''],

            ['LENGTH("HELLO")', mb_strlen('HELLO')],
            ['LENGTH( "HELLO" )', mb_strlen('HELLO')],
            ['LENGTH("HELLO") + G * PI', mb_strlen('HELLO') + $this->constants['G'] * $this->constants['PI']],
            ['LENGTH("HELLO") + G * PI', mb_strlen('HELLO') + $this->constants['G'] * $this->constants['PI']],
            ['LENGTH(PI)', mb_strlen($this->constants['PI'])],
            ['LENGTH({{STRING}})', mb_strlen(($this->vars)('STRING'))],
            ['LENGTH( {{STRING}} )', mb_strlen(($this->vars)('STRING'))],
            ['LENGTH("\"HELLO\"")', mb_strlen('"HELLO"')],
            ['LENGTH(string: "HELLO")', mb_strlen('HELLO')],
            ['LENGTH( string : "HELLO" )', mb_strlen('HELLO')],
            ['LENGTH(string: {{STRING}})', mb_strlen(($this->vars)('STRING'))],
            ['LENGTH( string : {{STRING}} )', mb_strlen(($this->vars)('STRING'))],

            ['MIN("1", "2")', min(["1", "2"])],
            ['MIN( "1"  ,  "2" )', min(["1", "2"])],
            ['MIN(1, 2)', min([1, 2])],
            ['MIN( 1 , 2 )', min([1, 2])],
            ['MIN(1.2, 2.3)', min([1.2, 2.3])],
            ['MIN( 1.2 , 2.3 )', min([1.2, 2.3])],
            ['MIN((-1), (-2))', min([-1, -2])],
            ['MIN( ( -1 ) , ( -2 ) )', min([-1, -2])],
            ['MIN( (-1.2) , (-2.3) )', min([-1.2, -2.3])],

            ['MIN(value_1: "1", value_2: "2")', min(["1", "2"])],
            ['MIN(value_1: 1, value_2: 2)', min([1, 2])],
            ['MIN(value_1: 0.1, value_2: 1.2)', min([0.1, 1.2])],
            ['MIN(value_1: (-1), value_2: (-2))', min([-1, -2])],
            ['MIN( value_1 : ( -1 ) , value_2 : ( -2 ) )', min([-1, -2])],
            ['MIN(value_1: (-0.1), value_2: (-1.2))', min([-0.1, -1.2])],
            ['MIN( value_1 : ( -0.1 ) , value_2 : ( -1.2 ) )', min([-0.1, -1.2])],

            ['MIN(value_1: LENGTH({{STRING}}), value_2: {{TEN}})', min([
                mb_strlen(($this->vars)('STRING')),
                    ($this->vars)('TEN')
            ])],

            ['MIN("2", "3", "4")', min(["2", "3", "4"])],
            ['MIN({{TEN}}, "3", LENGTH({{STRING}}))', min([
                ($this->vars)('TEN'),
                "3",
                mb_strlen(($this->vars)('STRING'))
            ])],

            [
                'MIN(MAX(MIN("1", "2"), "3"), MIN(MAX("5", {{TEN}}), "2"))',
                min(max(min("1", "2"), "3"), min(max("5", ($this->vars)('TEN')), "2"))
            ],

            [
                'MIN(MAX(MIN("1", "2"), "3"), MIN(MAX("5", {{TEN}}), "2"))',
                min(max(min("1", "2"), "3"), min(max("5", ($this->vars)('TEN')), "2"))
            ],
            ['MIN(value_1: "8" : "2", value_2: "5") : "2"', min(["8" / "2", "5"]) / "2"],
            ['MIN(value_1: 8 : 2, value_2: 5 + 1.32) : 2.5', min([8 / 2, 5 + 1.32]) / 2.5],

            ['"2" + "3"', 5],
            ['2 + 3', 5],
            ['  2   +   3  ', 5],
            ['2.2 + 3.3', 5.5],

            ['"2" + "3" * "2"', 2 + 3 * 2],
            ['2 + 3 * 2', 2 + 3 * 2],
            ['2.2 + 3.3 * 2.2', 2.2 + 3.3 * 2.2],

            ['("2" + "3") * "2"', (2 + 3) * 2],
            ['(2 + 3) * 2', (2 + 3) * 2],
            ['( 2 + 3 ) * 2', (2 + 3) * 2],
            ['(2 + 3)', (2 + 3)],
            ['(2.2 + 3.3) * 2.2', (2.2 + 3.3) * 2.2],
            ['(2.2 + 3.3 * 2.2)', (2.2 + 3.3 * 2.2)],
            ['(2.2 + 3.3 * 2.2 + 56) + (2.2 + 3.3 * 2.2 + (2.2 + 3.3 * 2.2 + 56))', (2.2 + 3.3 * 2.2 + 56) + (2.2 + 3.3 * 2.2 + (2.2 + 3.3 * 2.2 + 56))],
            ['(2.2 + 3.3 * 2.2 + 56) + (2.2 + 3.3 * 2.2 + (2.2 + 3.3 * 2.2 + LENGTH("LENGTH(\"{{TEN}}\")")))', (2.2 + 3.3 * 2.2 + 56) + (2.2 + 3.3 * 2.2 + (2.2 + 3.3 * 2.2 + strlen("LENGTH(\"{{TEN}}\")")))],

            ['SQR( {{TEN}} - SQRT( {{NINE}} ) )', 49],


            [
                '({{TEN}} + 3.3) * MAX(2 + 3, LENGTH(string: {{STRING}}))',
                (($this->vars)('TEN') + 3.3) * MAX([2 + 3, mb_strlen(($this->vars)('STRING'))])
            ],

            ['5 + {{CONTEXT.VALUE}}', 15, ['VALUE' => 10]],
            ['CONTEXT("value")', 10, ['value' => 10]],
            ['"first" # "second"', "success", ['first' => [
                'second' => "success",
            ]]],

            ['["HELLO", "MY", "WORLD"]', ['HELLO', 'MY', 'WORLD']],
            ['[ "HELLO" , "MY" , "WORLD" ]', ['HELLO', 'MY', 'WORLD']],
            ['["HELLO", "MY", "WORLD",]', ['HELLO', 'MY', 'WORLD']],
            ['["Age", "is", 25]', ['Age', 'is', 25]],
            ['["Age", "is", 25 + 5]', ['Age', 'is', 30]],
            ['[25 + 5 * 2, "is not", (25 + 5) * 2]', [35, 'is not', 60]],
            ['[]', []],

            ['"MY" IN ["HELLO", "MY", "WORLD"]', 1],
            ['"HIS" IN ["HELLO", "MY", "WORLD"]', 0],
            ['35 IN [25 + 5 * 2, "is not", (25 + 5) * 2]', 1],
            ['"35" IN [25 + 5 * 2, "is not", (25 + 5) * 2]', 0],
            ['"is not" IN [25 + 5 * 2, "is not", (25 + 5) * 2]', 1],
            ['30+30 IN [25 + 5 * 2, "is not", (25 + 5) * 2]', 1],
            ['"60" IN [25 + 5 * 2, "is not", (25 + 5) * 2]', 0],

            ['TRUE AND TRUE', true],
            ['TRUE OR TRUE', true],
            ['TRUE AND FALSE', false],
            ['TRUE OR FALSE', true],
            ['FALSE OR FALSE', false],

            ['{{NINE}} == 9', true],
            ['{{TEN}} == 10', true],
            ['{{TEN}} == 11', false],

            ['{{TEN}} == 10 AND {{NINE}} == 9', true],
            ['{{STRING.EMPTY_1}} == "" AND {{STRING.EMPTY_2}} == ""', true],
            ['{{STRING.VALUE_1}} == "1" AND {{STRING.VALUE_2}} == "2"', true],
            ['{{STRING.VALUE_1}} == "1" AND {{STRING.EMPTY_2}} == "" AND {{STRING.QUOTES}} == "\"HELLO\""', true],
            ['{{STRING.VALUE_1}} == "1" AND LENGTH({{STRING.VALUE_2}}) > 0', true]
        ];

        return array_combine(array_column($examples, 0), $examples);
    }

    /**
     * @dataProvider validExpressionProvider
     * @param string $expression
     * @param $expected
     * @param array $context
     * @throws SyntaxException
     */
    public function testExecute(string $expression, $expected, array $context = []): void
    {
        $actual = $this->executor->execute($expression, $context);
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
            ['"2" + 3"'],
            ['UNKNOWN("10")'],
            ['("2" + "3"))'],
            ['(("2" + "3")'],
            ['MIN()'],
            ['MIN("2", value_2: "3")'],
            ['"2" + "3"()'],
            ['"2" + "3" ()'],
            ['()'],
            ['[,]'],
        ];
    }


    /**
     * @dataProvider invalidExpressionProvider
     * @param string $expression
     * @throws Exceptions\SyntaxException
     */
    public function testExecuteInvalid(string $expression): void
    {
        $this->expectException(SyntaxException::class);
        $this->executor->execute($expression);
    }


}

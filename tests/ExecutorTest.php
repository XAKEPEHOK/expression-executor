<?php
/**
 * @author Timur Kasumov (aka XAKEPEHOK)
 * Datetime: 18.01.2019 23:32
 */

namespace XAKEPEHOK\ExpressionExecutor;

use PHPUnit\Framework\TestCase;

class ExecutorTest extends TestCase
{

    /** @var Executor */
    private $executor;

    protected function setUp()
    {
        parent::setUp();

        $min = new class implements FunctionInterface {
            public function getName(): string
            {
                return 'MIN';
            }

            public function getArgumentsCount(): int
            {
                return 2;
            }

            public function execute(array $arguments)
            {
                return min($arguments);
            }
        };

        $this->executor = new Executor(
            [
                $min,
                'MAX' => function (array $arguments) {
                    return max($arguments);
                },
                'LENGTH' => function (array $arguments) {
                    if (isset($arguments['string'])) {
                        return mb_strlen($arguments['string']);
                    }
                    return mb_strlen($arguments[0]);
                }
            ],
            [
                new class implements OperatorInterface {

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
                },
                new class implements OperatorInterface {

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
                },
            ],
            [
            'TEN' => 10,
                'FIVE' => 5,
            ]
        );
    }

    public function testExecute()
    {
        $result = $this->executor->execute('("10" + "10") * LENGTH(string:"QA") + "10" * "2" + MIN(    MAX("1", {{TEN}}) * "2", {{FIVE}}) + MAX(    MIN("1", LENGTH("PRIVET \"TIMUR\"")), ("50" + "50") * "2")');
        echo $result;
    }

}

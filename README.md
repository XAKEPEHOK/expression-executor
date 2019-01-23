# ExpressionExecutor [![Build Status](https://travis-ci.com/XAKEPEHOK/expression-executor.svg?branch=master)](https://travis-ci.com/XAKEPEHOK/expression-executor)

Expression executor, which allows to implement domain-specific language. This lib doesn’t contain any
implemented operators or functions. Its only a framework, which allows you to build your own domain-specific 
language for expressions, with any functions, operators and typing system.

You can define your own operator, functions and variables. For example, you want to calc/execute expressions
like: 
```
MIN(5, 10.5) + NUMBER_OF_DAY(year: "2019", month: "01", day: "20") + PI * {{VARIABLE}} + ((-2) + 2.5) * 2
``` 
In example above
- `MIN` and `NUMBER_OF_DAY` - functions
- `{{VARIABLE}}` - variable
- `PI` - syntax user-defined constant (for example, you can define TRUE, FALSE and NULL constants)
- `+` and `*` - operators
-  `"5"`, `"10"`, `"2019"` - strings in double-quotes
-  `5`, `10.5`, `(-2)`, `2.5`, `2` - int/float as is, but negative values should be wrapped in brackets

### Installation:
```bash
composer require xakepehok/expression-executor
```

### Usage

In order to calc/execute expressions above you need to define those functions, operators and values

MIN():
```php
<?php
class MinFunction implements \XAKEPEHOK\ExpressionExecutor\FunctionInterface 
{

    public function getName(): string
    {
        return 'MIN';
    }

    public function execute(array $arguments)
    {
        return min($arguments);
    }
}
```

NUMBER_OF_DAY():
```php
<?php
class NumberOfDayFunction implements \XAKEPEHOK\ExpressionExecutor\FunctionInterface 
{

    public function getName(): string
    {
        return 'NUMBER_OF_DAY';
    }

    public function execute(array $arguments)
    {
        $year = $arguments['year'] ?? ($arguments[0] ?? null);
        $month = $arguments['month'] ?? ($arguments[1] ?? null);
        $day = $arguments['day'] ?? ($arguments[2] ?? null);
        
        if ($year === null || $month === null || $day === null) {
            throw new \XAKEPEHOK\ExpressionExecutor\Exceptions\FunctionException('Arguments error');
        }
        
        return date('N', strtotime("{$year}-{$month}-{$day}"));
    }
}
```

`+` operator:
```php
<?php
class PlusOperator implements \XAKEPEHOK\ExpressionExecutor\OperatorInterface 
{
    
    public function operator() : string
    {
        return '+';    
    }
    
    /**
    * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
    * @return int
    */
    public function priority() : int
    {
        return 1;
    }
    
    public function execute($leftOperand, $rightOperand)
    {
        return $leftOperand + $rightOperand;
    }    
}
```

`*` operator:
```php
<?php
class MultiplyOperator implements \XAKEPEHOK\ExpressionExecutor\OperatorInterface 
{
    
    public function operator() : string
    {
        return '*';    
    }
    
    /**
    * Custom integer priority value. For example, for "+" it can be 1, for "*" it can be 2
    * @return int
    */
    public function priority() : int
    {
        return 2;
    }
    
    public function execute($leftOperand, $rightOperand)
    {
        return $leftOperand * $rightOperand;
    }    
}
```

Create executor instance:
```php
<?php
$executor = new \XAKEPEHOK\ExpressionExecutor\Executor(
    [new MinFunction(), new NumberOfDayFunction()],
    [new PlusOperator(), new MultiplyOperator()],
    function ($name, array $context) {
        $vars = [
            'VARIABLE' => 10,
            'CONTEXT.VALUE' => $context['value'],
        ];
        return $vars[$name];
    },
    ['PI' => 3.14]
);

//And simply execute our expression 
$result = $executor->execute('MIN(5, 10.5) + NUMBER_OF_DAY(year: "2019", month: "01", day: "20") + PI * {{VARIABLE}} + ((-2) + 2.5) * 2');
```

### Features
- Its safe. No `eval()`
- Executor can return and work with any types of data. All types checking and manipulating should be implemented
in your classes (functions and operators)
- String arguments support escaped double quotes, for example `"My name is \"Timur\""`
- Functions accept any count of arguments (you can limit in function body by exceptions)
- Functions arguments can be named `NUMBER_OF_DAY(year: "2019", month: "01", day: "20")` and unnamed
NUMBER_OF_DAY("2019", "01", "20"), but not combined
- Function arguments can be strings, numbers, variables, constants, other functions result and any expressions
- You can pass context (any common data as array) as second param for `execute()` method. Context will be passed to
functions, operators and variables callable
- Use brackets `(2 + 2) * 2` for priority
- Use brackets for negative numbers, such as `(-1)`, `(-1.2)`
- You can implement any operator, such as `>`, `>=`, `<`, `<=` and any what you want and desire

See [ExecutorTest.php](tests/ExecutorTest.php) for more examples.

### Differences from analogues
- https://symfony.com/doc/current/components/expression_language.html - great symfony component for
expressions, but it is impossible to override logic of any built-in operators and also impossible use
your own strict type system. Only one way to extend - define your own function
- https://github.com/NeonXP/MathExecutor - good math expressions calculator with user-defined operators
and functions, but it is also impossible to use your own strict type system. For example, you can't do something 
like Datetime - Datetime (with type saving)


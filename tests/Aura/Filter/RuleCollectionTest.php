<?php
namespace Aura\Filter;

use Aura\Filter\RuleCollection as Filter;

class RuleCollectionTest extends \PHPUnit_Framework_TestCase
{
    protected $filter;
    
    protected function setUp()
    {
        $rule_locator = new RuleLocator([
            'alnum'     => function() { return new Rule\Alnum; },
            'alpha'     => function() { return new Rule\Alpha; },
            'between'   => function() { return new Rule\Between; },
            'blank'     => function() { return new Rule\Blank; },
            'int'       => function() { return new Rule\Int; },
            'max'       => function() { return new Rule\Max; },
            'min'       => function() { return new Rule\Min; },
            'regex'     => function() { return new Rule\Regex; },
            'string'    => function() { return new Rule\String; },
            'strlen'    => function() { return new Rule\Strlen; },
            'strlenMin' => function() { return new Rule\StrlenMin; },
        ]);
        
        $intl = require dirname(dirname(dirname(__DIR__)))
              . DIRECTORY_SEPARATOR . 'intl'
              . DIRECTORY_SEPARATOR . 'en_US.php';
        
        $translator = new Translator($intl);
        
        $this->filter = new Filter($rule_locator, $translator);
    }
    
    public function testValue()
    {
        // validate
        $actual = 'abc123def';
        $this->assertTrue($this->filter->value($actual, Filter::IS, 'alnum'));
        
        // sanitize in place
        $expect = 123;
        $this->assertTrue($this->filter->value($actual, Filter::FIX, 'int'));
        $this->assertSame(123, $actual);
    }
    
    public function testGetTranslator()
    {
        $actual = $this->filter->getTranslator();
        $expect = 'Aura\Filter\Translator';
        $this->assertInstanceOf($expect, $actual);
    }
    
    public function testGetRuleLocator()
    {
        $actual = $this->filter->getRuleLocator();
        $expect = 'Aura\Filter\RuleLocator';
        $this->assertInstanceOf($expect, $actual);
    }
    
    public function testAddAndGetRules()
    {
        $this->filter->addSoftRule('field1', Filter::IS, 'alnum');
        $this->filter->addHardRule('field1', Filter::IS, 'alpha');
        
        $this->filter->addSoftRule('field2', Filter::IS, 'alnum');
        $this->filter->addHardRule('field2', Filter::IS, 'alpha');
        
        $actual = $this->filter->getRules();
        $expect = [
            0 => [
                'field' => 'field1',
                'method' => 'is',
                'name' => 'alnum',
                'params' => [],
                'type' => Filter::SOFT_RULE,
            ],
            1 => [
                'field' => 'field1',
                'method' => 'is',
                'name' => 'alpha',
                'params' => [],
                'type' => Filter::HARD_RULE,
            ],
            2 => [
                'field' => 'field2',
                'method' => 'is',
                'name' => 'alnum',
                'params' => [],
                'type' => Filter::SOFT_RULE,
            ],
            3 => [
                'field' => 'field2',
                'method' => 'is',
                'name' => 'alpha',
                'params' => [],
                'type' => Filter::HARD_RULE,
            ],
        ];
        
        $this->assertSame($expect, $actual);
    }

    public function testValues()
    {
        $this->filter->addSoftRule('field', Filter::IS, 'alnum');
        $this->filter->addHardRule('field', Filter::IS, 'strlenMin', 6);
        
        $data = (object) ['field' => 'foobar'];
        $result = $this->filter->values($data);
        $this->assertTrue($result);
        $messages = $this->filter->getMessages();
        $this->assertTrue(empty($messages));
    }
    
    public function testValues_invalidArgument()
    {
        $this->setExpectedException('InvalidArgumentException');
        $data = 'string';
        $this->filter->values($data);
    }
    
    public function testValues_hardRule()
    {
        $this->filter->addHardRule('field', Filter::IS, 'alnum');
        $this->filter->addHardRule('field', Filter::IS, 'strlenMin', 6);
        
        $data = (object) ['field' => array()];
        $result = $this->filter->values($data);
        $this->assertFalse($result);
        
        $expect = [
            'field' => [
                'Please use only alphanumeric characters.',
            ],
        ];

        $actual = $this->filter->getMessages();
        $this->assertSame($expect, $actual);
        
        $actual = $this->filter->getMessages('field');
        $expect = [
            'Please use only alphanumeric characters.',
        ];
        $this->assertSame($expect, $actual);
        
        $expect = [];
        $actual = $this->filter->getMessages('no-such-field');
        $this->assertSame($expect, $actual);
    }

    public function testValues_softRule()
    {
        $this->filter->addSoftRule('field1', Filter::IS, 'alnum');
        $this->filter->addHardRule('field1', Filter::IS, 'strlenMin', 6);
        $this->filter->addHardRule('field1', Filter::FIX, 'string');
        $this->filter->addHardRule('field2', Filter::IS, 'int');
        $this->filter->addHardRule('field2', Filter::FIX, 'int');
        
        $data = (object) [
            'field1' => array(),
            'field2' => 88
        ];
        
        $result = $this->filter->values($data);
        $this->assertFalse($result);
        
        $expect = [
            'field1' => [
                'Please use only alphanumeric characters.',
                'Please use at least 6 character(s).',
            ],
        ];

        $actual = $this->filter->getMessages();
        $this->assertSame($expect, $actual);
    }
    
    public function testValues_stopRule()
    {
        $this->filter->addSoftRule('field1', Filter::IS, 'alnum');
        $this->filter->addStopRule('field1', Filter::IS, 'strlenMin', 6);
        $this->filter->addHardRule('field2', Filter::IS, 'int');
        
        $data = (object) ['field1' => array()];
        $result = $this->filter->values($data);
        $this->assertFalse($result);
        
        $expect = [
            'field1' => [
                'Please use only alphanumeric characters.',
                'Please use at least 6 character(s).',
            ],
        ];

        $actual = $this->filter->getMessages();
        
        $this->assertSame($expect, $actual);
    }
    
    public function testValues_sanitizesInPlace()
    {
        $this->filter->addHardRule('field', Filter::FIX, 'string', 'foo', 'bar');
        $data = (object) ['field' => 'foo'];
        $result = $this->filter->values($data);
        $this->assertTrue($result);
        $this->assertSame($data->field, 'bar');
    }
    
    public function testValues_missingField()
    {
        $this->filter->addHardRule('field', Filter::IS, 'string');
        $data = (object) ['other_field' => 'foo']; // 'field' is missing
        $result = $this->filter->values($data);
        $this->assertFalse($result);
    }
    
    public function testValues_arraySanitizesInPlace()
    {
        $this->filter->addHardRule('field', Filter::FIX, 'string', 'foo', 'bar');
        $data = ['field' => 'foo'];
        $result = $this->filter->values($data);
        $this->assertTrue($result);
        $this->assertSame($data['field'], 'bar');
    }
    
    public function testUseFieldMessage()
    {
        $this->filter->addSoftRule('field1', Filter::IS, 'alnum');
        $this->filter->addHardRule('field1', Filter::IS, 'strlenMin', 6);
        $this->filter->addHardRule('field1', Filter::FIX, 'string');
        $this->filter->addHardRule('field2', Filter::IS, 'int');
        $this->filter->addHardRule('field2', Filter::FIX, 'int');
        $this->filter->useFieldMessage('field1', 'FILTER_FIELD_FAILURE_FIELD1');
        
        $data = (object) [
            'field1' => array(),
            'field2' => 88
        ];
        
        $result = $this->filter->values($data);
        $this->assertFalse($result);
        
        $expect = [
            'field1' => [
                'FILTER_FIELD_FAILURE_FIELD1',
            ],
        ];

        $actual = $this->filter->getMessages();
        $this->assertSame($expect, $actual);
    }
}

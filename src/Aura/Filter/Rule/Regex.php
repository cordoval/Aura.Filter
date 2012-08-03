<?php
namespace Aura\Filter\Rule;

/**
 * 
 * Sanitizes a value to a string using preg_replace().
 * 
 * @package Aura.Filter
 * 
 * @license http://opensource.org/licenses/bsd-license.php BSD
 * 
 */
class Regex extends AbstractRule
{
    protected $message = 'FILTER_REGEX';
    
    /**
     * 
     * Validates the value against a regular expression.
     * 
     * Uses [[php::preg_match() | ]] to compare the value against the given
     * regular epxression.
     * 
     * @param string $expr The regular expression to validate against.
     * 
     * @return bool True if the value matches the expression, false if not.
     * 
     */
    protected function validate($expr)
    {
        return (bool) preg_match($expr, $this->getValue());
    }
    
    /**
     * 
     * Applies [[php::preg_replace() | ]] to the value.
     * 
     * @param string $expr The regular expression pattern to apply.
     * 
     * @param string $replace Replace the found pattern with this string.
     * 
     * @return bool True if the value was fixed, false if not.
     * 
     */
    protected function sanitize($expr, $replace)
    {
        $value = $this->getValue();
        $this->setValue(preg_replace($expr, $replace, $value));
        return true;
    }
}

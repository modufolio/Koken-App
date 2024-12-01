<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Processes bytes as they pass through a buffer and replaces sequences in it.
 *
 * @author  Chris Corbyn
 */
class Swift_StreamFilters_StringReplacementFilter implements Swift_StreamFilter
{
    /**
     * Create a new StringReplacementFilter with $search and $replace.
     *
     * @param string|array $_search
     * @param string|array $_replace
     */
    public function __construct(private $_search, private $_replace)
    {
    }

    /**
     * Returns true if based on the buffer passed more bytes should be buffered.
     *
     * @param string $buffer
     *
     * @return bool
     */
    #[\Override]
    public function shouldBuffer($buffer)
    {
        $endOfBuffer = substr($buffer, -1);
        foreach ((array) $this->_search as $needle) {
            if (str_contains((string) $needle, $endOfBuffer)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Perform the actual replacements on $buffer and return the result.
     *
     * @param string $buffer
     *
     * @return string
     */
    #[\Override]
    public function filter($buffer)
    {
        return str_replace($this->_search, $this->_replace, $buffer);
    }
}

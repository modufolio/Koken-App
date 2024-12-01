<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Logs to an Array backend.
 *
 * @author     Chris Corbyn
 */
class Swift_Plugins_Loggers_ArrayLogger implements Swift_Plugins_Logger
{
    /**
     * The log contents.
     *
     * @var array
     */
    private $_log = [];

    /**
     * Create a new ArrayLogger with a maximum of $size entries.
     *
     * @var int $size
     * @param int $size
     */
    public function __construct(
        /**
         * Max size of the log.
         */
        private $_size = 50
    )
    {
    }

    /**
     * Add a log entry.
     *
     * @param string $entry
     */
    #[\Override]
    public function add($entry)
    {
        $this->_log[] = $entry;
        while (count($this->_log) > $this->_size) {
            array_shift($this->_log);
        }
    }

    /**
     * Clear the log contents.
     */
    #[\Override]
    public function clear()
    {
        $this->_log = [];
    }

    /**
     * Get this log as a string.
     *
     * @return string
     */
    #[\Override]
    public function dump()
    {
        return implode(PHP_EOL, $this->_log);
    }
}

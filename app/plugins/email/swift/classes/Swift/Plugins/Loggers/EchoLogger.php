<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Prints all log messages in real time.
 *
 * @author     Chris Corbyn
 */
class Swift_Plugins_Loggers_EchoLogger implements Swift_Plugins_Logger
{
    /**
     * Create a new EchoLogger.
     *
     * @param bool $_isHtml
     */
    public function __construct(private $_isHtml = true)
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
        if ($this->_isHtml) {
            printf('%s%s%s', htmlspecialchars($entry, ENT_QUOTES), '<br />', PHP_EOL);
        } else {
            printf('%s%s', $entry, PHP_EOL);
        }
    }

    /**
     * Not implemented.
     */
    #[\Override]
    public function clear()
    {
    }

    /**
     * Not implemented.
     */
    #[\Override]
    public function dump()
    {
    }
}

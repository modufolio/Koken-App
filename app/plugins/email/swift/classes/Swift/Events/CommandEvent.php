<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generated when a command is sent over an SMTP connection.
 *
 * @author     Chris Corbyn
 */
class Swift_Events_CommandEvent extends Swift_Events_EventObject
{
    /**
     * Create a new CommandEvent for $source with $command.
     *
     * @param Swift_Transport $source
     * @param string $_command
     * @param array $_successCodes
     */
    public function __construct(Swift_Transport $source, /**
     * The command sent to the server.
     */
    private $_command, /**
     * An array of codes which a successful response will contain.
     */
    private $_successCodes = [])
    {
        parent::__construct($source);
    }

    /**
     * Get the command which was sent to the server.
     *
     * @return string
     */
    public function getCommand()
    {
        return $this->_command;
    }

    /**
     * Get the numeric response codes which indicate success for this command.
     *
     * @return integer[]
     */
    public function getSuccessCodes()
    {
        return $this->_successCodes;
    }
}

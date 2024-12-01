<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Generated when a response is received on a SMTP connection.
 *
 * @author     Chris Corbyn
 */
class Swift_Events_ResponseEvent extends Swift_Events_EventObject
{
    /**
     * Create a new ResponseEvent for $source and $response.
     *
     * @param Swift_Transport $source
     * @param string $_response
     * @param bool $_valid
     */
    public function __construct(Swift_Transport $source, /**
     * The response received from the server.
     */
    private $_response, /**
     * The overall result.
     */
    private $_valid = false)
    {
        parent::__construct($source);
    }

    /**
     * Get the response which was received from the server.
     *
     * @return string
     */
    public function getResponse()
    {
        return $this->_response;
    }

    /**
     * Get the success status of this Event.
     *
     * @return bool
     */
    public function isValid()
    {
        return $this->_valid;
    }
}

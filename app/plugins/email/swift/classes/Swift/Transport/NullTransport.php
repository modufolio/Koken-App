<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2009 Fabien Potencier <fabien.potencier@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Pretends messages have been sent, but just ignores them.
 *
 * @author  Fabien Potencier
 */
class Swift_Transport_NullTransport implements Swift_Transport
{
    /**
     * Constructor.
     */
    public function __construct(
        /** The event dispatcher from the plugin API */
        private readonly Swift_Events_EventDispatcher $_eventDispatcher
    )
    {
    }

    /**
     * Tests if this Transport mechanism has started.
     *
     * @return bool
     */
    #[\Override]
    public function isStarted()
    {
        return true;
    }

    /**
     * Starts this Transport mechanism.
     */
    #[\Override]
    public function start()
    {
    }

    /**
     * Stops this Transport mechanism.
     */
    #[\Override]
    public function stop()
    {
    }

    /**
     * Sends the given message.
     *
     * @param Swift_Mime_Message $message
     * @param string[]           $failedRecipients An array of failures by-reference
     *
     * @return int     The number of sent emails
     */
    #[\Override]
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        if ($evt = $this->_eventDispatcher->createSendEvent($this, $message)) {
            $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
            if ($evt->bubbleCancelled()) {
                return 0;
            }
        }

        if ($evt) {
            $evt->setResult(Swift_Events_SendEvent::RESULT_SUCCESS);
            $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        }

        $count = (
            count((array) $message->getTo())
            + count((array) $message->getCc())
            + count((array) $message->getBcc())
        );

        return $count;
    }

    /**
     * Register a plugin.
     *
     * @param Swift_Events_EventListener $plugin
     */
    #[\Override]
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->_eventDispatcher->bindEventListener($plugin);
    }
}

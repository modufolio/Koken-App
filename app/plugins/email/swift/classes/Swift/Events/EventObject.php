<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A base Event which all Event classes inherit from.
 *
 * @author     Chris Corbyn
 */
class Swift_Events_EventObject implements Swift_Events_Event
{
    /** The state of this Event (should it bubble up the stack?) */
    private $_bubbleCancelled = false;

    /**
     * Create a new EventObject originating at $source.
     *
     * @param object $_source
     */
    public function __construct(private $_source)
    {
    }

    /**
     * Get the source object of this event.
     *
     * @return object
     */
    #[\Override]
    public function getSource()
    {
        return $this->_source;
    }

    /**
     * Prevent this Event from bubbling any further up the stack.
     *
     * @param bool    $cancel, optional
     */
    #[\Override]
    public function cancelBubble($cancel = true)
    {
        $this->_bubbleCancelled = $cancel;
    }

    /**
     * Returns true if this Event will not bubble any further up the stack.
     *
     * @return bool
     */
    #[\Override]
    public function bubbleCancelled()
    {
        return $this->_bubbleCancelled;
    }
}

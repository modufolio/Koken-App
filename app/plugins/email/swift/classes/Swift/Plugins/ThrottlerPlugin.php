<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Throttles the rate at which emails are sent.
 *
 * @author     Chris Corbyn
 */
class Swift_Plugins_ThrottlerPlugin extends Swift_Plugins_BandwidthMonitorPlugin implements Swift_Plugins_Sleeper, Swift_Plugins_Timer
{
    /** Flag for throttling in bytes per minute */
    public const BYTES_PER_MINUTE = 0x01;

    /** Flag for throttling in emails per second (Amazon SES) */
    public const MESSAGES_PER_SECOND = 0x11;

    /** Flag for throttling in emails per minute */
    public const MESSAGES_PER_MINUTE = 0x10;

    /**
     * The time at which the first email was sent.
     *
     * @var int
     */
    private $_start;

    /**
     * An internal counter of the number of messages sent.
     *
     * @var int
     */
    private $_messages = 0;

    /**
     * Create a new ThrottlerPlugin.
     *
     * @param int $_rate
     * @param int $_mode ,   defaults to {@link BYTES_PER_MINUTE}
     * @param Swift_Plugins_Sleeper $_sleeper (only needed in testing)
     * @param Swift_Plugins_Timer $_timer (only needed in testing)
     */
    public function __construct(
        /**
         * The rate at which messages should be sent.
         */
        private $_rate,
        /**
         * The mode for throttling.
         *
         * This is {@link BYTES_PER_MINUTE} or {@link MESSAGES_PER_MINUTE}
         */
        private $_mode = self::BYTES_PER_MINUTE,
        /**
         * The Sleeper instance for sleeping.
         */
        private readonly ?\Swift_Plugins_Sleeper $_sleeper = null,
        /**
         * The Timer instance which provides the timestamp.
         */
        private readonly ?\Swift_Plugins_Timer $_timer = null
    )
    {
    }

    /**
     * Invoked immediately before the Message is sent.
     *
     * @param Swift_Events_SendEvent $evt
     */
    #[\Override]
    public function beforeSendPerformed(Swift_Events_SendEvent $evt)
    {
        $time = $this->getTimestamp();
        if (!isset($this->_start)) {
            $this->_start = $time;
        }
        $duration = $time - $this->_start;

        $sleep = match ($this->_mode) {
            self::BYTES_PER_MINUTE => $this->_throttleBytesPerMinute($duration),
            self::MESSAGES_PER_SECOND => $this->_throttleMessagesPerSecond($duration),
            self::MESSAGES_PER_MINUTE => $this->_throttleMessagesPerMinute($duration),
            default => 0,
        };

        if ($sleep > 0) {
            $this->sleep($sleep);
        }
    }

    /**
     * Invoked when a Message is sent.
     *
     * @param Swift_Events_SendEvent $evt
     */
    #[\Override]
    public function sendPerformed(Swift_Events_SendEvent $evt)
    {
        parent::sendPerformed($evt);
        ++$this->_messages;
    }

    /**
     * Sleep for $seconds.
     *
     * @param int     $seconds
     */
    #[\Override]
    public function sleep($seconds)
    {
        if (isset($this->_sleeper)) {
            $this->_sleeper->sleep($seconds);
        } else {
            sleep($seconds);
        }
    }

    /**
     * Get the current UNIX timestamp.
     *
     * @return int
     */
    #[\Override]
    public function getTimestamp()
    {
        if (isset($this->_timer)) {
            return $this->_timer->getTimestamp();
        } else {
            return time();
        }
    }

    /**
     * Get a number of seconds to sleep for.
     *
     * @param int     $timePassed
     *
     * @return int
     */
    private function _throttleBytesPerMinute($timePassed)
    {
        $expectedDuration = $this->getBytesOut() / ($this->_rate / 60);

        return (int) ceil($expectedDuration - $timePassed);
    }

    /**
     * Get a number of seconds to sleep for.
     *
     * @param int $timePassed
     *
     * @return int
     */
    private function _throttleMessagesPerSecond($timePassed)
    {
        $expectedDuration = $this->_messages / ($this->_rate);

        return (int) ceil($expectedDuration - $timePassed);
    }

    /**
     * Get a number of seconds to sleep for.
     *
     * @param int     $timePassed
     *
     * @return int
     */
    private function _throttleMessagesPerMinute($timePassed)
    {
        $expectedDuration = $this->_messages / ($this->_rate / 60);

        return (int) ceil($expectedDuration - $timePassed);
    }
}

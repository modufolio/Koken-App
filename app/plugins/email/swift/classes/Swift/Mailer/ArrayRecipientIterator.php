<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Wraps a standard PHP array in an iterator.
 *
 * @author     Chris Corbyn
 */
class Swift_Mailer_ArrayRecipientIterator implements Swift_Mailer_RecipientIterator
{
    /**
     * Create a new ArrayRecipientIterator from $recipients.
     *
     * @param array $_recipients
     */
    public function __construct(
        /**
         * The list of recipients.
         */
        private array $_recipients
    )
    {
    }

    /**
     * Returns true only if there are more recipients to send to.
     *
     * @return bool
     */
    #[\Override]
    public function hasNext()
    {
        return !empty($this->_recipients);
    }

    /**
     * Returns an array where the keys are the addresses of recipients and the
     * values are the names. e.g. ('foo@bar' => 'Foo') or ('foo@bar' => NULL)
     *
     * @return array
     */
    #[\Override]
    public function nextRecipient()
    {
        return array_splice($this->_recipients, 0, 1);
    }
}

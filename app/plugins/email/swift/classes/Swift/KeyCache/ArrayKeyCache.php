<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A basic KeyCache backed by an array.
 *
 * @author     Chris Corbyn
 */
class Swift_KeyCache_ArrayKeyCache implements Swift_KeyCache
{
    /**
     * Cache contents.
     *
     * @var array
     */
    private $_contents = [];

    /**
     * Create a new ArrayKeyCache with the given $stream for cloning to make
     * InputByteStreams.
     *
     * @param Swift_KeyCache_KeyCacheInputStream $_stream
     */
    public function __construct(
        /**
         * An InputStream for cloning.
         */
        private readonly Swift_KeyCache_KeyCacheInputStream $_stream
    )
    {
    }

    /**
     * Set a string into the cache under $itemKey for the namespace $nsKey.
     *
     * @see MODE_WRITE, MODE_APPEND
     *
     * @param string  $nsKey
     * @param string  $itemKey
     * @param string  $string
     * @param int     $mode
     */
    #[\Override]
    public function setString($nsKey, $itemKey, $string, $mode)
    {
        $this->_prepareCache($nsKey);
        switch ($mode) {
            case self::MODE_WRITE:
                $this->_contents[$nsKey][$itemKey] = $string;
                break;
            case self::MODE_APPEND:
                if (!$this->hasKey($nsKey, $itemKey)) {
                    $this->_contents[$nsKey][$itemKey] = '';
                }
                $this->_contents[$nsKey][$itemKey] .= $string;
                break;
            default:
                throw new Swift_SwiftException(
                    'Invalid mode ['.$mode.'] used to set nsKey='.
                    $nsKey.', itemKey='.$itemKey
                );
        }
    }

    /**
     * Set a ByteStream into the cache under $itemKey for the namespace $nsKey.
     *
     * @see MODE_WRITE, MODE_APPEND
     *
     * @param string                 $nsKey
     * @param string                 $itemKey
     * @param Swift_OutputByteStream $os
     * @param int                    $mode
     */
    #[\Override]
    public function importFromByteStream($nsKey, $itemKey, Swift_OutputByteStream $os, $mode)
    {
        $this->_prepareCache($nsKey);
        switch ($mode) {
            case self::MODE_WRITE:
                $this->clearKey($nsKey, $itemKey);
                // no break
            case self::MODE_APPEND:
                if (!$this->hasKey($nsKey, $itemKey)) {
                    $this->_contents[$nsKey][$itemKey] = '';
                }
                while (false !== $bytes = $os->read(8192)) {
                    $this->_contents[$nsKey][$itemKey] .= $bytes;
                }
                break;
            default:
                throw new Swift_SwiftException(
                    'Invalid mode ['.$mode.'] used to set nsKey='.
                    $nsKey.', itemKey='.$itemKey
                );
        }
    }

    /**
     * Provides a ByteStream which when written to, writes data to $itemKey.
     *
     * NOTE: The stream will always write in append mode.
     *
     * @param string                $nsKey
     * @param string                $itemKey
     * @param Swift_InputByteStream $writeThrough
     *
     * @return Swift_InputByteStream
     */
    #[\Override]
    public function getInputByteStream($nsKey, $itemKey, Swift_InputByteStream $writeThrough = null)
    {
        $is = clone $this->_stream;
        $is->setKeyCache($this);
        $is->setNsKey($nsKey);
        $is->setItemKey($itemKey);
        if (isset($writeThrough)) {
            $is->setWriteThroughStream($writeThrough);
        }

        return $is;
    }

    /**
     * Get data back out of the cache as a string.
     *
     * @param string $nsKey
     * @param string $itemKey
     *
     * @return string
     */
    #[\Override]
    public function getString($nsKey, $itemKey)
    {
        $this->_prepareCache($nsKey);
        if ($this->hasKey($nsKey, $itemKey)) {
            return $this->_contents[$nsKey][$itemKey];
        }
    }

    /**
     * Get data back out of the cache as a ByteStream.
     *
     * @param string                $nsKey
     * @param string                $itemKey
     * @param Swift_InputByteStream $is      to write the data to
     */
    #[\Override]
    public function exportToByteStream($nsKey, $itemKey, Swift_InputByteStream $is)
    {
        $this->_prepareCache($nsKey);
        $is->write($this->getString($nsKey, $itemKey));
    }

    /**
     * Check if the given $itemKey exists in the namespace $nsKey.
     *
     * @param string $nsKey
     * @param string $itemKey
     *
     * @return bool
     */
    #[\Override]
    public function hasKey($nsKey, $itemKey)
    {
        $this->_prepareCache($nsKey);

        return array_key_exists($itemKey, $this->_contents[$nsKey]);
    }

    /**
     * Clear data for $itemKey in the namespace $nsKey if it exists.
     *
     * @param string $nsKey
     * @param string $itemKey
     */
    #[\Override]
    public function clearKey($nsKey, $itemKey)
    {
        unset($this->_contents[$nsKey][$itemKey]);
    }

    /**
     * Clear all data in the namespace $nsKey if it exists.
     *
     * @param string $nsKey
     */
    #[\Override]
    public function clearAll($nsKey)
    {
        unset($this->_contents[$nsKey]);
    }

    /**
     * Initialize the namespace of $nsKey if needed.
     *
     * @param string $nsKey
     */
    private function _prepareCache($nsKey)
    {
        if (!array_key_exists($nsKey, $this->_contents)) {
            $this->_contents[$nsKey] = [];
        }
    }
}

<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A KeyCache which streams to and from disk.
 *
 * @author     Chris Corbyn
 */
class Swift_KeyCache_DiskKeyCache implements Swift_KeyCache
{
    /** Signal to place pointer at start of file */
    public const POSITION_START = 0;

    /** Signal to place pointer at end of file */
    public const POSITION_END = 1;

    /** Signal to leave pointer in whatever position it currently is */
    public const POSITION_CURRENT = 2;

    /**
     * Stored keys.
     *
     * @var array
     */
    private $_keys = [];

    /**
     * Will be true if magic_quotes_runtime is turned on.
     *
     * @var bool
     */
    private $_quotes = false;

    /**
     * Create a new DiskKeyCache with the given $stream for cloning to make
     * InputByteStreams, and the given $path to save to.
     *
     * @param Swift_KeyCache_KeyCacheInputStream $_stream
     * @param string $_path to save to
     */
    public function __construct(/**
     * An InputStream for cloning.
     */
    private readonly Swift_KeyCache_KeyCacheInputStream $_stream, /**
     * A path to write to.
     */
    private $_path)
    {
        if (function_exists('get_magic_quotes_runtime') && @get_magic_quotes_runtime() == 1) {
            $this->_quotes = true;
        }
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
     *
     * @throws Swift_IoException
     */
    #[\Override]
    public function setString($nsKey, $itemKey, $string, $mode)
    {
        $this->_prepareCache($nsKey);
        $fp = match ($mode) {
            self::MODE_WRITE => $this->_getHandle($nsKey, $itemKey, self::POSITION_START),
            self::MODE_APPEND => $this->_getHandle($nsKey, $itemKey, self::POSITION_END),
            default => throw new Swift_SwiftException(
                'Invalid mode ['.$mode.'] used to set nsKey='.
                $nsKey.', itemKey='.$itemKey
            ),
        };
        fwrite($fp, $string);
        $this->_freeHandle($nsKey, $itemKey);
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
     *
     * @throws Swift_IoException
     */
    #[\Override]
    public function importFromByteStream($nsKey, $itemKey, Swift_OutputByteStream $os, $mode)
    {
        $this->_prepareCache($nsKey);
        $fp = match ($mode) {
            self::MODE_WRITE => $this->_getHandle($nsKey, $itemKey, self::POSITION_START),
            self::MODE_APPEND => $this->_getHandle($nsKey, $itemKey, self::POSITION_END),
            default => throw new Swift_SwiftException(
                'Invalid mode ['.$mode.'] used to set nsKey='.
                $nsKey.', itemKey='.$itemKey
            ),
        };
        while (false !== $bytes = $os->read(8192)) {
            fwrite($fp, $bytes);
        }
        $this->_freeHandle($nsKey, $itemKey);
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
     *
     * @throws Swift_IoException
     */
    #[\Override]
    public function getString($nsKey, $itemKey)
    {
        $this->_prepareCache($nsKey);
        if ($this->hasKey($nsKey, $itemKey)) {
            $fp = $this->_getHandle($nsKey, $itemKey, self::POSITION_START);
            if ($this->_quotes) {
                ini_set('magic_quotes_runtime', 0);
            }
            $str = '';
            while (!feof($fp) && false !== $bytes = fread($fp, 8192)) {
                $str .= $bytes;
            }
            if ($this->_quotes) {
                ini_set('magic_quotes_runtime', 1);
            }
            $this->_freeHandle($nsKey, $itemKey);

            return $str;
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
        if ($this->hasKey($nsKey, $itemKey)) {
            $fp = $this->_getHandle($nsKey, $itemKey, self::POSITION_START);
            if ($this->_quotes) {
                ini_set('magic_quotes_runtime', 0);
            }
            while (!feof($fp) && false !== $bytes = fread($fp, 8192)) {
                $is->write($bytes);
            }
            if ($this->_quotes) {
                ini_set('magic_quotes_runtime', 1);
            }
            $this->_freeHandle($nsKey, $itemKey);
        }
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
        return is_file($this->_path.'/'.$nsKey.'/'.$itemKey);
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
        if ($this->hasKey($nsKey, $itemKey)) {
            $this->_freeHandle($nsKey, $itemKey);
            unlink($this->_path.'/'.$nsKey.'/'.$itemKey);
        }
    }

    /**
     * Clear all data in the namespace $nsKey if it exists.
     *
     * @param string $nsKey
     */
    #[\Override]
    public function clearAll($nsKey)
    {
        if (array_key_exists($nsKey, $this->_keys)) {
            foreach ($this->_keys[$nsKey] as $itemKey => $null) {
                $this->clearKey($nsKey, $itemKey);
            }
            if (is_dir($this->_path.'/'.$nsKey)) {
                rmdir($this->_path.'/'.$nsKey);
            }
            unset($this->_keys[$nsKey]);
        }
    }

    /**
     * Initialize the namespace of $nsKey if needed.
     *
     * @param string $nsKey
     */
    private function _prepareCache($nsKey)
    {
        $cacheDir = $this->_path.'/'.$nsKey;
        if (!is_dir($cacheDir)) {
            if (!mkdir($cacheDir)) {
                throw new Swift_IoException('Failed to create cache directory '.$cacheDir);
            }
            $this->_keys[$nsKey] = [];
        }
    }

    /**
     * Get a file handle on the cache item.
     *
     * @param string  $nsKey
     * @param string  $itemKey
     * @param int     $position
     *
     * @return resource
     */
    private function _getHandle($nsKey, $itemKey, $position)
    {
        if (!isset($this->_keys[$nsKey][$itemKey])) {
            $openMode = $this->hasKey($nsKey, $itemKey)
                ? 'r+b'
                : 'w+b'
                ;
            $fp = fopen($this->_path.'/'.$nsKey.'/'.$itemKey, $openMode);
            $this->_keys[$nsKey][$itemKey] = $fp;
        }
        if (self::POSITION_START == $position) {
            fseek($this->_keys[$nsKey][$itemKey], 0, SEEK_SET);
        } elseif (self::POSITION_END == $position) {
            fseek($this->_keys[$nsKey][$itemKey], 0, SEEK_END);
        }

        return $this->_keys[$nsKey][$itemKey];
    }

    private function _freeHandle($nsKey, $itemKey)
    {
        $fp = $this->_getHandle($nsKey, $itemKey, self::POSITION_CURRENT);
        fclose($fp);
        $this->_keys[$nsKey][$itemKey] = null;
    }

    /**
     * Destructor.
     */
    public function __destruct()
    {
        foreach ($this->_keys as $nsKey => $null) {
            $this->clearAll($nsKey);
        }
    }
}

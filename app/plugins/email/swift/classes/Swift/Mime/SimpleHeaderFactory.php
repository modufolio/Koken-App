<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Creates MIME headers.
 *
 * @author     Chris Corbyn
 */
class Swift_Mime_SimpleHeaderFactory implements Swift_Mime_HeaderFactory
{
    /**
     * Creates a new SimpleHeaderFactory using $encoder and $paramEncoder.
     *
     * @param Swift_Mime_HeaderEncoder $_encoder
     * @param Swift_Encoder $_paramEncoder
     * @param Swift_Mime_Grammar $_grammar
     * @param string|null $_charset
     */
    public function __construct(private Swift_Mime_HeaderEncoder $_encoder, private Swift_Encoder $_paramEncoder, private readonly Swift_Mime_Grammar $_grammar, private $_charset = null)
    {
    }

    /**
     * Create a new Mailbox Header with a list of $addresses.
     *
     * @param string            $name
     * @param array|string|null $addresses
     *
     * @return Swift_Mime_Header
     */
    #[\Override]
    public function createMailboxHeader($name, $addresses = null)
    {
        $header = new Swift_Mime_Headers_MailboxHeader($name, $this->_encoder, $this->_grammar);
        if (isset($addresses)) {
            $header->setFieldBodyModel($addresses);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Create a new Date header using $timestamp (UNIX time).
     * @param string       $name
     * @param int|null     $timestamp
     *
     * @return Swift_Mime_Header
     */
    #[\Override]
    public function createDateHeader($name, $timestamp = null)
    {
        $header = new Swift_Mime_Headers_DateHeader($name, $this->_grammar);
        if (isset($timestamp)) {
            $header->setFieldBodyModel($timestamp);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Create a new basic text header with $name and $value.
     *
     * @param string $name
     * @param string $value
     *
     * @return Swift_Mime_Header
     */
    #[\Override]
    public function createTextHeader($name, $value = null)
    {
        $header = new Swift_Mime_Headers_UnstructuredHeader($name, $this->_encoder, $this->_grammar);
        if (isset($value)) {
            $header->setFieldBodyModel($value);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Create a new ParameterizedHeader with $name, $value and $params.
     *
     * @param string $name
     * @param string $value
     * @param array  $params
     *
     * @return Swift_Mime_ParameterizedHeader
     */
    #[\Override]
    public function createParameterizedHeader(
        $name,
        $value = null,
        $params = []
    )
    {
        $header = new Swift_Mime_Headers_ParameterizedHeader(
            $name,
            $this->_encoder,
            $this->_grammar,
            (strtolower($name) == 'content-disposition')
                ? $this->_paramEncoder
                : null
        );
        if (isset($value)) {
            $header->setFieldBodyModel($value);
        }
        foreach ($params as $k => $v) {
            $header->setParameter($k, $v);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Create a new ID header for Message-ID or Content-ID.
     *
     * @param string       $name
     * @param string|array $ids
     *
     * @return Swift_Mime_Header
     */
    #[\Override]
    public function createIdHeader($name, $ids = null)
    {
        $header = new Swift_Mime_Headers_IdentificationHeader($name, $this->_grammar);
        if (isset($ids)) {
            $header->setFieldBodyModel($ids);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Create a new Path header with an address (path) in it.
     *
     * @param string $name
     * @param string $path
     *
     * @return Swift_Mime_Header
     */
    #[\Override]
    public function createPathHeader($name, $path = null)
    {
        $header = new Swift_Mime_Headers_PathHeader($name, $this->_grammar);
        if (isset($path)) {
            $header->setFieldBodyModel($path);
        }
        $this->_setHeaderCharset($header);

        return $header;
    }

    /**
     * Notify this observer that the entity's charset has changed.
     *
     * @param string $charset
     */
    #[\Override]
    public function charsetChanged($charset)
    {
        $this->_charset = $charset;
        $this->_encoder->charsetChanged($charset);
        $this->_paramEncoder->charsetChanged($charset);
    }

    /**
    * Make a deep copy of object
    */
    public function __clone()
    {
        $this->_encoder = clone $this->_encoder;
        $this->_paramEncoder = clone $this->_paramEncoder;
    }

    /** Apply the charset to the Header */
    private function _setHeaderCharset(Swift_Mime_Header $header)
    {
        if (isset($this->_charset)) {
            $header->setCharset($this->_charset);
        }
    }
}

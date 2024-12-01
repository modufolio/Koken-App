<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Proxy for quoted-printable content encoders.
 *
 * Switches on the best QP encoder implementation for current charset.
 *
 * @author     Jean-François Simon <jeanfrancois.simon@sensiolabs.com>
 */
class Swift_Mime_ContentEncoder_QpContentEncoderProxy implements Swift_Mime_ContentEncoder
{
    /**
     * Constructor.
     *
     * @param Swift_Mime_ContentEncoder_QpContentEncoder       $safeEncoder
     * @param Swift_Mime_ContentEncoder_NativeQpContentEncoder $nativeEncoder
     * @param string|null                                      $charset
     */
    public function __construct(private Swift_Mime_ContentEncoder_QpContentEncoder $safeEncoder, private Swift_Mime_ContentEncoder_NativeQpContentEncoder $nativeEncoder, private $charset)
    {
    }

    /**
     * Make a deep copy of object
     */
    public function __clone()
    {
        $this->safeEncoder = clone $this->safeEncoder;
        $this->nativeEncoder = clone $this->nativeEncoder;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function charsetChanged($charset)
    {
        $this->charset = $charset;
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function encodeByteStream(Swift_OutputByteStream $os, Swift_InputByteStream $is, $firstLineOffset = 0, $maxLineLength = 0)
    {
        $this->getEncoder()->encodeByteStream($os, $is, $firstLineOffset, $maxLineLength);
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function getName()
    {
        return 'quoted-printable';
    }

    /**
     * {@inheritdoc}
     */
    #[\Override]
    public function encodeString($string, $firstLineOffset = 0, $maxLineLength = 0)
    {
        return $this->getEncoder()->encodeString($string, $firstLineOffset, $maxLineLength);
    }

    /**
     * @return Swift_Mime_ContentEncoder
     */
    private function getEncoder()
    {
        return 'utf-8' === $this->charset ? $this->nativeEncoder : $this->safeEncoder;
    }
}

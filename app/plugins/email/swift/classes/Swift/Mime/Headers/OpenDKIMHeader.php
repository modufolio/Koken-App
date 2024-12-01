<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * An OpenDKIM Specific Header using only raw header datas without encoding
 *
 * @author     De Cock Xavier <xdecock@gmail.com>
 */
class Swift_Mime_Headers_OpenDKIMHeader implements Swift_Mime_Header
{
    /**
     * The value of this Header.
     *
     * @var string
     */
    private $_value;

    /**
     * Creates a new SimpleHeader with $name.
     *
     * @param string $_fieldName
     * @param Swift_Mime_HeaderEncoder $encoder
     * @param Swift_Mime_Grammar       $grammar
     */
    public function __construct(
        /**
         * The name of this Header
         */
        private $_fieldName
    )
    {
    }

    /**
     * Get the type of Header that this instance represents.
     *
     * @see TYPE_TEXT, TYPE_PARAMETERIZED, TYPE_MAILBOX
     * @see TYPE_DATE, TYPE_ID, TYPE_PATH
     *
     * @return int
     */
    #[\Override]
    public function getFieldType()
    {
        return self::TYPE_TEXT;
    }

    /**
     * Set the model for the field body.
     *
     * This method takes a string for the field value.
     *
     * @param string $model
     */
    #[\Override]
    public function setFieldBodyModel($model)
    {
        $this->setValue($model);
    }

    /**
     * Get the model for the field body.
     *
     * This method returns a string.
     *
     * @return string
     */
    #[\Override]
    public function getFieldBodyModel()
    {
        return $this->getValue();
    }

    /**
     * Get the (unencoded) value of this header.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->_value;
    }

    /**
     * Set the (unencoded) value of this header.
     *
     * @param string $value
     */
    public function setValue($value)
    {
        $this->_value = $value;
    }

    /**
     * Get the value of this header prepared for rendering.
     *
     * @return string
     */
    #[\Override]
    public function getFieldBody()
    {
        return $this->_value;
    }

    /**
     * Get this Header rendered as a RFC 2822 compliant string.
     *
     * @return string
     */
    #[\Override]
    public function toString()
    {
        return $this->_fieldName.': '.$this->_value;
    }

    /**
     * Set the Header FieldName
     * @see Swift_Mime_Header::getFieldName()
     */
    #[\Override]
    public function getFieldName()
    {
        return $this->_fieldName;
    }

    /**
     * Ignored
     */
    #[\Override]
    public function setCharset($charset)
    {
    }
}

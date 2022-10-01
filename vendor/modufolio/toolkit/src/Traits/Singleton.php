<?php

namespace Modufolio\Traits;

/**
 * Singleton trait to implements Singleton pattern in any classes where this trait is used.
 */

trait Singleton {

    protected static array $instance = [];

    /**
     * Protected class constructor to prevent direct object creation.
     */
    protected function  __construct() { }

    /**
     * Prevent object cloning
     */
    final protected function  __clone() { }

    /**
     * To return new or existing Singleton instance of the class from which it is called.
     * As it sets to final it can't be overridden.
     *
     * @return object Singleton instance of the class.
     */
    final public static function instance(): object
    {

        /**
         * Returns name of the class the static method is called in.
         */
        $called_class = get_called_class();

        if ( ! isset( static::$instance[ $called_class ] ) ) {

            static::$instance[ $called_class ] = new $called_class();

        }

        return static::$instance[ $called_class ];

    }

}
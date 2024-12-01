<?php

/*
 * This file is part of SwiftMailer.
 * (c) 2004-2009 Chris Corbyn
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Makes sure a connection to a POP3 host has been established prior to connecting to SMTP.
 *
 * @author     Chris Corbyn
 */
class Swift_Plugins_PopBeforeSmtpPlugin implements Swift_Events_TransportChangeListener, Swift_Plugins_Pop_Pop3Connection
{
    /** A delegate connection to use (mostly a test hook) */
    private $_connection;

    /** Username to use (if any) */
    private $_username;

    /** Password to use (if any) */
    private $_password;

    /** Established connection via TCP socket */
    private $_socket;

    /** Connect timeout in seconds */
    private $_timeout = 10;

    /** SMTP Transport to bind to */
    private $_transport;

    /**
     * Create a new PopBeforeSmtpPlugin for $host and $port.
     *
     * @param string $_host
     * @param int $_port
     * @param string $_crypto as "tls" or "ssl"
     */
    public function __construct(private $_host, private $_port = 110, private $_crypto = null)
    {
    }

    /**
     * Create a new PopBeforeSmtpPlugin for $host and $port.
     *
     * @param string  $host
     * @param int     $port
     * @param string  $crypto as "tls" or "ssl"
     *
     * @return Swift_Plugins_PopBeforeSmtpPlugin
     */
    public static function newInstance($host, $port = 110, $crypto = null)
    {
        return new self($host, $port, $crypto);
    }

    /**
     * Set a Pop3Connection to delegate to instead of connecting directly.
     *
     * @param Swift_Plugins_Pop_Pop3Connection $connection
     *
     * @return Swift_Plugins_PopBeforeSmtpPlugin
     */
    public function setConnection(Swift_Plugins_Pop_Pop3Connection $connection)
    {
        $this->_connection = $connection;

        return $this;
    }

    /**
     * Bind this plugin to a specific SMTP transport instance.
     *
     * @param Swift_Transport
     */
    public function bindSmtp(Swift_Transport $smtp)
    {
        $this->_transport = $smtp;
    }

    /**
     * Set the connection timeout in seconds (default 10).
     *
     * @param int     $timeout
     *
     * @return Swift_Plugins_PopBeforeSmtpPlugin
     */
    public function setTimeout($timeout)
    {
        $this->_timeout = (int) $timeout;

        return $this;
    }

    /**
     * Set the username to use when connecting (if needed).
     *
     * @param string $username
     *
     * @return Swift_Plugins_PopBeforeSmtpPlugin
     */
    public function setUsername($username)
    {
        $this->_username = $username;

        return $this;
    }

    /**
     * Set the password to use when connecting (if needed).
     *
     * @param string $password
     *
     * @return Swift_Plugins_PopBeforeSmtpPlugin
     */
    public function setPassword($password)
    {
        $this->_password = $password;

        return $this;
    }

    /**
     * Connect to the POP3 host and authenticate.
     *
     * @throws Swift_Plugins_Pop_Pop3Exception if connection fails
     */
    #[\Override]
    public function connect()
    {
        if (isset($this->_connection)) {
            $this->_connection->connect();
        } else {
            if (!isset($this->_socket)) {
                if (!$socket = fsockopen(
                    $this->_getHostString(),
                    $this->_port,
                    $errno,
                    $errstr,
                    $this->_timeout
                )) {
                    throw new Swift_Plugins_Pop_Pop3Exception(
                        sprintf('Failed to connect to POP3 host [%s]: %s', $this->_host, $errstr)
                    );
                }
                $this->_socket = $socket;

                if (false === $greeting = fgets($this->_socket)) {
                    throw new Swift_Plugins_Pop_Pop3Exception(
                        sprintf('Failed to connect to POP3 host [%s]', trim($greeting))
                    );
                }

                $this->_assertOk($greeting);

                if ($this->_username) {
                    $this->_command(sprintf("USER %s\r\n", $this->_username));
                    $this->_command(sprintf("PASS %s\r\n", $this->_password));
                }
            }
        }
    }

    /**
     * Disconnect from the POP3 host.
     */
    #[\Override]
    public function disconnect()
    {
        if (isset($this->_connection)) {
            $this->_connection->disconnect();
        } else {
            $this->_command("QUIT\r\n");
            if (!fclose($this->_socket)) {
                throw new Swift_Plugins_Pop_Pop3Exception(
                    sprintf('POP3 host [%s] connection could not be stopped', $this->_host)
                );
            }
            $this->_socket = null;
        }
    }

    /**
     * Invoked just before a Transport is started.
     *
     * @param Swift_Events_TransportChangeEvent $evt
     */
    #[\Override]
    public function beforeTransportStarted(Swift_Events_TransportChangeEvent $evt)
    {
        if (isset($this->_transport)) {
            if ($this->_transport !== $evt->getTransport()) {
                return;
            }
        }

        $this->connect();
        $this->disconnect();
    }

    /**
     * Not used.
     */
    #[\Override]
    public function transportStarted(Swift_Events_TransportChangeEvent $evt)
    {
    }

    /**
     * Not used.
     */
    #[\Override]
    public function beforeTransportStopped(Swift_Events_TransportChangeEvent $evt)
    {
    }

    /**
     * Not used.
     */
    #[\Override]
    public function transportStopped(Swift_Events_TransportChangeEvent $evt)
    {
    }

    private function _command($command)
    {
        if (!fwrite($this->_socket, (string) $command)) {
            throw new Swift_Plugins_Pop_Pop3Exception(
                sprintf('Failed to write command [%s] to POP3 host', trim((string) $command))
            );
        }

        if (false === $response = fgets($this->_socket)) {
            throw new Swift_Plugins_Pop_Pop3Exception(
                sprintf('Failed to read from POP3 host after command [%s]', trim((string) $command))
            );
        }

        $this->_assertOk($response);

        return $response;
    }

    private function _assertOk($response)
    {
        if (!str_starts_with((string) $response, '+OK')) {
            throw new Swift_Plugins_Pop_Pop3Exception(
                sprintf('POP3 command failed [%s]', trim((string) $response))
            );
        }
    }

    private function _getHostString()
    {
        $host = $this->_host;
        $host = match (strtolower((string) $this->_crypto)) {
            'ssl' => 'ssl://'.$host,
            'tls' => 'tls://'.$host,
            default => $host,
        };

        return $host;
    }
}

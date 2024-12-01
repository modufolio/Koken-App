<?php

class WebhostWhois
{
    public $key = 'unknown';
    private $results;

    // For magic methods
    // Ex. isMediaTempleGrid(), isDreamhost(), etc
    public function __call($name, $parameters)
    {
        $key = preg_replace_callback('/^is([A-Z])/', fn($matches) => strtolower((string) $matches[1]), (string) $name);
        $key = preg_replace_callback('/([A-Z])/', fn($matches) => strtolower((string) $matches[1]), (string) $key);

        if (isset($this->results[$key])) {
            return (bool) $this->results[$key];
        } else {
            throw new BadMethodCallException('WebhostWhois class does not have method ' . $key . '()');
        }
    }

    private function is_really_callable($function_name)
    {
        $disabled_functions = explode(',', str_replace(' ', '', ini_get('disable_functions')));

        if (ini_get('suhosin.executor.func.blacklist')) {
            $disabled_functions = array_merge($disabled_functions, explode(',', str_replace(' ', '', ini_get('suhosin.executor.func.blacklist'))));
        }

        if (in_array($function_name, $disabled_functions)) {
            return false;
        } else {
            return is_callable($function_name);
        }
    }

    public function __construct($options = [])
    {
        if (!$this->is_really_callable('php_uname')) {
            return;
        }

        $options = array_merge(
            ['uname' => php_uname(), 'server' => $_SERVER, 'useDns' => false],
            $options
        );

        if (!$this->is_really_callable('dns_get_record')) {
            $options['useDns'] = false;
        }

        // Tests for each webhost go here. Each test should evaluate to a boolean.
        // Keep tests in alphabetical order by key.
        $this->results = ['bluehost'          => str_contains((string) $options['uname'], 'hostmonster.com '), 'dreamhost'         => isset($options['server']['DH_USER']), 'go-daddy'          => str_contains((string) $options['uname'], 'secureserver.net'), 'in-motion'         => str_contains((string) $options['uname'], '.inmotionhosting.com'), 'media-temple-grid' => isset($options['server']['ACCESS_DOMAIN']) && preg_match('/\.gridserver\.com$/', (string) $options['server']['ACCESS_DOMAIN']) === 1, 'ovh'               => str_contains((string) $options['uname'], '.ovh.net '), 'rackspace-cloud'   => str_contains((string) $options['uname'], 'stabletransit.com '), 'site5'             => str_contains((string) $options['uname'], '.accountservergroup.com '), 'strato'            => str_contains((string) $options['uname'], '.stratoserver.net ')];

        // Separate definitions for hosts that can only be detected via DNS nameservers.
        // Should try as much as possible not to do this, as it is slower.
        // These will only be checked if none of the $results pass.
        // Test will pass if any of the supplied nameservers are found in the DNS lookup.
        $dns = ['media-temple-dv' => ['ns1.mediatemple.net', 'ns2.mediatemple.net']];

        $host = array_search(true, $this->results);

        if ($host) {
            $this->key = $host;

            foreach ($dns as $key => $nameServers) {
                $this->results[$key] = false;
            }
        } else {
            $ns = [];

            if ($options['useDns'] && isset($options['server']['HTTP_HOST'])) {
                $dnsInfo = dns_get_record($options['server']['HTTP_HOST'], DNS_NS);
                foreach ($dnsInfo as $info) {
                    $ns[] = $info['target'];
                }
            }

            foreach ($dns as $key => $nameServers) {
                if ($this->key === 'unknown' && count(array_intersect($nameServers, $ns)) > 0) {
                    $this->key = $key;
                    $this->results[$key] = true;
                } else {
                    $this->results[$key] = false;
                }
            }
        }
    }
}

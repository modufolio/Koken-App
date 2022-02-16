<?php

class WebhostWhois
{
    public $key = 'unknown';
    private $results;

    // For magic methods
    // Ex. isMediaTempleGrid(), isDreamhost(), etc
    public function __call($name, $parameters)
    {
        $key = preg_replace_callback('/^is([A-Z])/', create_function('$matches', 'return strtolower($matches[1]);'), $name);
        $key = preg_replace_callback('/([A-Z])/', create_function('$matches', 'return \'-\' . strtolower($matches[1]);'), $key);

        if (isset($this->results[$key])) {
            return (bool) $this->results[$key];
        } else {
            throw new BadMethodCallException('WebhostWhois class does not have method ' . $key . '()');
        }
    }

    private function is_really_callable($function_name)
    {
        $disabled_functions = explode(',', str_replace(' ', '', ini_get('disable_functions')));

        if (ini_get('suhosin.executor.func.blacklist'))
        {
            $disabled_functions = array_merge($disabled_functions, explode(',', str_replace(' ', '', ini_get('suhosin.executor.func.blacklist'))));
        }

        if (in_array($function_name, $disabled_functions))
        {
            return false;
        }
        else
        {
            return is_callable($function_name);
        }
    }

    public function __construct($options = array())
    {
        if (!$this->is_really_callable('php_uname')) return;

    	$options = array_merge(
    		array(
    			'uname' => php_uname(),
    			'server' => $_SERVER,
    			'useDns' => false,
    		),
    		$options
    	);

    	if (!$this->is_really_callable('dns_get_record'))
    	{
    		$options['useDns'] = false;
    	}

        // Tests for each webhost go here. Each test should evaluate to a boolean.
        // Keep tests in alphabetical order by key.
        $this->results = array(
            'bluehost'          => strpos($options['uname'], 'hostmonster.com ') !== false,
            'dreamhost'         => isset($options['server']['DH_USER']),
            'go-daddy'          => strpos($options['uname'], 'secureserver.net') !== false,
            'in-motion'         => strpos($options['uname'], '.inmotionhosting.com') !== false,
            'media-temple-grid' => isset($options['server']['ACCESS_DOMAIN']) && preg_match('/\.gridserver\.com$/', $options['server']['ACCESS_DOMAIN']) === 1,
            'ovh'               => strpos($options['uname'], '.ovh.net ') !== false,
            'rackspace-cloud'   => strpos($options['uname'], 'stabletransit.com ') !== false,
            'site5'             => strpos($options['uname'], '.accountservergroup.com ') !== false,
            'strato'            => strpos($options['uname'], '.stratoserver.net ') !== false,
        );

        // Separate definitions for hosts that can only be detected via DNS nameservers.
        // Should try as much as possible not to do this, as it is slower.
        // These will only be checked if none of the $results pass.
        // Test will pass if any of the supplied nameservers are found in the DNS lookup.
        $dns = array(
            'media-temple-dv' => array('ns1.mediatemple.net', 'ns2.mediatemple.net'),
        );

        $host = array_search(true, $this->results);

        if ($host) {
            $this->key = $host;

            foreach ($dns as $key => $nameServers) {
                $this->results[$key] = false;
            }
        } else {
            $ns = array();

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
<?php
/**
 * Consent Authentication Processing filter
 *
 *
 * @package SimpleSAMLphp
 */
class sspmod_consent_Auth_Process_Preprocess extends SimpleSAML_Auth_ProcessingFilter
{
    /**
     * Initialize consent filter
     *
     * Validates and parses the configuration
     *
     * @param array $config   Configuration information
     * @param mixed $reserved For future use
     */
    public function __construct($config, $reserved) {
        assert('is_array($config)');
        parent::__construct($config, $reserved);
    }

    public function process(&$state)
    {
        assert('is_array($state)');
        assert('array_key_exists("UserID", $state)');
        assert('array_key_exists("Destination", $state)');
        assert('array_key_exists("entityid", $state["Destination"])');
        assert('array_key_exists("metadata-set", $state["Destination"])');
        assert('array_key_exists("entityid", $state["Source"])');
        assert('array_key_exists("metadata-set", $state["Source"])');

        $attributes  = $state['Attributes'];
        $state['userdb'] = $attributes;

        $id  = SimpleSAML_Auth_State::saveState($state, 'consent:preprocess');
    }
}

SimpleSAMLphp module for user consent
=====================================
User consent for Korean users (Korean Access Federation)

Add-on by KAFE

* GUI
* trimmed eduPersonTargetedID
* read and show privacypolicy
* read and show jurisdiction (work with KAFE federation manager)

Example configuration on simplesamlphp config.php

90 => array(

     'class' => 'consent:Consent',

     'store' => 'consent:Cookie',

     'focus' => 'yes',

     'checked' => TRUE,

     'useRegistrationAuthority' => TRUE,

     'useLogo' => FALSE

),


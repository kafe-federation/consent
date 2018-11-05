SimpleSAMLphp module for user consent
=====================================
User consent for Korean users (Korean Access Federation)

Add-on by KAFE
--------------

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

consent:Preprocess
------------------
* store raw (not-attributeLimited) user attributes to the state variable

Example configuration on config.php

10 => array('class' => 'core:AttributeMap',

      'oid2name',

      ),

11 => 'consent:Preprocess',

12 => array('class' => 'core:AttributeMap',

     'name2oid',

),

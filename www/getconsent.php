<?php
/**
 * Consent script
 *
 * This script displays a page to the user, which requests that the user
 * authorizes the release of attributes.
 *
 * @package SimpleSAMLphp
 */
/**
 * Explicit instruct consent page to send no-cache header to browsers to make 
 * sure the users attribute information are not store on client disk.
 * 
 * In an vanilla apache-php installation is the php variables set to:
 *
 * session.cache_limiter = nocache
 *
 * so this is just to make sure.
 */

$db = SimpleSAML_Database::getInstance();
$kafeConfig = array();
$stmt = $db->read("SELECT * FROM system_config WHERE config_key='config_consent_log'");
while ($row = $stmt->fetch()) {
    if($row['config_key'] == "config_consent_log") {
        $kafeConfig = json_decode($row['config_value'], true);
    }
}

foreach ($kafeConfig as $key => $val) {
    if($val != "on") {
        unset($kafeConfig[$key]);
    }
}

function get_client_ip() {
    $ipaddress = '';
    if (getenv('HTTP_CLIENT_IP'))
        $ipaddress = getenv('HTTP_CLIENT_IP');
    else if(getenv('HTTP_X_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_X_FORWARDED_FOR');
    else if(getenv('HTTP_X_FORWARDED'))
        $ipaddress = getenv('HTTP_X_FORWARDED');
    else if(getenv('HTTP_FORWARDED_FOR'))
        $ipaddress = getenv('HTTP_FORWARDED_FOR');
    else if(getenv('HTTP_FORWARDED'))
       $ipaddress = getenv('HTTP_FORWARDED');
    else if(getenv('REMOTE_ADDR'))
        $ipaddress = getenv('REMOTE_ADDR');
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}

$IP = base64_encode(SimpleSAML\Utils\Crypto::aesEncrypt(get_client_ip()));

session_cache_limiter('nocache');

$globalConfig = SimpleSAML_Configuration::getInstance();

SimpleSAML_Logger::info('Consent - getconsent: Accessing consent interface');

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest(
        'Missing required StateId query parameter.'
    );
}

$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'consent:request');

if (array_key_exists('core:SP', $state)) {
    $spentityid = $state['core:SP'];
} else if (array_key_exists('saml:sp:State', $state)) {
    $spentityid = $state['saml:sp:State']['core:SP'];
} else {
    $spentityid = 'UNKNOWN';
}

// The user has pressed the yes-button
if (array_key_exists('yes', $_REQUEST)) {
    $udb = $state['userdb'];
    SimpleSAML_Logger::notice(json_encode($udb));

    $logdata = array();
   
    if(isset($kafeConfig["uid"])) $logdata["uid"] = $udb["uid"][0];
    if(isset($kafeConfig["eppn"])) $logdata["eppn"] = $udb["eduPersonPrincipalName"][0];
    if(isset($kafeConfig["email"])) $logdata["email"] = $udb["mail"][0];
    if(isset($kafeConfig["displayname"])) $logdata["displayname"] = $udb["displayName"][0];
    if(isset($kafeConfig["attributename"])) {
        $attr = $state["Attributes"];
        $attrnames = array();
        foreach($attr as $key => $value)
            array_push($attrnames, $key);
        $logdata["attributenames"] = implode(",", $attrnames);
    } 
    
    $logkeys = "session,spentityid,ip,remember";
    $logmap = ":session,:sp,:ip,:remember";
    foreach($logdata as $key => $value) {
        $logkeys = $logkeys.",".$key;
        $logmap = $logmap.",:".$key;
    }

    $logdata["sp"] = $spentityid;
    $logdata["ip"] = $IP;
    $logdata["session"] = session_id();
    $logdata["remember"] = array_key_exists('saveconsent', $_REQUEST) ? "on" : "off";

    $db->write("INSERT INTO log_consent($logkeys) VALUES($logmap)", $logdata);

    if (array_key_exists('saveconsent', $_REQUEST)) {
        SimpleSAML_Logger::stats('consentResponse remember');
    } 
    else {
        SimpleSAML_Logger::stats('consentResponse rememberNot');
    }

    $statsInfo = array(
        'remember' => array_key_exists('saveconsent', $_REQUEST),
    );
    if (isset($state['Destination']['entityid'])) {
        $statsInfo['spEntityID'] = $state['Destination']['entityid'];
    }
    SimpleSAML_Stats::log('consent:accept', $statsInfo);

    if (   array_key_exists('consent:store', $state) 
        && array_key_exists('saveconsent', $_REQUEST)
        && $_REQUEST['saveconsent'] === '1'
    ) {
        // Save consent
        $store = $state['consent:store'];
        $userId = $state['consent:store.userId'];
        $targetedId = $state['consent:store.destination'];
        $attributeSet = $state['consent:store.attributeSet'];

        SimpleSAML_Logger::debug(
            'Consent - saveConsent() : [' . $userId . '|' .
            $targetedId . '|' .  $attributeSet . ']'
        );    
        try {
            $store->saveConsent($userId, $targetedId, $attributeSet);
        } 
        catch (Exception $e) {
            SimpleSAML_Logger::error('Consent: Error writing to storage: ' . $e->getMessage());
        }
    }

    // 접속 로깅
    // IDP: '.$state['Source']['entityid'].',
    $logging = 'LOGIN - SP: '.$state['Destination']['entityid'].', USER: '.json_encode($state['Attributes']);
    SimpleSAML_Logger::notice($logging);

    /*$encUid = base64_encode(SimpleSAML\Utils\Crypto::aesEncrypt($state['Attributes']['uid'][0]));
    $encAddr =  base64_encode(SimpleSAML\Utils\Crypto::aesEncrypt($_SERVER['REMOTE_ADDR']));

    $logging = 'Consent for \''.$state['Destination']['entityid'].'\', User \''. $encUid . '\' has made a consent from \'' . $encAddr . '\'.';
    SimpleSAML_Logger::stats($logging);*/

    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
}

// Prepare attributes for presentation
$attributes = $state['Attributes'];
$noconsentattributes = $state['consent:noconsentattributes'];

// Remove attributes that do not require consent
foreach ($attributes AS $attrkey => $attrval) {
    if (in_array($attrkey, $noconsentattributes)) {
        unset($attributes[$attrkey]);
    }
}
$para = array(
    'attributes' => &$attributes
);

// Reorder attributes according to attributepresentation hooks
SimpleSAML_Module::callHooks('attributepresentation', $para);

// Make, populate and layout consent form
$t = new SimpleSAML_XHTML_Template($globalConfig, 'consent:consentform.php');
$t->data['srcMetadata'] = $state['Source'];
$t->data['dstMetadata'] = $state['Destination'];
$t->data['yesTarget'] = SimpleSAML_Module::getModuleURL('consent/getconsent.php');
$t->data['yesData'] = array('StateId' => $id);
$t->data['noTarget'] = SimpleSAML_Module::getModuleURL('consent/noconsent.php');
$t->data['noData'] = array('StateId' => $id);
$t->data['attributes'] = $attributes;
$t->data['checked'] = $state['consent:checked'];
$t->data['useLogo'] = $state['consent:useLogo'];

// Fetch privacypolicy
if (array_key_exists('privacypolicy', $state['Destination'])) {
    $privacypolicy = $state['Destination']['privacypolicy'];
} elseif (array_key_exists('privacypolicy', $state['Source'])) {
    $privacypolicy = $state['Source']['privacypolicy'];
} else {
    $privacypolicy = false;
}

// Fetch PrivacyStatement
if (array_key_exists('UIInfo', $state['Destination'])) {
    if(array_key_exists('PrivacyStatementURL', $state['Destination']['UIInfo'])){
        if(array_key_exists('en', $state['Destination']['UIInfo']['PrivacyStatementURL'])){
            $privacypolicy =  $state['Destination']['UIInfo']['PrivacyStatementURL']['en'];
        }else{
            $newidx = 0;
            foreach($state['Destination']['UIInfo']['PrivacyStatementURL'] as $key =>$val)
            {
                unset($state['Destination']['UIInfo']['PrivacyStatementURL'][$key]);
                $new_key =$newidx;
                $state['Destination']['UIInfo']['PrivacyStatementURL'][$new_key] = $val;
                $newidx++;
            }
            $privacypolicy =  $state['Destination']['UIInfo']['PrivacyStatementURL'][0];
        }
    }
} elseif (array_key_exists('UIInfo', $state['Source'])) {
    if(array_key_exists('PrivacyStatementURL', $state['Source']['UIInfo'])){
        if(array_key_exists('en', $state['Source']['UIInfo']['PrivacyStatementURL'])){
            $privacypolicy =  $state['Source']['UIInfo']['PrivacyStatementURL']['en'];
        }else{
            $newidx = 0;
            if(isset($state['Destination']['UIInfo'])){
                foreach($state['Destination']['UIInfo']['PrivacyStatementURL'] as $key =>$val)
                {
                    unset($state['Destination']['UIInfo']['PrivacyStatementURL'][$key]);
                    $new_key =$newidx;
                    $state['Destination']['UIInfo']['PrivacyStatementURL'][$new_key] = $val;
                    $newidx++;
                }
            }
            if(isset($state['Source']['UIInfo']['PrivacyStatementURL'][0]))
                $privacypolicy =  $state['Source']['UIInfo']['PrivacyStatementURL'][0];
        }
    }
}

if ($privacypolicy !== false) {
    $privacypolicy = str_replace(
        '%SPENTITYID%',
        urlencode($spentityid), 
        $privacypolicy
    );
}
$t->data['sppp'] = $privacypolicy;

// Set focus element
switch ($state['consent:focus']) {
case 'yes':
    $t->data['autofocus'] = 'yesbutton';
    break;
case 'no':
    $t->data['autofocus'] = 'nobutton';
    break;
case null:
default:
    break;
}

if (array_key_exists('consent:store', $state)) {
    $t->data['usestorage'] = true;
} else {
    $t->data['usestorage'] = false;
}

if (array_key_exists('consent:hiddenAttributes', $state)) {
    $t->data['hiddenAttributes'] = $state['consent:hiddenAttributes'];
} else {
    $t->data['hiddenAttributes'] = array();
}

include_once dirname(dirname(__FILE__)) . '/lib/Config.php';

// SP 국가정보 가져오기
$t->data['country'] = '';
if (isset($state['SPMetadata']['EntityAttributes']['http://kafe.kreonet.net/jurisdiction'][0])) {
    $t->data['country'] = implode(',' ,$state['SPMetadata']['EntityAttributes']['http://kafe.kreonet.net/jurisdiction']);
    $t->data['country'] = $NATION[$t->data['country']];
}

// RegistrationAuthority 를 이용한다고 설정되었을 경우
if (empty($t->data['country']) && 
    isset($state['consent:useRegistrationAuthority']) && 
    $state['consent:useRegistrationAuthority'] === true && 
    isset($state['SPMetadata']['registrationInfo']['registrationAuthority'])) {
        $t->data['country'] = $state['SPMetadata']['registrationInfo']['registrationAuthority'];
        $sp_aa = trim(trim($t->data['country']), '/');
        if (isset($AA[$sp_aa])) {
            $t->data['country'] = $NATION[$AA[$sp_aa]];
        }
    }

$t->show();

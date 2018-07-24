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

//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

session_cache_limiter('nocache');

$globalConfig = SimpleSAML_Configuration::getInstance();

function translate_attributes_keys($attributes) {
    $c = SimpleSAML_Configuration::loadFromArray(array());
    $t = new SimpleSAML_Locale_Translate($c);

    $output = array();
    foreach ($attributes as $name => $value) {
        $name = $t->getAttributeTranslation($name);
        array_push($output, $name);
    }
    return implode(',', $output);
}

function getConsentRule($_state) {
    $config = SimpleSAML_Configuration::getInstance();
    $enabled = $config->getBoolean('rzone.consent.enabled');
    if(!$enabled) {
        return;
    }

    // mysql config
    $tableName = $config->getString('rzone.consent.mysql.log_table');
    $db_host = $config->getString('rzone.consent.mysql.host');
    $db_user = $config->getString('rzone.consent.mysql.user');
    $db_pw = $config->getString('rzone.consent.mysql.password');
    $db_name = $config->getString('rzone.consent.mysql.database');

    $sp = $_state['SPMetadata']['entityid'];

    $sql = "SELECT * FROM rz_consent_rule WHERE sp_entity='$sp';";
    $link = mysqli_connect($db_host, $db_user, $db_pw, $db_name);
    $rows = array();
    if($result = mysqli_query($link, $sql, MYSQLI_USE_RESULT)) {
        while($row = mysqli_fetch_object($result)) {
            array_push($rows, json_decode(json_encode($row), true));
        }
        mysqli_free_result($result);
    }
    mysqli_close($link);
 
    if(count($rows) == 0) return 'SAML';
    return $rows[0]['consent_type']; 
} 

function saveConsentLog($_state, $_sp, $attributes, $type=null) {
    $config = SimpleSAML_Configuration::getInstance();
    $enabled = $config->getBoolean('rzone.consent.enabled');
    if(!$enabled) {
        return;
    }

    // mysql config
    $tableName = $config->getString('rzone.consent.mysql.log_table');
    $db_host = $config->getString('rzone.consent.mysql.host');
    $db_user = $config->getString('rzone.consent.mysql.user');
    $db_pw = $config->getString('rzone.consent.mysql.password');
    $db_name = $config->getString('rzone.consent.mysql.database');

    $link = mysqli_connect($db_host, $db_user, $db_pw, $db_name);
    
    // extract log info
    $eppn = $_state['Attributes']['eduPersonPrincipalName'][0];

    // encrypt ip address
    $ip = isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
    $ip = mcrypt_ecb(MCRYPT_GOST, 'K4a12kweEy4F1kB3T8AfdUGaRCfsI8F9', $ip, MCRYPT_ENCRYPT);
    $ip = base64_encode($ip);

    $idp = $_state['Source']['entityid'];
    //$attributes = $_state['Attributes'];
    $attributes = translate_attributes_keys($attributes);
    $attributes = mysqli_real_escape_string($link, $attributes);
    $validity = array_key_exists('saveconsent', $_REQUEST) ? 90 : 0;
    if($type == null)
        $type = $validity == 90 ? 'remember' : 'onetime';

    $sql = "INSERT INTO $tableName(eppn, type, ip_address, idp_entity, sp_entity, attributes, validity) VALUES('$eppn', '$type', '$ip', '$idp', '$_sp', '$attributes', '$validity')";

    mysqli_query($link, $sql);
}

function isConsentVisible($_state, $_sp) {
    $config = SimpleSAML_Configuration::getInstance();
    $enabled = $config->getBoolean('rzone.consent.enabled');
    if(!$enabled) {
        return false;
    }

    // mysql config
    $tableName = $config->getString('rzone.consent.mysql.log_table');
    $db_host = $config->getString('rzone.consent.mysql.host');
    $db_user = $config->getString('rzone.consent.mysql.user');
    $db_pw = $config->getString('rzone.consent.mysql.password');
    $db_name = $config->getString('rzone.consent.mysql.database');

    $eppn = $_state['Attributes']['eduPersonPrincipalName'][0];

    $sql = "SELECT * FROM rz_consent_log WHERE eppn='$eppn' and type='remember' and sp_entity='$_sp' ORDER BY timestamp DESC LIMIT 1;";
    $link = mysqli_connect($db_host, $db_user, $db_pw, $db_name);
    $rows = array();
    if($result = mysqli_query($link, $sql, MYSQLI_USE_RESULT)) {
        while($row = mysqli_fetch_object($result)) {
            array_push($rows, json_decode(json_encode($row), true));
        }
        mysqli_free_result($result);
    }
    mysqli_close($link);

    if(count($rows) == 0) return false;
    
    $log = $rows[0];
    
    $ts = strtotime(date($log['timestamp']));
    $v = $log['validity'];
    $now = time();
   
    if($ts + $v * 24 * 60 * 60 < $now)
        return false;

    // TODO If Requested Attributes Changed $pre_attributes = $log['attributes'];

    return true;
}

if (!array_key_exists('StateId', $_REQUEST)) {
    throw new SimpleSAML_Error_BadRequest(
        'Missing required StateId query parameter.'
    );
}

// Process Start
$id = $_REQUEST['StateId'];
$state = SimpleSAML_Auth_State::loadState($id, 'consent:request');

$rule = getConsentRule($state);
$relayState = $state['saml:RelayState'];

if (array_key_exists('core:SP', $state)) {
    $spentityid = $state['core:SP'];
} else if (array_key_exists('saml:sp:State', $state)) {
    $spentityid = $state['saml:sp:State']['core:SP'];
} else {
    $spentityid = 'UNKNOWN';
}

$sp = $spentityid;

if($rule == 'OIDC') {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://rz-oidc.kreonet.net/rz-api/client-info?relay_state=$relayState");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    $content = trim(curl_exec($ch));
    curl_close($ch);

    $oidc_info = json_decode($content, true);
    
    if($oidc_info['status'] == 200) {
        $sp = $sp.'/'.$oidc_info['data']['requester'];
        $oidc_attributes = $oidc_info['data']['requested_attributes'];
        $oidc_client_name = $oidc_info['data']['client_info']['client_name'];
        $oidc_privacy = $oidc_info['data']['client_info']['privacy_policy_url'];
        $oidc_country = $oidc_info['data']['client_info']['country'];
    }
}

// Prepare attributes for presentation
$attributes = $state['Attributes'];
$noconsentattributes = $state['consent:noconsentattributes'];

foreach ($attributes AS $attrkey => $attrval) {
    if (in_array($attrkey, $noconsentattributes)) {
        unset($attributes[$attrkey]);
    }
    
    if($oidc_attributes) {
        if (!in_array($attrkey, $oidc_attributes)) {
            unset($attributes[$attrkey]);
        }
    }
}

$para = array(
    'attributes' => &$attributes
);

// The user has pressed the yes-button
if (array_key_exists('yes', $_REQUEST)) {
    saveConsentLog($state, $sp, $attributes);
    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
    exit;
}

if(isConsentVisible($state, $sp)) {
    saveConsentLog($state, $sp, $attributes, 'pass');
    SimpleSAML_Auth_ProcessingChain::resumeProcessing($state);
    exit;
}

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

if($oidc_client_name) {
    $t->data['oidc_client_name'] = $oidc_client_name;
}
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
        urlencode($sp), 
        $privacypolicy
    );
}
$t->data['sppp'] = $privacypolicy;
if($oidc_privacy)
    $t->data['sppp'] = $oidc_privacy;

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
if ( $oidc_country ) {
    $t->data['country'] = $NATION[$oidc_country];
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

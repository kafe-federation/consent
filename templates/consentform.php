<?php
/**
 * Template form for giving consent.
 *
 * Parameters:
 * - 'srcMetadata': Metadata/configuration for the source.
 * - 'dstMetadata': Metadata/configuration for the destination.
 * - 'yesTarget': Target URL for the yes-button. This URL will receive a POST request.
 * - 'yesData': Parameters which should be included in the yes-request.
 * - 'noTarget': Target URL for the no-button. This URL will receive a GET request.
 * - 'noData': Parameters which should be included in the no-request.
 * - 'attributes': The attributes which are about to be released.
 * - 'sppp': URL to the privacy policy of the destination, or FALSE.
 *
 * @package simpleSAMLphp
 */
assert('is_array($this->data["srcMetadata"])');
assert('is_array($this->data["dstMetadata"])');
assert('is_string($this->data["yesTarget"])');
assert('is_array($this->data["yesData"])');
assert('is_string($this->data["noTarget"])');
assert('is_array($this->data["noData"])');
assert('is_array($this->data["attributes"])');
assert('is_array($this->data["hiddenAttributes"])');
assert('$this->data["sppp"] === false || is_string($this->data["sppp"])');


// Parse parameters
if (array_key_exists('name', $this->data['srcMetadata'])) {
    $srcName = $this->data['srcMetadata']['name'];
} elseif (array_key_exists('OrganizationDisplayName', $this->data['srcMetadata'])) {
    $srcName = $this->data['srcMetadata']['OrganizationDisplayName'];
} else {
    $srcName = $this->data['srcMetadata']['entityid'];
}

if (is_array($srcName)) {
    $srcName = $this->t($srcName);
}

if (array_key_exists('name', $this->data['dstMetadata'])) {
    $dstName = $this->data['dstMetadata']['name'];
} elseif (array_key_exists('OrganizationDisplayName', $this->data['dstMetadata'])) {
    $dstName = $this->data['dstMetadata']['OrganizationDisplayName'];
} else {
    $dstName = $this->data['dstMetadata']['entityid'];
}

if (is_array($dstName)) {
    $dstName = $this->t($dstName);
}

$srcName = htmlspecialchars($srcName);
$dstName = htmlspecialchars($dstName);

$attributes = $this->data['attributes'];

$this->includeAtTemplateBase('consent:includes/header.php');

$dstCountry = '';
if (!empty($this->data['country'])) {
	$dstCountry = ' in '.$this->data['country'];
}

?>
<div class="login-user">
	<?php if ($this->data['useLogo'] === true) { ?>
	<h1><img src="images/logo.gif" alt="logo" /></h1>
	<?php } ?>

	<div class="box-line">
		<h2>User Information</h2>
		<a href="#" class="control open">More detail</a>
		<div class="contents">
			<ul class="bul-dot">
				<?php echo(present_attributes_keys($this, $attributes)); ?>
			</ul>
		</div>
	</div>

	<div class="table" data-ref="detail" style="display:none">
	<?php echo(present_attributes($this, $attributes, '')); ?>
	</div>

	<ul class="list">
		<li>
			<img src="images/login_user_01.gif" alt="" />
			<p>
			<?php
				echo htmlspecialchars($this->t('{consent:consent:consent_privacypolicy}')) . " ";
			    echo "<a target='_blank' href='" . htmlspecialchars($this->data['sppp']) . "'>" . $dstName . "</a>";				
			?>
			</p>
		</li>
		<li>
			<img src="images/login_user_02.gif" alt="" />
			<p>
			<?php
				echo $this->t('{consent:consent:consent_attributes_header}', array( 'SPNAME' =>  $dstName, 'IDPNAME' => $srcName, 'NATION' => $dstCountry));
			?>
			</p>
		</li>
		<li>
			<img src="images/login_user_03.gif" alt="" />
			<p>
			<?php
				echo $this->t('{consent:consent:consent_refuse_right_header}', array( 'SPNAME' => $dstName, 'IDPNAME' => $srcName));
			?>
			</p>
		</li>
		<!--
		<li>
			<img src="images/login_user_04.gif" alt="" />
			<p>This is an example. This is an example. This is an example.</p>
		</li>
		-->
	</ul>
	
	<form action="<?php echo htmlspecialchars($this->data['yesTarget']); ?>">
	<?php if ($this->data['usestorage']) { ?>
	<p class="login-ing">
		<input type="checkbox" id="saveconsent" name="saveconsent" <?php echo $this->data['checked'] ? 'checked="checked"' : ''; ?> value="1" />
		<label for="saveconsent"><?php echo $this->t('{consent:consent:remember}'); ?></label>
		<a href="" class="icon-tip">
			<img src="images/icon_tip.gif" alt="?" />
			<span>Remember to send it to <?php echo $dstName ?>.</span>
		</a>
	</p>
	<?php } ?>
	
	<div class="txt-center">
		<form action="<?php echo htmlspecialchars($this->data['yesTarget']); ?>">
		<?php
		foreach ($this->data['yesData'] as $name => $value) {
			echo '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />';
		}
		?>
		<input type="submit" class="btn-purple" name="yes"  value="<?php echo htmlspecialchars($this->t('{consent:consent:yes}')) ?>" />
		</form>

		<form action="<?php echo htmlspecialchars($this->data['noTarget']); ?>" method="get">
		<?php
		foreach ($this->data['noData'] as $name => $value) {
			echo '<input type="hidden" name="'.htmlspecialchars($name).'" value="'.htmlspecialchars($value).'" />';
		}
		?>
		<input type="submit" class="btn-gray" name="no" value="<?php echo htmlspecialchars($this->t('{consent:consent:no}')) ?>" />
	</div>
	</form>
</div>

<?php
function present_attributes_keys($t, $attributes) 
{
	$output = '';
    foreach ($attributes as $name => $value) {
        $name = $t->getAttributeTranslation($name);
		$output .= '<li>'.$name.'</li>'."\n";
	}
	return $output;
}

/**
 * Recursiv attribute array listing function
 *
 * @param SimpleSAML_XHTML_Template $t          Template object
 * @param array                     $attributes Attributes to be presented
 * @param string                    $nameParent Name of parent element
 *
 * @return string HTML representation of the attributes 
 */
function present_attributes($t, $attributes, $nameParent)
{
    $summary = 'summary="' . $t->t('{consent:consent:table_summary}') . '"';

    if (strlen($nameParent) > 0) {
        $parentStr = strtolower($nameParent) . '_';
        $str = '<table ' . $summary . '><tbody>';
    } else {
        $parentStr = '';
        $str = '<table '. $summary .'>';
        $str .= "\n" . '<caption>' . $t->t('{consent:consent:table_caption}') . '</caption><tbody>';
    }

    foreach ($attributes as $name => $value) {
        $nameraw = $name;
        $name = $t->getAttributeTranslation($parentStr . $nameraw);

        if (preg_match('/^child_/', $nameraw)) {
            // Insert child table
            $parentName = preg_replace('/^child_/', '', $nameraw);
            foreach ($value AS $child) {
                $str .= "\n" . '<tr><td style="padding: 2em" colspan="2">'.present_attributes($t, $child, $parentName) . '</td></tr>';
            }
        } else {
            // Insert values directly

            $str .= "\n" . '<tr><th scope="row">' . htmlspecialchars($name) .'</th><td>';

            $isHidden = in_array($nameraw, $t->data['hiddenAttributes'], true);
            if ($isHidden) {
                $hiddenId = SimpleSAML_Utilities::generateID();
                $str .= '<div class="attrvalue" style="display: none;" id="hidden_' . $hiddenId . '">';
            } else {
                $str .= '<div class="attrvalue">';
            }

            if (sizeof($value) > 1) {
                // We hawe several values
                $str .= '<ul>';
                foreach ($value AS $listitem) {
                    if ($nameraw === 'jpegPhoto') {
                        $str .= '<li><img src="data:image/jpeg;base64,' .
                            htmlspecialchars($listitem) .
                            '" alt="User photo" /></li>';
                    } else {
                        $str .= '<li>' . htmlspecialchars($listitem) . '</li>';
                    }
                }
                $str .= '</ul>';
            } elseif (isset($value[0])) {
                // We hawe only one value
                if ($nameraw === 'jpegPhoto') {
                    $str .= '<img src="data:image/jpeg;base64,' .
                        htmlspecialchars($value[0]) .
                        '" alt="User photo" />';
                } else {
                    $str .= htmlspecialchars(strip_tags($value[0]));
                }
            }   // end of if multivalue
            $str .= '</div>';

            if ($isHidden) {
                $str .= '<div class="attrvalue consent_showattribute" id="visible_' . $hiddenId . '">';
                $str .= '... ';
                $str .= '<a class="consent_showattributelink" href="javascript:SimpleSAML_show(\'hidden_' . $hiddenId . '\'); SimpleSAML_hide(\'visible_' . $hiddenId . '\');">';
                $str .= $t->t('{consent:consent:show_attribute}');
                $str .= '</a>';
                $str .= '</div>';
            }

            $str .= '</td></tr>';
        }       // end else: not child table
    }   // end foreach
    $str .= isset($attributes)? '</tbody></table>':'';
    return $str;
}

$this->includeAtTemplateBase('consent:includes/footer.php');

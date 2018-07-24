<?php
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
$dstName = htmlspecialchars($dstName);

$this->includeAtTemplateBase('consent:includes/header.php');
?>
	<div class="box-kreonet">
		<?php if ($this->data['useLogo'] === true) { ?>
		<p class="img">
			<img src="images/logo.gif" alt="logo" />
		</p>
		<?php } ?>

		<!-- logout No consent -->
		<div class="icon-ment">
			<span class="icon">
				<img src="images/icon_noconsent.png" alt="icon" />
			</span>
			<strong><?php echo $this->t('{consent:consent:noconsent_title}'); ?></strong>
			<p><?php echo $this->t('{consent:consent:noconsent_text}', array('SPNAME' => $dstName)); ?></p>
		</div>
		<!-- //logout No consent -->
	</div>

	<p class="btn-area">
		<?php if ($this->data['resumeFrom']) { ?>
		<a href="<?php echo htmlspecialchars($this->data['resumeFrom']) ?>" class="btn-purple"><?php echo $this->t('{consent:consent:noconsent_return}') ?></a>
		<?php } ?>

		<?php if ($this->data['aboutService']) { ?>
		<a href="<?php echo htmlspecialchars($this->data['aboutService']) ?>" class="btn-navylight"><?php echo $this->t('{consent:consent:noconsent_goto_about}') ?></a>
		<?php } ?>

		<a href="<?php echo htmlspecialchars($this->data['logoutLink']) ?>" class="btn-gray"><?php echo $this->t('{consent:consent:abort}', array('SPNAME' => $dstName)) ?></a>
	</p>
</div>

<?php
$this->includeAtTemplateBase('consent:includes/footer.php');

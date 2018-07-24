<?php
	$this->data['short'] = true;
	$this->includeAtTemplateBase('consent:includes/header.php');
?>
<div class="box-kreonet">
	<?php if ($this->data['useLogo'] === true) { ?>
	<p class="img">
		<img src="images/logo.gif" alt="logo" />
	</p>
	<?php } ?>

	<div class="icon-ment">
		<span class="icon">
			<img src="images/icon_noconsent.png" alt="icon" />
		</span>
		<strong><?php echo $this->t('{logout:title}'); ?></strong>
		<p><?php echo $this->t('{logout:logged_out_text}'); ?></p>
	</div>
</div>
<?php
	$this->includeAtTemplateBase('consent:includes/footer.php');

			</div>
		</div>
		<!-- //container-body -->
	</div>
</div>
<!-- //layout-container -->

<!-- layout-footer -->
<div id="layout-footer">
	<div id="footer">
		<p class="kafe">
			<img src="images/kafe.png" alt="KAFE" />
			Korean Access FEderation
		</p>

		<ul id="fnb">
			<li><a href="https://coreen.kreonet.net/user_agreement" target="_blank">PRIVACY POLICY</a></li>
			<li><a href="https://coreen.kreonet.net/privacy_policy" target="_blank">TERMS OF USE</a></li>
		</ul>
	</div>
</div>
<!-- //layout-footer -->

<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<script>
$(function() {
	$('.control').click(function(e) {
		e.preventDefault();
		var $t = $(this);
		var $detail = $('[data-ref=detail]');
		if ($t.hasClass('open')) {
			$t.removeClass('open');
			$detail.slideDown();
		} else {
			$t.addClass('open');
			$detail.slideUp();
		}
	});
});
</script>

</body>
</html>

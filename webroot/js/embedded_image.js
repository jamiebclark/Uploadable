$(document).ready(function() {
	$('.embedded-image-input-list').on('cloned', function(e, $content) {
		var $uid = $('input[name*="[uid]"]', $content),
			$inputsWithUid = $('input[name*="[uid]"]', $content),
			$copy = $('input[class*="input-copy"]', $content),
			$img = $('img', $content).remove(),
			uid = parseInt($uid.val());
		uid = isNaN(uid) ? 1 : uid + 1;
		$inputsWithUid.each(function() {
			$(this).removeAttr('id').val(uid);
		});
		$copy.val("<Photo " + uid + ">");
	});
});
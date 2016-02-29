(function($) {
	$.fn.cropbox = function() {
		return this.each(function() {
			var $image = $(this),
				imageW,
				imageH,
				selectW,
				selectH,
				aspectRatio = 1,
				scalePct = 1,
				selectPadding = 25;

			function updateCoords(c) {
				$('#x').val(c.x * scalePct);
				$('#y').val(c.y * scalePct);
				$('#w').val(c.w * scalePct);
				$('#h').val(c.h * scalePct);
			};

			function setScalePct() {
				var parentW = $image.parent().width();
				if (imageW > parentW) {
					scalePct = imageW / parentW;
					$image.width(parentW);
				} else {
					scalePct = 1;
				}
			}

			function cropboxInit () {
				if ($image.width() && !$image.data('cropbox-init')) {
					imageW = $image.width();
					imageH = $image.height();
					selectW = $image.data('select-w');
					selectH = $image.data('select-h');
					aspectRatio = selectW / selectH;

					console.log($image.data('select-w'));
					console.log($image.data('select-h'));
					var setSelectW = imageW - 2 * selectPadding,
						setSelectH = setSelectW / aspectRatio,
						x1 = selectPadding,
						y1 = (imageH - setSelectH) / 2,

						x2 = x1 + setSelectW,
						y2 = y1 + setSelectH;

					setScalePct();

					console.log([x1,y1,x2,y2]);

					if ($('#x').val()) {
						x1 = $('#x').val() / scalePct;
						y1 = $('#y').val() / scalePct;
						x2 = x1 + $('#w').val() / scalePct;
						y2 = y1 + $('#h').val() / scalePct;
					}

					console.log([x1,y1,x2,y2]);

					$image.Jcrop({
						aspectRatio: aspectRatio,
						onSelect: updateCoords,
						setSelect: [x1, y1, x2, y2]
					});

					$image.data('cropbox-init', true);
				}
			}

			$image.on('load', function() {		
				cropboxInit();
			});
			cropboxInit();
		});
	};

	$(document).ajaxComplete(function() {
		setTimeout(function() {
			$('#cropbox').cropbox();
		}, 500);
	});
	$(document).bind('ready', function() {
		$('#cropbox').cropbox();
	});
})(jQuery);
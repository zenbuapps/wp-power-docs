<?php


$args = $args ?? [];
/** @var array{toc_html: string} $args */
[
	'toc_html' => $toc_html,
] = $args;

if (!$toc_html) {
	return;
}

echo '<div class="sticky top-8">';
echo '<p class="text-base text-base-content font-bold mb-2">大綱：</p>';
echo $toc_html;
echo '</div>';

?>
<script type="module">
	(function($){
		$(document).ready(function(){
			// 讓 toc 的 a 點擊時，改變自己的顏色
			$('.pc-toc').on('click', 'a', function(){
				$(this).closest('.pc-toc').find('a').removeClass('text-primary').addClass('text-base-content');
				$(this).removeClass('text-base-content').addClass('text-primary');
			});
		});


		class TocToggler{
				_isOpen = false;
				$toggle = null;
				$blackWrap = null;
				$toc = null;

				constructor(){
					this.init();
					this.attachEvent();
				}

				get isOpen(){
					return this._isOpen;
				}

				set isOpen(value) {
					this._isOpen = value;

					if(this._isOpen){
						// 開啟時要做什麼
						this.$toc.animate({
							right: '0'
						}, 300);
						this.$blackWrap.fadeIn();
						return;
					}

					// 關閉時要做什麼
					this.$toc.animate({
						right: '-100%'
					}, 300);
					this.$blackWrap.fadeOut();
				}

				init(){
					this.$toggle = $('#doc-detail__toc-toggle');
					this.$blackWrap = $('#doc-detail__black-wrap');
					this.$toc = $('#doc-detail__toc');
				}

				attachEvent(){
					this.$toggle.click(() => {
						this.isOpen = !this.isOpen;
					});
					this.$blackWrap.click(() => {
						this.isOpen = false;
					});
				}
			}

			new TocToggler();
	})(jQuery)
</script>
<?php

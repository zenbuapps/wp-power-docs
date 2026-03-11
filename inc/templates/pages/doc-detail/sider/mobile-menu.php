<?php
/**
 * 手機板 Sider 出現的選單
 */

$args = $args ?? [];
/** @var array{toc_html: string} $args */
[
	'toc_html' => $toc_html,
] = $args;

?>
<div class="h-10 flex xl:tw-hidden items-center justify-between px-4" style="border-bottom: 1px solid var(--fallback-bc,oklch(var(--bc)/.1))">
	<div id="doc-detail__sider-toggle" class="flex items-center gap-x-2 text-sm cursor-pointer">
		<svg class="size-4 stroke-base-content/30" viewBox="0 0 48 48" fill="none">
			<path d="M42 9H6" stroke="#78716c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
			<path d="M34 19H6" stroke="#78716c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
			<path d="M42 29H6" stroke="#78716c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
			<path d="M34 39H6" stroke="#78716c" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"></path>
		</svg>
		選單
	</div>

	<div id="doc-detail__toc-toggle" class="flex items-center gap-x-2 text-sm cursor-pointer <?php echo $toc_html ? '' : 'tw-hidden'; ?>">
		大綱
		<svg class="size-4 stroke-base-content/30" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><g stroke-width="0"></g><g stroke-linecap="round" stroke-linejoin="round"></g><g> <path d="M8 6.00067L21 6.00139M8 12.0007L21 12.0015M8 18.0007L21 18.0015M3.5 6H3.51M3.5 12H3.51M3.5 18H3.51M4 6C4 6.27614 3.77614 6.5 3.5 6.5C3.22386 6.5 3 6.27614 3 6C3 5.72386 3.22386 5.5 3.5 5.5C3.77614 5.5 4 5.72386 4 6ZM4 12C4 12.2761 3.77614 12.5 3.5 12.5C3.22386 12.5 3 12.2761 3 12C3 11.7239 3.22386 11.5 3.5 11.5C3.77614 11.5 4 11.7239 4 12ZM4 18C4 18.2761 3.77614 18.5 3.5 18.5C3.22386 18.5 3 18.2761 3 18C3 17.7239 3.22386 17.5 3.5 17.5C3.77614 17.5 4 17.7239 4 18Z" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path> </g></svg>
	</div>
</div>

<div id="doc-detail__black-wrap" class="tw-fixed tw-hidden top-0 left-0 w-full h-full bg-black/50 z-[59]"></div>
<script type="module">
	(function($) {
		$(document).ready(function() {
			class SiderToggler{
				_isOpen = false;
				$toggle = null;
				$blackWrap = null;
				$sider = null;

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
						this.$sider.animate({
							left: '0'
						}, 300);
						this.$blackWrap.fadeIn();
						return;
					}

					// 關閉時要做什麼
					this.$sider.animate({
						left: '-100%'
					}, 300);
					this.$blackWrap.fadeOut();
				}

				init(){
					this.$toggle = $('#doc-detail__sider-toggle');
					this.$blackWrap = $('#doc-detail__black-wrap');
					this.$sider = $('#doc-detail__sider');
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

			new SiderToggler();
		});
	})(jQuery);
</script>

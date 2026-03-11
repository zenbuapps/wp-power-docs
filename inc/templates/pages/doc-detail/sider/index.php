<?php
/**
 * Sider 組件
 */

global $post;

use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;
use J7\PowerDocs\Domains\Doc\Utils;

$top_parent_id = PostUtils::get_top_post_id($post->ID);

$html = Utils::get_children_posts_html_uncached($top_parent_id);
?>

<style>
	.icon-arrow svg {
		transform: rotate(0deg);
		transition: all 0.3s ease-in-out;
	}

	.expanded .icon-arrow svg {
		transform: rotate(90deg);
		transition: all 0.3s ease-in-out;
	}
</style>

<div id="pd-sider" class="w-full h-full pr-2" style="display: none;border-right: 1px solid var(--fallback-bc,oklch(var(--bc)/.1));">
	<?php echo $html; ?>
</div>


<script type="module" async>
	(function($) {
		$(document).ready(function() {
			// 點擊箭頭展開或收合章節
			$('#pd-sider').on('click', 'li', function(e) {
				e.stopPropagation();
				e.preventDefault();

				const $li = $(this);
				const href = $li.data('href');
				const $sub_ul = $li.next('ul'); // 子章節

				if ($sub_ul.length > 0) {
					$li.toggleClass('expanded'); // 如果有找到子章節
					$sub_ul.slideToggle('fast'); // 如果有找到子章節
				}

				// 如果點擊的是箭頭，就只展開/收合，不要跳轉頁面
				if ($(e.target).closest('.icon-arrow').length > 0) {
					return;
				}

				if (href) {
					window.location.href = href;
				}
			})

			// 跳轉頁面前先記錄展開的章節
			$('#pd-sider').on('click', 'li a', function(e) {
				// 阻止原本的超連結行為
				e.preventDefault();
				e.stopPropagation();

				handle_save_expanded_post_ids()

				// 然後才跳轉頁面
				const href = $(this).attr('href');
				window.location.href = href;
			})

			// 離開頁面時，恢復章節的展開狀態
			$(window).on('beforeunload', function(e) {
				// 避免顯示確認框，不要使用 preventDefault()
				handle_save_expanded_post_ids()
			});

			restore_expanded_post_ids();

			// 把當前展開的章節 id 先記錄起來
			function handle_save_expanded_post_ids() {
				const expanded_post_ids = $('#pd-sider li.expanded').map(function() {
					return $(this).data('post-id');
				}).get();

				// 記錄到 sessionStorage
				sessionStorage.setItem('expanded_post_ids', JSON.stringify(expanded_post_ids));
			}

			// 恢復章節的展開狀態
			function restore_expanded_post_ids() {
				const expanded_post_ids_string = sessionStorage.getItem('expanded_post_ids') // 拿不到為 null
				const expanded_post_ids = expanded_post_ids_string ? JSON.parse(expanded_post_ids_string) : [];
				if (expanded_post_ids.length > 0) {
					expanded_post_ids.forEach(function(post_id) {
						const $li = $(`#pd-sider li[data-post-id="${post_id}"]`);
						if ($li.length > 0) {
							$li.addClass('expanded');
							$li.next('ul').show();
						}
					});
				}

				// 恢復完畢，清除 sessionStorage，顯示 #pd-sider
				sessionStorage.removeItem('expanded_post_ids');
				$('#pd-sider').show();
			}
		})
	})(jQuery)
</script>

<?php
/**
 * 知識庫首頁
 */

use J7\Powerhouse\Plugin as Powerhouse;
use J7\PowerDocs\Domains\Doc\CPT;
use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;

global $post;
$top_parent_id    = PostUtils::get_top_post_id($post->ID);
$all_children_ids = PostUtils::get_flatten_post_ids( $top_parent_id);

$search = $_GET['search'] ?? ''; // phpcs:ignore
$to     = $_GET['to'] ?? 1; // phpcs:ignore

$query = new \WP_Query(
	[
		'post_type'      => CPT::POST_TYPE,
		'posts_per_page' => 20,
		'paged'          => $to,
		's'              => $search,
		'post__in'       => [
			$top_parent_id,
			...$all_children_ids,
		],
	]
);

$search_posts = $query->posts;

Powerhouse::load_template('hero');

echo /* html */ '<div class="tw-container mx-auto mt-8 px-4">';


Powerhouse::load_template('breadcrumb/search');

// 所有分類區塊
printf(
	/*html*/
	'
<h6 class="text-lg md:text-2xl text-content mb-6">所有與 %1$s 相關的結果</h6>
',
	(string) $search
);

echo '<div id="pc-search-results">';
foreach ($search_posts as $search_post) {
	Powerhouse::load_template(
		'list',
		[
			'post' => $search_post,
		]
	);
}
echo '</div>';
echo '<div class="flex justify-center my-8">';
Powerhouse::load_template(
	'pagination',
	[
		'query' => $query,
	]
);
echo '</div>';

echo /* html */ '</div>';

?>

<script type="module" async>
	(function($) {
		$(document).ready(function() {
			// 取得 url params 上的 search 參數
			const urlParams = new URLSearchParams(window.location.search);
			const search = urlParams.get('search');

			if(search){
				highlightText(search);
			}

			function highlightText(keyword) {
				// 建立不區分大小寫的正則表達式
				const regex = new RegExp(`(${keyword})`, 'gi');
				$('#pc-search-results').find('*').contents().filter(function() {
					// 節點的純文字
					return this.nodeType === 3;
				}).each(function() {
					const text = $(this).text() || '';
					if (text.toLowerCase().includes(keyword.toLowerCase())) {
						const wrapped = text.replace(regex, `<span class="bg-warning">$1</span>`);
						$(this).replaceWith(wrapped)
					}
				});
			}

		});
	})(jQuery)
</script>

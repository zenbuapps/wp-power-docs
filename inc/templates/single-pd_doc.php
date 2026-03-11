<?php
/**
 * Template for single pd_doc
 */

use J7\PowerDocs\Plugin;
use J7\PowerDocs\Domains\Doc\Access;
use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;
use J7\Powerhouse\Theme\Core\FrontEnd as Theme;

global $post;

$top_parent_id = PostUtils::get_top_post_id($post->ID);

$can_access = Access::can_access( (int) $top_parent_id);
// 判斷用戶是否可以 manage_options
$is_admin = \current_user_can('manage_options');

if (!$can_access && !$is_admin) {
	// 沒有權限，跳到404
	$unauthorized_redirect_url = get_post_meta($top_parent_id, 'unauthorized_redirect_url', true) ?: site_url('404');
	/** @var string $unauthorized_redirect_url */
	wp_safe_redirect($unauthorized_redirect_url);
	exit;
}

if ('draft' === $post->post_status && !$is_admin) {
	wp_safe_redirect(site_url('404'));
	exit;
}

$search = $_GET['search'] ?? '';//phpcs:ignore

get_header();

echo '<div class="bg-base-200 pb-20">';

if ($search) {
	Plugin::load_template('doc-search');
} else {
	// 如果是頂層就顯示 doc-landing，否則顯示 doc-detail
	Plugin::load_template($post->post_parent ? 'doc-detail' : 'doc-landing');
}

echo '</div>';

Theme::render_button();



get_footer();

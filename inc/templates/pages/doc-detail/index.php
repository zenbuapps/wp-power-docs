<?php
/**
 * 文件詳情頁
 * 由 sider 和 main 組成
 */

use J7\PowerDocs\Plugin;
use J7\PowerDocs\Helper\TOCGenerator;


global $post;

$post_content = $post->post_content;

$toc          = new TOCGenerator($post_content);
$toc_html     = $toc->get_toc_html();
$content_html = $toc->get_html();

Plugin::load_template(
	'doc-detail/sider/mobile-menu',
	[
		'toc_html' => $toc_html,
	]
	);

echo /*html*/ '<div class="flex flex-col xl:flex-row tw-container mx-auto pt-8">';


echo /*html*/ '<div id="doc-detail__sider" class="
z-[60]
[&_#pd-sider]:py-4 pl-4 bg-base-200 h-screen overflow-auto w-3/4 max-[calc(100vw-3rem)] tw-fixed top-0 left-[-100%]
[&_#pd-sider]:xl:py-0 xl:pl-0 xl:bg-transparent xl:h-auto xl:overflow-visible xl:w-72 xl:block xl:relative xl:top-[unset] xl:left-[unset]">';
Plugin::load_template('doc-detail/sider');
echo /*html*/ '</div>';

echo /*html*/ '<div class="flex-1">';
Plugin::load_template(
	'doc-detail/main',
	[
		'content' => $content_html,
	]
	);
echo /*html*/ '</div>';

echo /*html*/ '<div id="doc-detail__toc" class="
z-[60]
[&_#pd-toc]:py-4 pl-4 bg-base-200 h-screen overflow-auto w-3/4 max-[calc(100vw-3rem)] tw-fixed top-0 right-[-100%]
[&_#pd-toc]:xl:py-0 xl:pl-0 xl:bg-transparent xl:h-auto xl:overflow-visible xl:w-72 xl:block xl:relative xl:top-[unset] xl:right-[unset]">';
Plugin::load_template(
	'doc-detail/toc',
	[
		'toc_html' => $toc_html,
	]
	);
echo /*html*/ '</div>';

echo /*html*/ '</div>';

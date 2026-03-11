<?php

declare( strict_types=1 );

namespace J7\PowerDocs\Domains\Doc;

use J7\PowerDocs\Plugin;
use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;

/**
 * 知識庫模板
 * 1. 版型覆寫
 * 2. 管理員工具列
 */
final class Templates {
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * Constructor
	 */
	public function __construct() {
		\add_filter('single_template', [ $this, 'template_override' ], 9999);
		\add_action( 'admin_bar_menu', [ $this, 'admin_bar_item' ], 210 );
	}

	/**
	 * 覆寫知識庫頁面
	 * [危險] 如果全域變數汙染，會導致無法預期行為
	 *
	 * @param string $template 原本的模板路徑
	 *
	 * @return string
	 */
	public function template_override( $template ) {

		global $post;
		$post_type = $post?->post_type;
		if ($post_type !== CPT::POST_TYPE) {
			return $template;
		}

		// 檢查主題複寫存不存在，不存在就用預設的
		$doc_post_type       = CPT::POST_TYPE;
		$dir                 = \get_stylesheet_directory();
		$theme_template_path = \wp_normalize_path("{$dir}/single-{$doc_post_type}.php");

		if (file_exists($theme_template_path)) {
			return $theme_template_path;
		}

		return \wp_normalize_path(Plugin::$dir . "/inc/templates/single-{$doc_post_type}.php");
	}

	/**
	 * 在管理員工具列中新增項目
	 *
	 * @param \WP_Admin_Bar $admin_bar 管理員工具列物件
	 *
	 * @return void
	 */
	public function admin_bar_item( \WP_Admin_Bar $admin_bar ): void {

		if ( ! \current_user_can( 'manage_options' ) ) {
			return;
		}

		global $post;

		if ( ! $post ) {
			return;
		}

		if ( CPT::POST_TYPE !== $post->post_type ) {
			return;
		}

		$post_id       = (int) $post->ID;
		$top_parent_id = PostUtils::get_top_post_id( $post_id );

		// 是課程銷售頁就顯示課程編輯
		$admin_bar->add_menu(
			[
				'id'     => Plugin::$kebab,
				'parent' => null,
				'group'  => null,
				'title'  => '編輯知識庫',
				'href'   => \admin_url("admin.php?page=power-docs#/docs/edit/{$top_parent_id}"),
				'meta'   => [
					'title' => \__( '編輯知識庫', 'power_docs' ),
				],
			]
		);
	}
}

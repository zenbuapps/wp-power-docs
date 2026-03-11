<?php
namespace J7\PowerDocs\Domains\Elementor;

if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
	return;
}

if ( class_exists( 'J7\PowerDocs\Domains\Elementor\Card' ) ) {
	return;
}

use J7\Powerhouse\Plugin as Powerhouse;
use J7\PowerDocs\Domains\Doc\CPT;

/**
 * Card
 * 第二層子分類的卡片
 */
final class Card extends \Elementor\Widget_Base {

	/**
	 * 取得 widget 名稱
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::class;
	}

	/**
	 * 取得 widget 標題
	 *
	 * @return string
	 */
	public function get_title(): string {
		return esc_html__( '知識庫子文章分類卡片', 'power_docs' );
	}

	/**
	 * 取得 widget 圖示
	 *
	 * @see https://elementor.github.io/elementor-icons/?referrer=wordpress.com
	 * @return string
	 */
	public function get_icon(): string {
		return 'eicon-kit-details';
	}

	/**
	 * Widget 要分類在哪個位置
	 * 可能的值: favorites, layout, basic, pro-elements, general, link-in-bio, theme-elements, elements-single, woocommerce-elements, WordPress
	 *
	 * @return array<string>
	 */
	public function get_categories(): array {
		return [ 'basic' ];
	}

	/**
	 * 關鍵字
	 *
	 * @return array<string>
	 */
	public function get_keywords(): array {
		return [ 'docs', 'doc', 'power', '知識庫', '卡片', 'card' ];
	}

	/**
	 * 渲染
	 *
	 * @return void
	 */
	protected function render(): void {
		/** @var array<string, mixed> $settings */
		$settings = $this->get_settings_for_display();

		if ( !isset($settings['post_id']) || !$settings['post_id'] ) {
			echo \esc_html__( '請選擇要顯示的子文章', 'power_docs' );
			return;
		}
		$post = \get_post( (int) $settings['post_id'] );

		Powerhouse::load_template(
			'card',
			[
				'post' => $post,
			]
			);
	}

	/**
	 * 控制項
	 */
	protected function register_controls(): void {

		// Content Tab Start
		$this->start_controls_section(
			'section_card',
			[
				'label' => esc_html__( '知識庫卡片設定', 'power_docs' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			]
		);

		global $post;

		$children_posts = \get_posts(
			[
				'post_type'      => CPT::POST_TYPE,
				'post_parent'    => $post->ID,
				'posts_per_page' => -1,
				'orderby'        => [
					'menu_order' => 'ASC',
					'ID'         => 'ASC',
					'date'       => 'ASC',
				],
			]
			);

		$options = [];
		foreach ( $children_posts as $child ) {
			$options[ $child->ID ] = $child->post_title;
		}

		$this->add_control(
			'post_id',
			[
				'label'   => esc_html__( '選擇子文章 ID', 'power_docs' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '',
				'options' => $options,
			]
		);

		$this->end_controls_section();

		// Content Tab End
	}
}

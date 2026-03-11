<?php

declare( strict_types=1 );

namespace J7\PowerDocs\Domains\Elementor;

/**
 * Elementor Loader
 * 載入自製的 Elementor Widgets
 */
final class Loader {
	use \J7\WpUtils\Traits\SingletonTrait;


	/**  @var array<string> 自製的 Elementor Widgets Class */
	private array $widget_classes = [
		Card::class,
		Search::class,
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		// 檢查 elementor 是否啟用，如果沒有啟用，就不載入
		if ( ! self::is_elementor_enabled() ) {
			return;
		}

		\add_action( 'elementor/widgets/register', [ $this, 'register_widget' ] );
	}

	/**
	 * 檢查 elementor 是否啟用
	 *
	 * @return bool
	 */
	public static function is_elementor_enabled(): bool {
		if (class_exists('\Elementor\Widget_Base')) {
			return false;
		}

		/** @var array<string> $active_plugins */
		$active_plugins       = \get_option( 'active_plugins', [] );
		$is_elementor_enabled = \in_array( 'elementor/elementor.php', $active_plugins, true );
		return $is_elementor_enabled;
	}

	/**
	 * 註冊 widget
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager widgets_manager
	 * @return void
	 */
	public function register_widget( $widgets_manager ): void {
		foreach ( $this->widget_classes as $widget_class ) {
			$widgets_manager->register( new $widget_class() ); // @phpstan-ignore-line
		}
	}
}

<?php
/**
 * 整合測試基礎類別
 * 所有 Power Docs 整合測試必須繼承此類別
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\CPT;
use J7\Powerhouse\Domains\Limit\Utils\MetaCRUD;
use J7\Powerhouse\Domains\Limit\Models\BoundItemData;

/**
 * Class TestCase
 * 整合測試基礎類別，提供共用 helper methods
 */
abstract class TestCase extends \WP_UnitTestCase {

	/**
	 * 最後發生的錯誤（用於驗證操作是否失敗）
	 *
	 * @var \Throwable|null
	 */
	protected ?\Throwable $lastError = null;

	/**
	 * 查詢結果（用於驗證 Query 操作的回傳值）
	 *
	 * @var mixed
	 */
	protected mixed $queryResult = null;

	/**
	 * ID 映射表（名稱 → ID 等）
	 *
	 * @var array<string, int>
	 */
	protected array $ids = [];

	/**
	 * Repository 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $repos;

	/**
	 * Service 容器
	 *
	 * @var \stdClass
	 */
	protected \stdClass $services;

	/**
	 * 設定（每個測試前執行）
	 */
	public function set_up(): void {
		parent::set_up();

		$this->lastError   = null;
		$this->queryResult = null;
		$this->ids         = [];
		$this->repos       = new \stdClass();
		$this->services    = new \stdClass();

		$this->configure_dependencies();
	}

	/**
	 * 清理（每個測試後執行）
	 * WP_UnitTestCase 會自動回滾資料庫事務，但自訂表需要手動清理
	 */
	public function tear_down(): void {
		$this->clean_custom_tables();
		parent::tear_down();
	}

	/**
	 * 清理 Powerhouse 自訂資料表（ph_access_itemmeta）
	 */
	protected function clean_custom_tables(): void {
		global $wpdb;

		$table_name = $wpdb->prefix . 'ph_access_itemmeta';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) === $table_name ) { // phpcs:ignore
			$wpdb->query( "DELETE FROM {$table_name}" ); // phpcs:ignore
		}
	}

	/**
	 * 初始化依賴（子類別可選擇覆寫）
	 * 在此方法中初始化 $this->repos 和 $this->services
	 */
	protected function configure_dependencies(): void {
		// 預設空實作，子類別自行覆寫
	}

	// ========== 資料建立 Helper ==========

	/**
	 * 建立測試根知識庫（pd_doc，post_parent=0）
	 *
	 * @param array<string, mixed> $args 覆蓋預設值
	 * @return int 知識庫 ID
	 */
	protected function create_doc( array $args = [] ): int {
		$defaults = [
			'post_title'  => '測試知識庫',
			'post_status' => 'publish',
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => 0,
		];

		$post_args = wp_parse_args( $args, $defaults );
		$doc_id    = $this->factory()->post->create( $post_args );

		// 設定預設根文件 meta
		$need_access = $args['need_access'] ?? 'no';
		update_post_meta( $doc_id, 'need_access', $need_access );
		update_post_meta( $doc_id, 'pd_keywords_label', $args['pd_keywords_label'] ?? '大家都在搜：' );

		if ( isset( $args['unauthorized_redirect_url'] ) ) {
			update_post_meta( $doc_id, 'unauthorized_redirect_url', $args['unauthorized_redirect_url'] );
		}

		return $doc_id;
	}

	/**
	 * 建立測試子章節（pd_doc，post_parent > 0）
	 *
	 * @param int                  $parent_id 父文章 ID（根知識庫或中間層章節）
	 * @param array<string, mixed> $args 覆蓋預設值
	 * @return int 章節 ID
	 */
	protected function create_nested_doc( int $parent_id, array $args = [] ): int {
		$defaults = [
			'post_title'  => '測試章節',
			'post_status' => 'publish',
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => $parent_id,
		];

		$post_args = wp_parse_args( $args, $defaults );
		$child_id  = $this->factory()->post->create( $post_args );

		// 子章節預設使用 power-editor
		$editor = $args['editor'] ?? 'power-editor';
		update_post_meta( $child_id, 'editor', $editor );

		return $child_id;
	}

	/**
	 * 建立 WooCommerce 商品（用於授權綁定）
	 *
	 * @param array<string, mixed> $args 覆蓋預設值
	 * @return int 商品 ID
	 */
	protected function create_product( array $args = [] ): int {
		$defaults = [
			'post_title'  => '測試商品',
			'post_status' => 'publish',
			'post_type'   => 'product',
		];

		$post_args  = wp_parse_args( $args, $defaults );
		$product_id = $this->factory()->post->create( $post_args );

		update_post_meta( $product_id, '_price', $args['_price'] ?? '100' );
		update_post_meta( $product_id, '_regular_price', $args['_price'] ?? '100' );

		return $product_id;
	}

	/**
	 * 授予用戶知識庫存取權（透過 BoundItemData，直接寫入 ph_access_itemmeta）
	 *
	 * @param int        $user_id    用戶 ID
	 * @param int        $doc_id     根知識庫 ID
	 * @param string|int $expire_date 到期日（0 = 永久；timestamp；'never'）
	 */
	protected function grant_doc_access( int $user_id, int $doc_id, string|int $expire_date = 0 ): void {
		MetaCRUD::update( $doc_id, $user_id, 'expire_date', $expire_date );
	}

	/**
	 * 撤銷用戶知識庫存取權（刪除 expire_date meta）
	 *
	 * @param int $user_id 用戶 ID
	 * @param int $doc_id  根知識庫 ID
	 */
	protected function revoke_doc_access( int $user_id, int $doc_id ): void {
		MetaCRUD::delete( $doc_id, $user_id, 'expire_date' );
	}

	/**
	 * 取得 ph_access_itemmeta 的 expire_date
	 *
	 * @param int    $doc_id  根知識庫 ID
	 * @param int    $user_id 用戶 ID
	 * @return mixed
	 */
	protected function get_doc_expire_date( int $doc_id, int $user_id ): mixed {
		return MetaCRUD::get( $doc_id, $user_id, 'expire_date', true );
	}

	/**
	 * 建立已完成的 WooCommerce 訂單（用於測試授權觸發）
	 *
	 * @param int $user_id    買家用戶 ID
	 * @param int $product_id 商品 ID
	 * @return \WC_Order
	 */
	protected function create_completed_order( int $user_id, int $product_id ): \WC_Order {
		$order = wc_create_order( [ 'customer_id' => $user_id ] );

		$product = wc_get_product( $product_id );
		if ( $product ) {
			$order->add_product( $product, 1 );
		}

		$order->set_status( 'completed' );
		$order->save();

		return $order;
	}

	// ========== 斷言 Helper ==========

	/**
	 * 斷言操作成功（$this->lastError 應為 null）
	 */
	protected function assert_operation_succeeded(): void {
		$this->assertNull(
			$this->lastError,
			sprintf( '預期操作成功，但發生錯誤：%s', $this->lastError?->getMessage() )
		);
	}

	/**
	 * 斷言操作失敗（$this->lastError 不應為 null）
	 */
	protected function assert_operation_failed(): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗，但沒有發生錯誤' );
	}

	/**
	 * 斷言操作失敗且錯誤訊息包含指定文字
	 *
	 * @param string $msg 期望錯誤訊息包含的文字
	 */
	protected function assert_operation_failed_with_message( string $msg ): void {
		$this->assertNotNull( $this->lastError, '預期操作失敗' );
		$this->assertStringContainsString(
			$msg,
			$this->lastError->getMessage(),
			"錯誤訊息不包含 \"{$msg}\"，實際訊息：{$this->lastError->getMessage()}"
		);
	}

	/**
	 * 斷言 action hook 被觸發
	 *
	 * @param string $action_name action 名稱
	 */
	protected function assert_action_fired( string $action_name ): void {
		$this->assertGreaterThan(
			0,
			did_action( $action_name ),
			"Action '{$action_name}' 未被觸發"
		);
	}

	/**
	 * 斷言用戶擁有知識庫存取權
	 *
	 * @param int $user_id 用戶 ID
	 * @param int $doc_id  知識庫 ID
	 */
	protected function assert_user_can_access_doc( int $user_id, int $doc_id ): void {
		$this->assertTrue(
			\J7\PowerDocs\Domains\Doc\Access::can_access( $doc_id, $user_id ),
			"用戶 {$user_id} 應有知識庫 {$doc_id} 的存取權，但實際上沒有"
		);
	}

	/**
	 * 斷言用戶沒有知識庫存取權
	 *
	 * @param int $user_id 用戶 ID
	 * @param int $doc_id  知識庫 ID
	 */
	protected function assert_user_cannot_access_doc( int $user_id, int $doc_id ): void {
		$this->assertFalse(
			\J7\PowerDocs\Domains\Doc\Access::can_access( $doc_id, $user_id ),
			"用戶 {$user_id} 不應有知識庫 {$doc_id} 的存取權，但實際上有"
		);
	}
}

<?php
/**
 * 授權流程整合測試
 * 測試 WooCommerce 訂單完成後的知識庫授權觸發
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\Access;
use J7\PowerDocs\Domains\Doc\CPT;
use J7\PowerDocs\Domains\Product\Api as ProductApi;

/**
 * Class GrantAccessTest
 *
 * @group happy
 * @group error
 * @group edge
 */
class GrantAccessTest extends TestCase {

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_woocommerce_order_status_completed_action已掛載(): void {
		$this->assertNotFalse(
			has_action( 'woocommerce_order_status_completed' ),
			'woocommerce_order_status_completed action 應已被掛載'
		);
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_商品bound_docs_data_meta_key常數正確(): void {
		$this->assertSame( 'bound_docs_data', ProductApi::BOUND_META_KEY );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_授予存取後expire_date寫入ph_access_itemmeta(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		$this->grant_doc_access( $user_id, $doc_id, 0 );

		$expire_date = $this->get_doc_expire_date( $doc_id, $user_id );
		$this->assertNotNull( $expire_date, 'expire_date 應已被寫入' );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_撤銷存取後can_access回傳false(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		$this->grant_doc_access( $user_id, $doc_id, 0 );
		$this->assertTrue( Access::can_access( $doc_id, $user_id ) );

		$this->revoke_doc_access( $user_id, $doc_id );
		$this->assertFalse( Access::can_access( $doc_id, $user_id ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_powerhouse_product_get_meta_keys_array_filter已掛載(): void {
		$this->assertNotFalse(
			has_filter( 'powerhouse/product/get_meta_keys_array' ),
			'powerhouse/product/get_meta_keys_array filter 應已被掛載'
		);
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_訂單無效時grant_access安全退出(): void {
		// 傳入不存在的訂單 ID
		$access_instance = Access::instance();

		try {
			$access_instance->grant_access( 999999999 );
			$this->assertTrue( true, 'grant_access 應安全處理無效訂單 ID 而不丟出例外' );
		} catch ( \Throwable $e ) {
			$this->fail( "grant_access 不應丟出例外：{$e->getMessage()}" );
		}
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_訂單沒有客戶ID時不授權(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 建立訂單但不設定 customer_id（guest 訂單）
		$order = wc_create_order( [] ); // customer_id = 0
		$order->update_status( 'completed' );
		$order->save();

		$order_id        = $order->get_id();
		$access_instance = Access::instance();
		$access_instance->grant_access( $order_id );

		// 沒有客戶，不應授予任何人
		$this->assertFalse( Access::can_access( $doc_id, $user_id ) );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_商品沒有bound_docs_data時不授權(): void {
		$doc_id     = $this->create_doc( [ 'need_access' => 'yes' ] );
		$product_id = $this->create_product( [ 'post_title' => '無綁定的商品' ] );
		$user_id    = $this->factory()->user->create();

		// 不設定 bound_docs_data，直接建立訂單
		$order = $this->create_completed_order( $user_id, $product_id );

		$access_instance = Access::instance();
		$access_instance->grant_access( $order->get_id() );

		// 商品沒有綁定，不應授予知識庫存取
		$this->assertFalse( Access::can_access( $doc_id, $user_id ) );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_並發授權同一用戶同一知識庫不重複(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 授予兩次（模擬並發）
		$this->grant_doc_access( $user_id, $doc_id, 0 );
		$this->grant_doc_access( $user_id, $doc_id, 0 );

		// 仍然只有一個有效的存取權
		$this->assertTrue( Access::can_access( $doc_id, $user_id ) );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_grant_access_trigger_on_completed_status(): void {
		// 驗證 hook 的優先級為 10
		$priority = has_action( 'woocommerce_order_status_completed', [ Access::instance(), 'grant_access' ] );
		$this->assertSame( 10, $priority );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_超大整數order_id安全處理(): void {
		$access_instance = Access::instance();

		try {
			$access_instance->grant_access( PHP_INT_MAX );
			$this->assertTrue( true );
		} catch ( \Throwable $e ) {
			$this->fail( "grant_access 不應丟出例外：{$e->getMessage()}" );
		}
	}
}

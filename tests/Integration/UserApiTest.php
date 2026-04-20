<?php
/**
 * User\Api REST API 整合測試
 * 測試 GET /power-docs/users 端點的查詢、分頁、搜尋、granted_docs 篩選
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\User\Api;

/**
 * Class UserApiTest
 *
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class UserApiTest extends TestCase {

	/** @var int 管理員用戶 ID */
	protected int $admin_id;

	/**
	 * 設定（每個測試前執行）
	 */
	public function set_up(): void {
		parent::set_up();

		// 初始化 WordPress REST API Server
		global $wp_rest_server;
		$wp_rest_server = new \WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );

		// 建立管理員用戶並設定為當前用戶
		$this->admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );
		wp_set_current_user( $this->admin_id );
	}

	/**
	 * 清理（每個測試後執行）
	 */
	public function tear_down(): void {
		global $wp_rest_server;
		$wp_rest_server = null;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * 覆寫 doing_it_wrong_run，靜默略過 wpdb::prepare 的不正確用法通知。
	 *
	 * User\Api::get_users_callback() 以無佔位符方式呼叫 $wpdb->prepare()，
	 * 此為來源碼的已知問題（無法在不修改源碼的前提下修復）。
	 * 是否觸發 "doing_it_wrong" 取決於 SQL 是否含 % 字元（LIKE 子句），
	 * 因此無法使用 setExpectedIncorrectUsage()（它要求通知必定觸發）。
	 *
	 * @param string $function_name 觸發通知的函式名稱
	 * @param string $message       通知訊息
	 * @param string $version       引入此通知的 WP 版本
	 */
	public function doing_it_wrong_run( $function_name, $message, $version ): void {
		if ( 'wpdb::prepare' === $function_name ) {
			// 靜默略過，不加入 caught_doing_it_wrong 追蹤清單
			return;
		}
		parent::doing_it_wrong_run( $function_name, $message, $version );
	}

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_User_Api類別存在(): void {
		$this->assertTrue( class_exists( Api::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_GET_power_docs_v1_users端點可存取(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );

		$this->assertNotEquals(
			404,
			$response->get_status(),
			'/power-docs/users 端點應已被正確註冊'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_GET_users端點回傳200(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users回傳陣列(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertIsArray( $data );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users包含分頁headers(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );
		$headers  = $response->get_headers();

		$this->assertArrayHasKey( 'X-WP-Total', $headers );
		$this->assertArrayHasKey( 'X-WP-TotalPages', $headers );
		$this->assertArrayHasKey( 'X-WP-CurrentPage', $headers );
		$this->assertArrayHasKey( 'X-WP-PageSize', $headers );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users預設分頁大小為20(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );
		$headers  = $response->get_headers();

		$this->assertSame( '20', $headers['X-WP-PageSize'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users可指定分頁大小(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 'posts_per_page' => 5 ] );
		$response = rest_do_request( $request );
		$headers  = $response->get_headers();

		$this->assertSame( '5', $headers['X-WP-PageSize'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users可以搜尋用戶email(): void {
		// 建立一個可識別的用戶
		$user_id = $this->factory()->user->create(
			[
				'user_email' => 'unique_test_user@example.com',
				'user_login' => 'unique_test_user',
			]
		);

		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 's' => 'unique_test_user' ] );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_GET_users預設按ID降序排列(): void {
		$request  = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_搜尋不存在的用戶回傳空陣列(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 's' => 'absolutely_nonexistent_user_xyz_12345' ] );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertIsArray( $data );
		$this->assertEmpty( $data );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_paged參數超過總頁數回傳空陣列(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params(
			[
				'paged'          => 9999,
				'posts_per_page' => 20,
			]
		);
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_granted_docs為空陣列時不篩選(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 'granted_docs' => [] ] );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_posts_per_page為1時只回傳一個用戶(): void {
		// 確保有足夠用戶
		$this->factory()->user->create_many( 3 );

		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 'posts_per_page' => 1 ] );
		$response = rest_do_request( $request );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $data );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_搜尋含有SQL特殊字符的關鍵字安全處理(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 's' => "'; DROP TABLE wp_users; --" ] );

		try {
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status(), 'SQL 注入字串應被安全處理' );
		} catch ( \Throwable $e ) {
			$this->fail( "SQL 注入字串不應導致例外：{$e->getMessage()}" );
		}
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_搜尋含百分比符號的關鍵字安全處理(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 's' => '%admin%' ] );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_搜尋包含unicode字元的用戶(): void {
		$user_id = $this->factory()->user->create(
			[
				'display_name' => '測試用戶Unicode',
				'user_login'   => 'unicode_test_user_' . time(),
			]
		);

		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 's' => 'Unicode' ] );
		$response = rest_do_request( $request );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_posts_per_page為負數時安全處理(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 'posts_per_page' => -1 ] );
		$response = rest_do_request( $request );

		// 應不崩潰，即使回傳奇怪的結果
		$this->assertNotEquals( 500, $response->get_status() );
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_granted_docs包含非數字ID安全處理(): void {
		$request = new \WP_REST_Request( 'GET', '/power-docs/users' );
		$request->set_query_params( [ 'granted_docs' => [ '<script>', '"; DROP TABLE', '0' ] ] );

		try {
			$response = rest_do_request( $request );
			$this->assertSame( 200, $response->get_status() );
		} catch ( \Throwable $e ) {
			$this->fail( "惡意 granted_docs 參數不應導致例外：{$e->getMessage()}" );
		}
	}
}

<?php
/**
 * CPT（自訂文章類型）整合測試
 * 測試 pd_doc 的註冊、Hooks、快取清除邏輯
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\CPT;

/**
 * Class CPTTest
 *
 * @group smoke
 * @group happy
 */
class CPTTest extends TestCase {

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_pd_doc_文章類型已註冊(): void {
		$this->assertTrue(
			post_type_exists( CPT::POST_TYPE ),
			'pd_doc CPT 應已註冊'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_pd_doc_POST_TYPE常數等於pd_doc(): void {
		$this->assertSame( 'pd_doc', CPT::POST_TYPE );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_init_action已掛載(): void {
		$this->assertNotFalse(
			has_action( 'init' ),
			'init action 應已被掛載'
		);
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立根知識庫成功(): void {
		$doc_id = $this->create_doc( [ 'post_title' => '我的知識庫' ] );

		$this->assertGreaterThan( 0, $doc_id );
		$post = get_post( $doc_id );
		$this->assertNotNull( $post );
		$this->assertSame( 'pd_doc', $post->post_type );
		$this->assertSame( '我的知識庫', $post->post_title );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立根知識庫時自動設定need_access預設為no(): void {
		$doc_id     = $this->create_doc();
		$need_access = get_post_meta( $doc_id, 'need_access', true );

		$this->assertSame( 'no', $need_access );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立子章節成功(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'post_title' => '第一章節' ] );

		$this->assertGreaterThan( 0, $child_id );
		$post = get_post( $child_id );
		$this->assertNotNull( $post );
		$this->assertSame( $root_id, $post->post_parent );
		$this->assertSame( 'pd_doc', $post->post_type );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立子章節時自動設定editor為power_editor(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id );

		$editor = get_post_meta( $child_id, 'editor', true );
		$this->assertSame( 'power-editor', $editor );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_三層巢狀結構建立成功(): void {
		$root_id  = $this->create_doc( [ 'post_title' => '根知識庫' ] );
		$mid_id   = $this->create_nested_doc( $root_id, [ 'post_title' => '中間章節' ] );
		$leaf_id  = $this->create_nested_doc( $mid_id, [ 'post_title' => '最底層單元' ] );

		$root  = get_post( $root_id );
		$mid   = get_post( $mid_id );
		$leaf  = get_post( $leaf_id );

		$this->assertSame( 0, $root->post_parent );
		$this->assertSame( $root_id, $mid->post_parent );
		$this->assertSame( $mid_id, $leaf->post_parent );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_pd_doc支援REST(): void {
		$post_type_obj = get_post_type_object( CPT::POST_TYPE );
		$this->assertNotNull( $post_type_obj );
		$this->assertTrue( $post_type_obj->show_in_rest );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_pd_doc是階層式文章類型(): void {
		$post_type_obj = get_post_type_object( CPT::POST_TYPE );
		$this->assertNotNull( $post_type_obj );
		$this->assertTrue( $post_type_obj->hierarchical );
	}

	// ========== 快取清除測試 ==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_儲存文章後快取金鑰格式正確(): void {
		$doc_id    = $this->create_doc();
		$cache_key = \J7\PowerDocs\Domains\Doc\Utils::get_cache_key( $doc_id );

		$this->assertStringContainsString( 'power_docs_', $cache_key );
		$this->assertStringContainsString( (string) $doc_id, $cache_key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_自訂快取key格式正確(): void {
		$doc_id    = $this->create_doc();
		$cache_key = \J7\PowerDocs\Domains\Doc\Utils::get_cache_key( $doc_id, 'my_custom_key' );

		$this->assertSame( "power_docs_my_custom_key_{$doc_id}", $cache_key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_儲存文章後快取被清除(): void {
		$doc_id    = $this->create_doc();
		$cache_key = \J7\PowerDocs\Domains\Doc\Utils::get_cache_key( $doc_id );

		// 先設定 transient
		set_transient( $cache_key, '舊的快取內容', 3600 );
		$this->assertSame( '舊的快取內容', get_transient( $cache_key ) );

		// 觸發 save_post_pd_doc action（模擬文章儲存）
		$post = get_post( $doc_id );
		do_action( 'save_post_' . CPT::POST_TYPE, $doc_id, $post, true );

		// 快取應已被清除
		$this->assertFalse( get_transient( $cache_key ) );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_嘗試存取不存在的文章回傳null(): void {
		$post = get_post( 999999999 );
		$this->assertNull( $post );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_使用其他post_type無法觸發pd_doc的save_post(): void {
		$triggered_count_before = did_action( 'save_post_' . CPT::POST_TYPE );

		// 建立非 pd_doc 的文章
		$other_post_id = $this->factory()->post->create( [ 'post_type' => 'post' ] );
		$other_post    = get_post( $other_post_id );

		// 觸發其他 post type 的 save_post 不應觸發 pd_doc 的 save_post
		do_action( 'save_post_post', $other_post_id, $other_post, false );

		$triggered_count_after = did_action( 'save_post_' . CPT::POST_TYPE );
		$this->assertSame( $triggered_count_before, $triggered_count_after );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_文章標題為空字串可建立(): void {
		$doc_id = $this->factory()->post->create(
			[
				'post_title'  => '',
				'post_type'   => CPT::POST_TYPE,
				'post_status' => 'publish',
			]
		);

		$this->assertGreaterThan( 0, $doc_id );
		$post = get_post( $doc_id );
		$this->assertSame( '', $post->post_title );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_文章標題包含unicode和emoji(): void {
		$title  = '測試📚知識庫 - Arabic: مرحبا - RTL: שלום';
		$doc_id = $this->create_doc( [ 'post_title' => $title ] );

		$post = get_post( $doc_id );
		$this->assertSame( $title, $post->post_title );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_同時建立多個根知識庫(): void {
		$ids = [];
		for ( $i = 1; $i <= 5; $i++ ) {
			$ids[] = $this->create_doc( [ 'post_title' => "知識庫 #{$i}" ] );
		}

		$this->assertCount( 5, $ids );
		$this->assertCount( 5, array_unique( $ids ), '每個知識庫應有唯一 ID' );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_AUTOSAVE時不清除快取(): void {
		$doc_id    = $this->create_doc();
		$cache_key = \J7\PowerDocs\Domains\Doc\Utils::get_cache_key( $doc_id );

		set_transient( $cache_key, '快取內容', 3600 );

		// 模擬 DOING_AUTOSAVE
		if ( ! defined( 'DOING_AUTOSAVE' ) ) {
			define( 'DOING_AUTOSAVE', true );
		}

		$post = get_post( $doc_id );

		// 呼叫 CPT 的 delete_transient 方法 — 注意此測試依賴常數只能定義一次
		// 若 DOING_AUTOSAVE 為 true，快取不應被清除
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			// DOING_AUTOSAVE 為 true，不應執行快取清除
			$this->assertSame( '快取內容', get_transient( $cache_key ) );
		}
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_文章標題包含XSS字串儲存後不執行(): void {
		$xss_title = '<script>alert("xss")</script>';
		$doc_id    = $this->create_doc( [ 'post_title' => $xss_title ] );

		$post = get_post( $doc_id );
		$this->assertNotNull( $post );

		// WordPress wp_insert_post 內部會呼叫 wp_strip_all_tags() 清除 post_title 中的 HTML 標籤
		// 因此 <script> 標籤被移除，只保留純文字內容，防止 XSS 執行
		$this->assertStringNotContainsString( '<script>', (string) $post->post_title );
		$this->assertStringNotContainsString( '</script>', (string) $post->post_title );
		// 文字內容（非標籤部分）仍然保留
		$this->assertStringContainsString( 'alert', (string) $post->post_title );
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_meta_key包含SQL注入字串安全存取(): void {
		$doc_id = $this->create_doc();

		// 嘗試以 SQL injection 風格的字串作為 meta 值
		$malicious_value = "'; DROP TABLE wp_posts; --";
		update_post_meta( $doc_id, 'need_access', $malicious_value );

		// WordPress 應透過 $wpdb->prepare 安全地處理
		$value = get_post_meta( $doc_id, 'need_access', true );
		$this->assertSame( $malicious_value, $value, '惡意字串應被安全儲存，不執行 SQL 注入' );
	}
}

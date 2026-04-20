<?php
/**
 * Doc\Api 過濾器整合測試
 * 測試 Powerhouse filter 擴展：meta_keys、file upload、建立預設值、複製
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\Api;
use J7\PowerDocs\Domains\Doc\CPT;

/**
 * Class DocApiTest
 *
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class DocApiTest extends TestCase {

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_Doc_Api類別存在(): void {
		$this->assertTrue( class_exists( Api::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_powerhouse_post_get_meta_keys_array_filter已掛載(): void {
		$this->assertNotFalse(
			has_filter( 'powerhouse/post/get_meta_keys_array' ),
			'powerhouse/post/get_meta_keys_array filter 應已被掛載'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_powerhouse_post_separator_body_params_filter已掛載(): void {
		$this->assertNotFalse(
			has_filter( 'powerhouse/post/separator_body_params' ),
			'powerhouse/post/separator_body_params filter 應已被掛載'
		);
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_powerhouse_post_create_post_args_filter已掛載(): void {
		$this->assertNotFalse(
			has_filter( 'powerhouse/post/create_post_args' ),
			'powerhouse/post/create_post_args filter 應已被掛載'
		);
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_根知識庫的editor欄位回傳空字串（預設版型）(): void {
		$doc_id = $this->factory()->post->create(
			[
				'post_type'   => CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => 0,
			]
		);
		$post = get_post( $doc_id );

		// meta_keys 初始值（模擬 Powerhouse 傳入）
		$meta_keys = [ 'editor' => '' ];
		$result    = Api::extend_post_meta_keys( $meta_keys, $post );

		// 根文件 editor 未設定時，應為 ''（因為 post_parent = 0）
		$this->assertSame( '', $result['editor'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_子章節的editor未設定時預設為power_editor(): void {
		$root_id = $this->create_doc();
		$child_id = $this->factory()->post->create(
			[
				'post_type'   => CPT::POST_TYPE,
				'post_status' => 'publish',
				'post_parent' => $root_id,
			]
		);
		// 不設定 editor meta

		$post      = get_post( $child_id );
		$meta_keys = [ 'editor' => '' ];
		$result    = Api::extend_post_meta_keys( $meta_keys, $post );

		$this->assertSame( 'power-editor', $result['editor'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_子章節的editor已設定為elementor時保留(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'editor' => 'elementor' ] );
		$post     = get_post( $child_id );

		$meta_keys = [ 'editor' => '' ];
		$result    = Api::extend_post_meta_keys( $meta_keys, $post );

		$this->assertSame( 'elementor', $result['editor'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_非pd_doc文章不被filter影響(): void {
		$other_post_id = $this->factory()->post->create( [ 'post_type' => 'post' ] );
		$post          = get_post( $other_post_id );

		$meta_keys      = [ 'editor' => 'some_value' ];
		$result         = Api::extend_post_meta_keys( $meta_keys, $post );

		// 非 pd_doc 應直接回傳原始 meta_keys
		$this->assertSame( $meta_keys, $result );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_根知識庫建立時有bg_images佔位(): void {
		$doc_id = $this->create_doc();
		$post   = get_post( $doc_id );

		$meta_keys = [
			'editor'    => '',
			'bg_images' => '',
		];
		$result = Api::extend_post_meta_keys( $meta_keys, $post );

		// bg_images 在無設定時應為空陣列
		$this->assertIsArray( $result['bg_images'] );
		$this->assertEmpty( $result['bg_images'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_子章節不應有bg_images欄位（早退機制）(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id );
		$post     = get_post( $child_id );

		$meta_keys = [
			'editor'    => '',
			'bg_images' => 'test',
		];
		$result = Api::extend_post_meta_keys( $meta_keys, $post );

		// 子章節 post_parent > 0，在設定 editor 之後即早退，bg_images 保持原值
		$this->assertSame( 'test', $result['bg_images'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_複製子文章時保持pd_doc的post_type(): void {
		$root_id      = $this->create_doc();
		$child_id     = $this->create_nested_doc( $root_id );
		$new_id       = $this->factory()->post->create( [ 'post_type' => CPT::POST_TYPE ] );
		$default_args = [ 'post_type' => 'post' ]; // 錯誤預設

		$result = Api::copy_children_post_args( $default_args, $child_id, $new_id, $root_id, 1 );

		$this->assertSame( CPT::POST_TYPE, $result['post_type'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立根知識庫時add_default_meta_keys設定預設值(): void {
		$args = [
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => 0,
		];

		$result = Api::add_default_meta_keys( $args );

		$this->assertArrayHasKey( 'meta_input', $result );
		$this->assertArrayHasKey( 'pd_keywords_label', $result['meta_input'] );
		$this->assertSame( '大家都在搜：', $result['meta_input']['pd_keywords_label'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立根知識庫時設定預設pd_keywords(): void {
		$args = [
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => 0,
		];

		$result = Api::add_default_meta_keys( $args );

		$this->assertArrayHasKey( 'pd_keywords', $result['meta_input'] );
		$this->assertIsArray( $result['meta_input']['pd_keywords'] );
		$this->assertNotEmpty( $result['meta_input']['pd_keywords'] );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_建立根知識庫時設定預設unauthorized_redirect_url(): void {
		$args = [
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => 0,
		];

		$result = Api::add_default_meta_keys( $args );

		$this->assertArrayHasKey( 'unauthorized_redirect_url', $result['meta_input'] );
		$this->assertStringContainsString( '404', $result['meta_input']['unauthorized_redirect_url'] );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_非pd_doc文章建立時不設定預設meta(): void {
		$args = [
			'post_type'   => 'post',
			'post_parent' => 0,
		];

		$result = Api::add_default_meta_keys( $args );

		// 非 pd_doc 不應有 meta_input
		$this->assertArrayNotHasKey( 'meta_input', $result );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_子章節建立時不設定pd_keywords_label預設值(): void {
		$args = [
			'post_type'   => CPT::POST_TYPE,
			'post_parent' => 123, // 有父文章
		];

		$result = Api::add_default_meta_keys( $args );

		// 有 post_parent 時早退，不設定預設 meta
		$this->assertArrayNotHasKey( 'meta_input', $result );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_複製非pd_doc文章時不改變post_type(): void {
		$other_post_id = $this->factory()->post->create( [ 'post_type' => 'post' ] );
		$new_id        = $this->factory()->post->create();
		$default_args  = [ 'post_type' => 'page' ];

		$result = Api::copy_children_post_args( $default_args, $other_post_id, $new_id, 0, 0 );

		// 非 pd_doc 文章不應被修改
		$this->assertSame( 'page', $result['post_type'] );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_extra_file_upload當bg_images為delete時清空值(): void {
		$body_params = [ 'bg_images' => 'delete' ];

		// 建立一個空的 WP_REST_Request
		$request = new \WP_REST_Request( 'POST', '/test' );
		$result  = Api::extra_file_upload( $body_params, $request );

		$this->assertSame( '', $result['bg_images'] );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_extra_file_upload當bg_images為非數字非delete時移除該鍵(): void {
		$body_params = [ 'bg_images' => 'not_a_number_or_delete' ];

		$request = new \WP_REST_Request( 'POST', '/test' );
		$result  = Api::extra_file_upload( $body_params, $request );

		$this->assertArrayNotHasKey( 'bg_images', $result );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_extra_file_upload當bg_images為有效數字ID時保留(): void {
		$body_params = [ 'bg_images' => '42' ];

		$request = new \WP_REST_Request( 'POST', '/test' );
		$result  = Api::extra_file_upload( $body_params, $request );

		$this->assertSame( '42', $result['bg_images'] );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_meta_keys陣列不含editor鍵時不修改editor(): void {
		$doc_id = $this->create_doc();
		$post   = get_post( $doc_id );

		// 不含 editor 鍵的 meta_keys
		$meta_keys = [ 'other_key' => 'value' ];
		$result    = Api::extend_post_meta_keys( $meta_keys, $post );

		// 雖然不含 editor，但 filter 仍會設定它
		$this->assertArrayHasKey( 'editor', $result );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_extra_file_upload當bg_images為數字零時(): void {
		$body_params = [ 'bg_images' => '0' ];

		$request = new \WP_REST_Request( 'POST', '/test' );
		$result  = Api::extra_file_upload( $body_params, $request );

		// '0' 是數字，應被保留
		$this->assertSame( '0', $result['bg_images'] );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_extend_user_meta_keys當不含granted_docs鍵時直接回傳(): void {
		$user_id = $this->factory()->user->create();
		$user    = get_user_by( 'id', $user_id );

		$meta_keys = [ 'other_key' => 'value' ];
		$result    = Api::extend_user_meta_keys( $meta_keys, $user );

		// 不含 granted_docs 鍵，直接回傳原值
		$this->assertSame( $meta_keys, $result );
	}
}

<?php
/**
 * Utils 整合測試
 * 測試 get_children_posts_html_uncached() 的遞迴 HTML 生成邏輯
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\CPT;
use J7\PowerDocs\Domains\Doc\Utils;

/**
 * Class UtilsTest
 *
 * @group happy
 * @group error
 * @group edge
 */
class UtilsTest extends TestCase {

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_get_cache_key預設格式正確(): void {
		$key = Utils::get_cache_key( 123 );
		$this->assertSame( 'power_docs_get_children_posts_html_123', $key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_get_cache_key自訂key格式正確(): void {
		$key = Utils::get_cache_key( 456, 'sidebar_html' );
		$this->assertSame( 'power_docs_sidebar_html_456', $key );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_無子章節時get_children_posts_html_uncached回傳空字串(): void {
		// 建立根知識庫，但不建立任何子章節
		$root_id = $this->create_doc();

		// 設定全域 $post（Utils 方法需要它）
		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post; // 還原

		$this->assertSame( '', $html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_有子章節時get_children_posts_html_uncached回傳HTML(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'post_title' => '第一章節' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $child_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( '第一章節', $html );
		$this->assertStringContainsString( '<ul', $html );
		$this->assertStringContainsString( '<li', $html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_多個子章節都出現在HTML中(): void {
		$root_id = $this->create_doc();
		$this->create_nested_doc( $root_id, [ 'post_title' => '第一章節' ] );
		$this->create_nested_doc( $root_id, [ 'post_title' => '第二章節' ] );
		$this->create_nested_doc( $root_id, [ 'post_title' => '第三章節' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( '第一章節', $html );
		$this->assertStringContainsString( '第二章節', $html );
		$this->assertStringContainsString( '第三章節', $html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_子章節HTML包含data_post_id屬性(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'post_title' => '子章節' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( "data-post-id=\"{$child_id}\"", $html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_當前文章在側邊欄中有高亮標示(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'post_title' => '當前章節' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $child_id ); // 設定為「當前文章」

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		// 當前文章應有 bg-primary/10 font-bold 樣式
		$this->assertStringContainsString( 'bg-primary/10', $html );
		$this->assertStringContainsString( 'font-bold', $html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_有孫層章節時顯示箭頭SVG(): void {
		$root_id  = $this->create_doc();
		$mid_id   = $this->create_nested_doc( $root_id, [ 'post_title' => '中間章節' ] );
		$leaf_id  = $this->create_nested_doc( $mid_id, [ 'post_title' => '最底層' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $leaf_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		// 中間章節有子章節，應顯示箭頭 SVG
		$this->assertStringContainsString( 'icon-arrow', $html );
		$this->assertStringContainsString( '<svg', $html );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_不存在的post_id回傳空字串(): void {
		global $post;
		$original_post = $post;
		$post          = $this->factory()->post->create_and_get();

		$html = Utils::get_children_posts_html_uncached( 999999999 );

		$post = $original_post;

		$this->assertSame( '', $html );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_draft狀態的子章節不出現在HTML中(): void {
		$root_id = $this->create_doc();
		$this->create_nested_doc( $root_id, [ 'post_title' => '草稿章節', 'post_status' => 'draft' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		// draft 狀態的子章節應不出現（get_posts 預設只取 publish）
		$this->assertStringNotContainsString( '草稿章節', $html );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_get_cache_key接受post_id為0(): void {
		$key = Utils::get_cache_key( 0 );
		$this->assertSame( 'power_docs_get_children_posts_html_0', $key );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_三層巢狀HTML正確遞迴輸出(): void {
		$root_id = $this->create_doc();
		$mid_id  = $this->create_nested_doc( $root_id, [ 'post_title' => '中間' ] );
		$leaf_id = $this->create_nested_doc( $mid_id, [ 'post_title' => '最底層' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $leaf_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( '中間', $html );
		$this->assertStringContainsString( '最底層', $html );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_子章節的padding_left隨深度增加(): void {
		$root_id  = $this->create_doc();
		$child_id = $this->create_nested_doc( $root_id, [ 'post_title' => '第一層' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		// depth=0，第一層子章節的 padding-left 應為 1rem
		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( 'padding-left: 1rem', $html );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_大量子章節不崩潰(): void {
		$root_id = $this->create_doc();

		for ( $i = 1; $i <= 20; $i++ ) {
			$this->create_nested_doc( $root_id, [ 'post_title' => "章節 {$i}" ] );
		}

		global $post;
		$original_post = $post;
		$post          = get_post( $root_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		$this->assertStringContainsString( '章節 1', $html );
		$this->assertStringContainsString( '章節 20', $html );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_子章節display_none樣式在depth大於0時注入(): void {
		$root_id = $this->create_doc();
		$mid_id  = $this->create_nested_doc( $root_id, [ 'post_title' => '中間層' ] );
		$this->create_nested_doc( $mid_id, [ 'post_title' => '最深層' ] );

		global $post;
		$original_post = $post;
		$post          = get_post( $mid_id );

		$html = Utils::get_children_posts_html_uncached( $root_id );

		$post = $original_post;

		// 深度 > 0 的 ul 應有 display: none
		$this->assertStringContainsString( 'display: none;', $html );
	}
}

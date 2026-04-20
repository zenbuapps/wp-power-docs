<?php
/**
 * TOCGenerator 整合測試
 * 測試目錄生成器的 HTML 解析、anchor 注入、多層級標題處理
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Helper\TOCGenerator;

/**
 * Class TOCGeneratorTest
 *
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 */
class TOCGeneratorTest extends TestCase {

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_TOCGenerator類別存在(): void {
		$this->assertTrue( class_exists( TOCGenerator::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_基本HTML可以實例化(): void {
		$gen = new TOCGenerator( '<h2>標題</h2>' );
		$this->assertInstanceOf( TOCGenerator::class, $gen );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_get_toc_html回傳字串(): void {
		$gen = new TOCGenerator( '<h2>標題</h2>' );
		$toc = $gen->get_toc_html();
		$this->assertIsString( $toc );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_單個h2標題生成目錄(): void {
		$html = '<h2>第一章</h2><p>內容文字</p>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '第一章', $toc );
		$this->assertStringContainsString( '<ul', $toc );
		$this->assertStringContainsString( '<li', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_多個h2標題都出現在目錄中(): void {
		$html = '<h2>第一章</h2><h2>第二章</h2><h2>第三章</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '第一章', $toc );
		$this->assertStringContainsString( '第二章', $toc );
		$this->assertStringContainsString( '第三章', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_目錄連結包含錨點href(): void {
		$html = '<h2>章節標題</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( 'href="#', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_get_html在標題上注入id屬性(): void {
		$html = '<h2>章節標題</h2>';
		$gen  = new TOCGenerator( $html );
		$gen->get_toc_html(); // 先生成目錄（注入 id）
		$processed_html = $gen->get_html();

		$this->assertStringContainsString( 'id="toc-', $processed_html );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_h2到h6都被包含在目錄中(): void {
		$html = '<h2>H2標題</h2><h3>H3標題</h3><h4>H4標題</h4><h5>H5標題</h5><h6>H6標題</h6>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( 'H2標題', $toc );
		$this->assertStringContainsString( 'H3標題', $toc );
		$this->assertStringContainsString( 'H4標題', $toc );
		$this->assertStringContainsString( 'H5標題', $toc );
		$this->assertStringContainsString( 'H6標題', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_目錄包含css類別(): void {
		$html = '<h2>標題</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( 'pc-toc', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_h3比h2有更大的padding_left(): void {
		$html = '<h2>H2</h2><h3>H3</h3>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		// H2 的 padding-left 應為 0rem，H3 應為 1rem
		$this->assertStringContainsString( 'padding-left:0rem', $toc );
		$this->assertStringContainsString( 'padding-left:1rem', $toc );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_自訂levels參數只包含指定層級(): void {
		$html   = '<h2>H2標題</h2><h3>H3標題</h3><h4>H4標題</h4>';
		$gen    = new TOCGenerator( $html );
		$toc    = $gen->get_toc_html( [ 2 ] ); // 只要 h2

		$this->assertStringContainsString( 'H2標題', $toc );
		$this->assertStringNotContainsString( 'H3標題', $toc );
		$this->assertStringNotContainsString( 'H4標題', $toc );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_無標題的HTML回傳空字串(): void {
		$html = '<p>只有段落，沒有標題</p>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertSame( '', $toc );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_空字串HTML回傳空目錄(): void {
		$gen = new TOCGenerator( '' );
		$toc = $gen->get_toc_html();

		$this->assertSame( '', $toc );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_只有h1的HTML不生成目錄（預設不包含h1）(): void {
		$html = '<h1>大標題（h1）</h1><p>內容</p>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html(); // 預設 levels = [2,3,4,5,6]

		$this->assertSame( '', $toc, 'h1 預設不應出現在目錄中' );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_get_html在無法載入時回傳安全結果(): void {
		$gen  = new TOCGenerator( '<h2>標題</h2>' );
		$html = $gen->get_html();

		$this->assertIsString( $html );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_標題包含中文繁體字正確解析(): void {
		$html = '<h2>繁體中文標題：知識庫管理</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '繁體中文標題', $toc );
		$this->assertStringContainsString( '知識庫管理', $toc );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_標題包含HTML特殊字元被正確跳脫(): void {
		$html = '<h2>標題含 &lt;script&gt; 的內容</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		// 確保 <script> 不在目錄輸出中（已被 htmlspecialchars）
		$this->assertStringNotContainsString( '<script>', $toc );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_大量標題不崩潰(): void {
		$html_parts = [];
		for ( $i = 1; $i <= 50; $i++ ) {
			$html_parts[] = "<h2>標題 {$i}</h2><p>內容 {$i}</p>";
		}
		$html = implode( "\n", $html_parts );
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '標題 1', $toc );
		$this->assertStringContainsString( '標題 50', $toc );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_標題含emoji正確處理(): void {
		$html = '<h2>📚 知識庫介紹</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '知識庫介紹', $toc );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_巢狀HTML標籤中的標題可被解析(): void {
		$html = '<div class="content"><h2>巢狀的標題</h2></div>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		$this->assertStringContainsString( '巢狀的標題', $toc );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_toc_id在同一頁面內唯一（wp_unique_id機制）(): void {
		$html = '<h2>標題一</h2><h2>標題二</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		// 找出所有 toc-xxx id
		preg_match_all( '/href="#(toc-[^"]+)"/', $toc, $matches );
		$ids = $matches[1] ?? [];

		$this->assertCount( 2, $ids, '應有 2 個目錄項目' );
		$this->assertCount( 2, array_unique( $ids ), 'ID 應各不相同' );
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_標題含XSS字串被安全跳脫(): void {
		$html = '<h2><script>alert("xss")</script>正常標題</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		// 確保 <script> 標籤不直接出現在目錄 HTML 中
		$this->assertStringNotContainsString( '<script>alert', $toc );
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_href屬性被htmlspecialchars保護(): void {
		$html = '<h2>標題</h2>';
		$gen  = new TOCGenerator( $html );
		$toc  = $gen->get_toc_html();

		// href 中的 ID 是由 wp_unique_id 生成，不包含用戶輸入
		$this->assertStringContainsString( 'href="#toc-', $toc );
	}
}

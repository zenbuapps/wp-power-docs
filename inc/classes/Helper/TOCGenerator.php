<?php

declare(strict_types=1);

namespace J7\PowerDocs\Helper;

/**
 * TOC 生成器類
 *
 * 用於解析 HTML 內容並生成目錄結構
 * 支援多層級標題的目錄生成
 */
class TOCGenerator {

	/**  @var \DOMDocument DOM 文檔物件 */
	private $dom;

	/** @var \DOMXPath XPath 查詢物件 */
	private $xpath;

	/** @var string 生成的目錄 HTML 字串 */
	private $toc = '';

	/** @var string 原始 HTML 內容 */
	private $raw_html = '';

	/** @var \WP_Error 錯誤物件 */
	private $error_handler;

	/**
	 * 構造函數
	 *
	 * 初始化 DOM 物件並設置錯誤處理
	 */
	public function __construct( string $html ) {
		$this->raw_html      = $html;
		$this->error_handler = new \WP_Error();
		$this->dom           = new \DOMDocument();
		// 設置錯誤處理
		libxml_use_internal_errors(true);
		$this->load_html($html);
	}

	/**
	 * 載入 HTML 內容
	 *
	 * 將 HTML 字串載入到 DOM 物件中，並初始化 XPath
	 *
	 * @param string $html HTML 內容字串
	 * @return bool 載入是否成功
	 * @throws \Exception 如果載入 HTML 時發生錯誤
	 */
	private function load_html( string $html ) {
		try {
			// 先將 HTML 包裝在一個臨時的根元素中，以確保正確解析
			$wrapped_html = sprintf(
				'<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>%s</body></html>',
				$html
			);

			// 轉換編碼
			$wrapped_html = mb_convert_encoding($wrapped_html, 'HTML-ENTITIES', 'UTF-8');

			$this->dom->loadHTML($wrapped_html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
			$this->xpath = new \DOMXPath($this->dom);
			return true;
		} catch (\Exception $e) {
			$this->error_handler->add('load_html_error', $e->getMessage());
			return false;
		}
	}

	/**
	 * 生成目錄
	 *
	 * 解析 HTML 內容中的標題標籤，生成階層式目錄結構
	 * 會自動為標題添加錨點 ID，並生成對應的目錄連結
	 *
	 * @param array<int> $levels 要包含的標題層級 (預設 h2-h6)
	 * @return string 生成的目錄 HTML
	 * @throws \Exception 如果生成目錄時發生錯誤
	 */
	public function get_toc_html( array $levels = [ 2, 3, 4, 5, 6 ] ) {
		try {
			// 構建 XPath 查詢
			$heading_query = '//h' . implode('|//h', $levels);
			/**
			 * @var \DOMNodeList<\DOMElement> $heading_elements
			 * DOMNodeList 物件有以下特性：
			 * 1. 只有 length 屬性可用於檢查是否有標題
			 * 2. 實現了 Traversable 介面，可用於 foreach 遍歷
			 * 3. 每個元素都是 DOMElement 物件
			 */
			$heading_elements = $this->xpath->query($heading_query);

			if ($heading_elements->length === 0) {
				return '';
			}

			$this->toc = '<ul class="pc-toc list-none pl-0">' . PHP_EOL;

			foreach ($heading_elements as $heading) {
				/** @var \DOMElement $heading */
				// 由於 nodeName 和 textContent 是 DOM 原生屬性，我們只能在賦值時改名
				$level = (int) substr($heading->nodeName, 1); // phpcs:ignore
				$text  = trim($heading->textContent); // phpcs:ignore
				$id    = 'toc-' . \wp_unique_id();

				// 為標題添加 ID
				$heading->setAttribute('id', $id);

				$padding_left = $level - $levels[0];

				$this->toc .= sprintf(
					/*html*/'
					<li style="padding-left:%3$s">
						<a class="no-underline text-base-content hover:text-primary text-sm" href="#%1$s">%2$s</a>
					</li>' . PHP_EOL,
					htmlspecialchars($id),
					htmlspecialchars($text),
					"{$padding_left}rem"
				);
			}

			$this->toc .= '</ul>';
			return $this->toc;
		} catch (\Exception $e) {
			$this->error_handler->add('toc_generation_error', $e->getMessage());
			return '';
		}
	}

	/**
	 * 獲取處理後的 HTML
	 *
	 * 將修改後的 DOM 轉換回 HTML 字串
	 *
	 * @return string 完整的 HTML 內容
	 * @throws \Exception 如果生成 HTML 時發生錯誤
	 */
	public function get_html(): string {
		try {
			// 只獲取 body 內的內容
			$body = $this->dom->getElementsByTagName('body')->item(0);

			if (!$body) {
				return '';
			}

			$html = '';
			foreach ($body->childNodes as $child) { // phpcs:ignore
				$html .= $this->dom->saveHTML($child);
			}

			return $html;
		} catch (\Exception $e) {
			$this->error_handler->add('html_generation_error', $e->getMessage());
			return $this->raw_html;
		}
	}
}

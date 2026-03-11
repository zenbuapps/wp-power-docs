@ignore
Feature: 前台檢視知識庫（ViewDocFrontend）

  用戶訪問知識庫前台頁面，系統根據存取權限和文章層級，
  渲染對應的版型（首頁、詳情、搜尋）。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
      | 10     | 小明   | ming@example.com   | subscriber    |
      | 11     | 小華   | hua@example.com    | subscriber    |
    And 系統中有以下知識庫：
      | postId | title    | post_parent | need_access | unauthorized_redirect_url | status  |
      | 100    | PHP 入門 | 0           | yes         | https://example.com/buy   | publish |
      | 101    | 第一章   | 100         |             |                           | publish |
      | 102    | 草稿章   | 100         |             |                           | draft   |
      | 200    | 免費教學 | 0           | no          |                           | publish |
      | 201    | 免費第一 | 200         |             |                           | publish |
    And 系統中有以下知識庫授權：
      | post_id | user_id | meta_key    | meta_value |
      | 100     | 10      | expire_date | 2027-01-01 |
      | 100     | 11      | expire_date | 2025-01-01 |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- need_access=yes 且未授權的用戶被跳轉
    Example: 未登入用戶訪問需授權知識庫
      Given 用戶未登入
      When 訪問 /pd_doc/php-入門/
      Then 跳轉至 https://example.com/buy

    Example: 授權已過期的用戶被跳轉
      Given 用戶 userId=11 已登入（expire_date 已過期）
      When 訪問 /pd_doc/php-入門/
      Then 跳轉至 https://example.com/buy

  Rule: 前置（狀態）- 管理員不受存取限制
    Example: 管理員可存取需授權知識庫
      Given 用戶 userId=1 已登入
      When 訪問 /pd_doc/php-入門/
      Then 正常顯示知識庫頁面

  Rule: 前置（狀態）- 草稿僅管理員可存取
    Example: 一般用戶無法存取草稿章節
      Given 用戶 userId=10 已登入
      When 訪問 /pd_doc/草稿章/
      Then 跳轉至 site_url('404')

    Example: 管理員可存取草稿章節
      Given 用戶 userId=1 已登入
      When 訪問 /pd_doc/草稿章/
      Then 正常顯示文章詳情頁面

  Rule: 前置（狀態）- need_access=no 的知識庫任何人可存取
    Example: 免費知識庫無需登入即可存取
      Given 用戶未登入
      When 訪問 /pd_doc/免費教學/
      Then 正常顯示知識庫首頁

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 根知識庫渲染 doc-landing 版型
    Example: 訪問根知識庫顯示首頁
      Given 用戶 userId=10 已登入
      When 訪問 /pd_doc/php-入門/
      Then 渲染 doc-landing 版型（hero + 子分類卡片）

  Rule: 後置（回應）- 子章節渲染 doc-detail 版型
    Example: 訪問子章節顯示詳情頁
      Given 用戶 userId=10 已登入
      When 訪問 /pd_doc/第一章/
      Then 渲染 doc-detail 版型（側邊欄 + 內容 + 目錄 TOC）

  Rule: 後置（回應）- search 參數渲染搜尋結果版型
    Example: 帶搜尋參數時顯示搜尋結果
      Given 用戶 userId=10 已登入
      When 訪問 /pd_doc/php-入門/?search=變數
      Then 渲染 doc-search 版型
      And 搜尋結果中的關鍵字被高亮標記

  Rule: 後置（回應）- Elementor 版型支援
    Example: 使用 elementor 編輯器的根知識庫用 the_content 渲染
      Given 知識庫 postId=100 的 editor 為 "elementor"
      And 用戶 userId=10 已登入
      When 訪問 /pd_doc/php-入門/
      Then 使用 the_content() 渲染 Elementor 內容

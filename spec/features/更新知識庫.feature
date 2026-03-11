@ignore
Feature: 更新知識庫（UpdateDoc）

  管理員透過後台編輯知識庫或章節的內容、設定、背景圖等，
  透過 Powerhouse REST API 更新 pd_doc 文章。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫：
      | postId | title    | post_parent | editor       |
      | 100    | PHP 入門 | 0           |              |
      | 101    | 第一章   | 100         | power-editor |
      | 102    | 第二章   | 100         | elementor    |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- bg_images 支援上傳、刪除或不動作
    Example: 上傳背景圖片
      Given 用戶 userId=1 已登入
      When 送出 PATCH /wp-json/powerhouse/v1/posts/100，附帶 bg_images 檔案
      Then 檔案上傳成功，bg_images meta 儲存為 attachment ID

    Example: 刪除背景圖片
      Given 用戶 userId=1 已登入
      When 送出 PATCH /wp-json/powerhouse/v1/posts/100，bg_images = "delete"
      Then bg_images meta 清空為 ""

    Example: 不修改背景圖片
      Given 用戶 userId=1 已登入
      When 送出 PATCH /wp-json/powerhouse/v1/posts/100，bg_images 為非數字非 delete 的值
      Then bg_images meta 不更新

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 儲存後清除 transient 快取
    Example: 更新章節後快取被清除
      Given 用戶 userId=1 已登入
      When 儲存文章 postId=101
      Then transient key "power_docs_get_children_posts_html_100" 被刪除

  Rule: 後置（狀態）- 切換為 power-editor 時清除 elementor 資料
    Example: 將章節從 elementor 切換為 power-editor
      Given 用戶 userId=1 已登入
      And 文章 postId=102 有 _elementor_data meta
      When 儲存文章 postId=102 且 editor = "power-editor"
      Then 所有 _elementor_* 開頭的 meta 被刪除

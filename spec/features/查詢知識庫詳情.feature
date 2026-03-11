@ignore
Feature: 查詢知識庫詳情（GetDocDetail）

  管理員在知識庫編輯頁面取得完整知識庫資料，包含所有 meta 欄位。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫：
      | postId | title    | post_parent | bg_images | need_access | editor       |
      | 100    | PHP 入門 | 0           | 999       | yes         |              |
      | 101    | 第一章   | 100         |           |             |              |
      | 102    | 第二章   | 100         |           |             | elementor    |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 需指定 meta_keys 取得擴充欄位
    Example: 查詢知識庫詳情需帶 meta_keys
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/powerhouse/v1/posts/100?meta_keys[]=need_access&meta_keys[]=bg_images&meta_keys[]=pd_keywords&meta_keys[]=pd_keywords_label&meta_keys[]=unauthorized_redirect_url&with_description=true
      Then 回應包含 need_access, bg_images, pd_keywords, pd_keywords_label, unauthorized_redirect_url, description 欄位

  # ========== 後置（回應）==========

  Rule: 後置（回應）- bg_images 回傳完整圖片資訊
    Example: 有背景圖的知識庫回傳圖片陣列
      Given 用戶 userId=1 已登入
      When 查詢知識庫 postId=100
      Then bg_images 回傳包含圖片 URL、寬高等完整資訊的陣列

  Rule: 後置（回應）- 子章節的 editor 預設為 power-editor
    Example: 未設定 editor 的子章節預設為 power-editor
      Given 用戶 userId=1 已登入
      When 查詢章節 postId=101
      Then editor 欄位回傳 "power-editor"

    Example: 已設定 editor 的子章節保持原值
      Given 用戶 userId=1 已登入
      When 查詢章節 postId=102
      Then editor 欄位回傳 "elementor"

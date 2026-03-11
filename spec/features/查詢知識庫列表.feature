@ignore
Feature: 查詢知識庫列表（GetDocs）

  管理員在知識庫列表頁面查詢所有根層級的知識庫。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫：
      | postId | title    | post_parent | status  | need_access |
      | 100    | PHP 入門 | 0           | publish | yes         |
      | 101    | 第一章   | 100         | publish | (無)        |
      | 200    | JS 進階  | 0           | draft   | no          |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 僅查詢根層級且 post_type 為 pd_doc
    Example: 列表僅顯示根知識庫
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/powerhouse/v1/posts?post_type=pd_doc（根層級）
      Then 回應包含 postId=100 和 postId=200
      And 不包含 postId=101（子章節）

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 回傳知識庫完整基本資料
    Example: 每筆知識庫包含必要欄位
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/powerhouse/v1/posts?post_type=pd_doc
      Then 每筆知識庫包含以下欄位：
        | 欄位         | 說明           |
        | id           | 文章 ID        |
        | name         | 標題           |
        | slug         | 網址別名       |
        | status       | 發佈狀態       |
        | permalink    | 前台連結       |
        | images       | 縮圖           |
        | need_access  | 是否需要授權   |

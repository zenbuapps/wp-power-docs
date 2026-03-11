@ignore
Feature: 建立知識庫（CreateDoc）

  管理員透過後台 React SPA 建立新的知識庫或章節，
  透過 Powerhouse REST API 建立 pd_doc 類型的文章。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
      | 10     | 小明   | ming@example.com   | subscriber    |
    And 系統中有以下知識庫：
      | postId | title    | post_parent |
      | 100    | PHP 入門 | 0           |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- post_type 必須為 pd_doc
    Example: 建立非 pd_doc 類型不觸發預設 meta
      Given 用戶 userId=1 已登入
      When 送出 POST /wp-json/powerhouse/v1/posts：
        | post_type | post_title |
        | post      | 一般文章   |
      Then 不設定 pd_keywords_label 和 pd_keywords 預設值

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 根知識庫自動建立預設 meta
    Example: 建立新的根知識庫
      Given 用戶 userId=1 已登入
      When 送出 POST /wp-json/powerhouse/v1/posts：
        | post_type | post_title | post_parent |
        | pd_doc    | 新知識庫   | 0           |
      Then 文章建立成功
      And meta pd_keywords_label 預設為 "大家都在搜："
      And meta pd_keywords 預設為 [{id: "some_keyword_id", title: "某個關鍵字"}]
      And meta unauthorized_redirect_url 預設為 site_url('404')

  Rule: 後置（狀態）- 子章節不建立預設 meta
    Example: 在知識庫下建立子章節
      Given 用戶 userId=1 已登入
      When 送出 POST /wp-json/powerhouse/v1/posts：
        | post_type | post_title | post_parent |
        | pd_doc    | 第一章     | 100         |
      Then 文章建立成功
      And 不設定 pd_keywords_label 預設值
      And editor 預設為 "power-editor"

@ignore
Feature: 刪除知識庫（DeleteDoc）

  管理員透過後台刪除知識庫或章節，支援單筆刪除和批量刪除。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫：
      | postId | title    | post_parent |
      | 100    | PHP 入門 | 0           |
      | 101    | 第一章   | 100         |
      | 102    | 第二章   | 100         |
      | 103    | 1-1 節   | 101         |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 用戶必須為管理員
    Example: 非管理員無法刪除
      Given 用戶 userId=10 已登入（role=subscriber）
      When 送出 DELETE /wp-json/powerhouse/v1/posts/101
      Then 回應 403 Forbidden

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 單筆刪除成功
    Example: 刪除單一章節
      Given 用戶 userId=1 已登入
      When 送出 DELETE /wp-json/powerhouse/v1/posts/102
      Then 文章 postId=102 被刪除

  Rule: 後置（狀態）- 批量刪除成功
    Example: 批量刪除多個章節
      Given 用戶 userId=1 已登入
      When 送出批量刪除 ids=[101, 102]
      Then 文章 postId=101 和 postId=102 被刪除

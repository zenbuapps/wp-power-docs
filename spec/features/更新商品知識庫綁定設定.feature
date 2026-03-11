@ignore
Feature: 更新商品知識庫綁定設定（UpdateBoundDocs）

  管理員在「知識庫權限綁定」頁面，修改已綁定知識庫的期限設定。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下商品：
      | productId | name     | bound_docs_data                                         |
      | 50        | PHP 課程 | [{post_id: 100, limit_type: "fixed", days: 365}]        |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須選取已綁定的知識庫
    Example: 未選取知識庫時無法更新
      Given 用戶 userId=1 已登入
      And 選取商品 productId=50
      And 未從 GcdItemsTags 選取任何知識庫
      When 點擊更新綁定按鈕
      Then 無任何操作

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功更新已綁定知識庫的期限設定
    Example: 將固定期限改為永久
      Given 用戶 userId=1 已登入
      And 選取商品 productId=50
      And 從 GcdItemsTags 選取知識庫 postId=100
      And 設定 limit_type 為 "unlimited"
      When 點擊更新綁定按鈕
      Then 商品 productId=50 的 bound_docs_data 中 post_id=100 的設定更新為：
        | post_id | limit_type |
        | 100     | unlimited  |

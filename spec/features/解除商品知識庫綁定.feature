@ignore
Feature: 解除商品知識庫綁定（UnbindDocsFromProduct）

  管理員在「知識庫權限綁定」頁面，移除商品上已綁定的知識庫授權。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下商品：
      | productId | name     | bound_docs_data                          |
      | 50        | PHP 課程 | [{post_id: 100}, {post_id: 200}]         |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須選取已綁定的知識庫才能解除
    Example: 未選取任何已綁定知識庫時按鈕無反應
      Given 用戶 userId=1 已登入
      And 選取商品 productId=50
      And 未從 GcdItemsTags 選取任何知識庫
      When 點擊解除綁定按鈕
      Then 無任何操作

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功解除指定知識庫綁定
    Example: 解除商品上的一個知識庫綁定
      Given 用戶 userId=1 已登入
      And 選取商品 productId=50
      And 從 GcdItemsTags 選取知識庫 postId=100
      When 點擊解除綁定按鈕
      Then 商品 productId=50 的 bound_docs_data 僅剩：
        | post_id |
        | 200     |

@ignore
Feature: 查詢商品列表（GetProducts）

  管理員在「知識庫權限綁定」頁面查詢商品列表，
  包含每個商品已綁定的知識庫授權資料。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下商品：
      | productId | name       | type         | bound_docs_data                          |
      | 50        | PHP 課程   | simple       | [{post_id: 100, limit_type: "fixed"}]    |
      | 60        | 訂閱方案   | subscription | []                                       |
      | 70        | 可變商品   | variable     | []                                       |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 必須帶 meta_keys 包含 bound_docs_data
    Example: 查詢商品需要帶 bound_docs_data meta_key
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/powerhouse/v1/products?meta_keys[]=bound_docs_data
      Then 每個商品回應包含 bound_docs_data 欄位

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 已綁定知識庫的商品回傳完整綁定資料
    Example: 商品包含 bound_docs_data
      Given 用戶 userId=1 已登入
      When 查詢商品 productId=50
      Then bound_docs_data 回傳：
        | post_id | limit_type |
        | 100     | fixed      |

  Rule: 後置（回應）- 未綁定知識庫的商品回傳空陣列
    Example: 商品未綁定知識庫
      Given 用戶 userId=1 已登入
      When 查詢商品 productId=60
      Then bound_docs_data 回傳 []

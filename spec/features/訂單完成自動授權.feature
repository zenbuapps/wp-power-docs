@ignore
Feature: 訂單完成自動授權（GrantAccessOnOrderCompleted）

  WooCommerce 訂單完成時，自動檢查訂單商品是否綁定知識庫，
  若有則為用戶寫入存取權限到 ph_access_itemmeta 資料表。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email             | role       |
      | 10     | 小明   | ming@example.com  | subscriber |
    And 系統中有以下知識庫：
      | postId | title      | need_access |
      | 100    | PHP 入門   | yes         |
      | 200    | JS 進階    | yes         |
    And 系統中有以下商品：
      | productId | name       | bound_docs_data                              |
      | 50        | PHP 課程包 | [{post_id: 100, limit_type: "fixed", days: 365}] |
      | 60        | 空白商品   |                                              |

  # ========== 前置（狀態）==========

  Rule: 前置（狀態）- 訂單必須有登入用戶
    Example: 未登入用戶的訂單不授權
      Given 一筆訂單 order_id=1001 的 customer_id 為 0
      And 訂單包含商品 productId=50
      When WooCommerce 觸發 woocommerce_order_status_completed(1001)
      Then 不寫入任何 ph_access_itemmeta 記錄

  Rule: 前置（狀態）- 訂單商品必須有 bound_docs_data
    Example: 商品未綁定知識庫時不授權
      Given 一筆訂單 order_id=1002 的 customer_id 為 10
      And 訂單包含商品 productId=60
      When WooCommerce 觸發 woocommerce_order_status_completed(1002)
      Then 不寫入任何 ph_access_itemmeta 記錄

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功授權後寫入到期日
    Example: 購買綁定知識庫的商品後自動授權
      Given 一筆訂單 order_id=1003 的 customer_id 為 10
      And 訂單包含商品 productId=50
      When WooCommerce 觸發 woocommerce_order_status_completed(1003)
      Then ph_access_itemmeta 新增一筆記錄：
        | post_id | user_id | meta_key    | meta_value |
        | 100     | 10      | expire_date | (計算值)   |

  Rule: 後置（狀態）- 訂單包含多個綁定商品時全部授權
    Example: 訂單有多個綁定知識庫的商品
      Given 一筆訂單 order_id=1004 的 customer_id 為 10
      And 商品 productId=50 的 bound_docs_data 包含 post_id=100
      And 另一商品 productId=70 的 bound_docs_data 包含 post_id=200
      And 訂單包含商品 productId=50 和 productId=70
      When WooCommerce 觸發 woocommerce_order_status_completed(1004)
      Then ph_access_itemmeta 新增兩筆記錄分別對應 post_id=100 和 post_id=200

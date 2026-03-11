@ignore
Feature: 綁定知識庫到商品（BindDocsToProduct）

  管理員在「知識庫權限綁定」頁面，將知識庫授權資料綁定到 WooCommerce 商品上，
  設定購買該商品後可獲得哪些知識庫的存取權限。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫：
      | postId | title    | need_access |
      | 100    | PHP 入門 | yes         |
      | 200    | JS 進階  | yes         |
      | 300    | 免費教學 | no          |
    And 系統中有以下商品：
      | productId | name       | type                 | bound_docs_data |
      | 50        | PHP 課程   | simple               | []              |
      | 60        | 訂閱方案   | subscription         | []              |
      | 70        | 可變商品   | variable             | []              |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 僅可綁定 need_access=yes 的知識庫
    Example: 知識庫下拉選單僅顯示需要授權的知識庫
      Given 用戶 userId=1 已登入
      When 進入「知識庫權限綁定」頁面
      Then 可選擇的知識庫僅包含 postId=100 和 postId=200
      And 不包含 postId=300（need_access=no）

  Rule: 前置（參數）- follow_subscription 限制只能選訂閱商品
    Example: limit_type 為 follow_subscription 時非訂閱商品不可選
      Given 用戶 userId=1 已登入
      And 設定 limit_type 為 "follow_subscription"
      When 查看商品列表
      Then 商品 productId=50（simple）的 checkbox 被停用
      And 商品 productId=60（subscription）的 checkbox 可選

  Rule: 前置（參數）- 可變商品母體不可選
    Example: 可變商品本身不可選，變體可選
      Given 用戶 userId=1 已登入
      When 查看商品列表
      Then 商品 productId=70（variable）的 checkbox 被隱藏
      And 其變體的 checkbox 可選

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 成功綁定知識庫到商品
    Example: 將知識庫綁定到簡單商品
      Given 用戶 userId=1 已登入
      And 選取商品 productId=50
      When 綁定知識庫 postId=100，limit_type="fixed"，days=365
      Then 商品 productId=50 的 bound_docs_data 包含：
        | post_id | limit_type | days |
        | 100     | fixed      | 365  |

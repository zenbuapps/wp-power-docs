@ignore
Feature: 查詢用戶列表（GetUsers）

  管理員在「學員管理」頁面查詢用戶列表，
  支援搜尋、分頁，及依已授權知識庫篩選。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
      | 10     | 小明   | ming@example.com   | subscriber    |
      | 11     | 小華   | hua@example.com    | subscriber    |
      | 12     | 小美   | mei@example.com    | subscriber    |
    And 系統中有以下知識庫授權（ph_access_itemmeta）：
      | post_id | user_id | meta_key    | meta_value |
      | 100     | 10      | expire_date | 2027-01-01 |
      | 100     | 11      | expire_date | 2027-01-01 |
      | 200     | 10      | expire_date | 2027-01-01 |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 分頁預設值
    Example: 未帶分頁參數時使用預設值
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users（無參數）
      Then 回應使用 posts_per_page=20, paged=1

  Rule: 前置（參數）- 搜尋支援多欄位
    Example: 以 email 搜尋用戶
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users?s=ming@example.com
      Then 回應包含 userId=10

    Example: 以 ID 搜尋用戶
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users?s=11
      Then 回應包含 userId=11

  # ========== 後置（回應）==========

  Rule: 後置（回應）- 依已授權知識庫篩選（交集）
    Example: 篩選同時擁有多個知識庫授權的用戶
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users?granted_docs[]=100&granted_docs[]=200
      Then 回應僅包含 userId=10（同時有 100 和 200 授權）
      And 不包含 userId=11（僅有 100 授權）

    Example: 篩選擁有單一知識庫授權的用戶
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users?granted_docs[]=100
      Then 回應包含 userId=10 和 userId=11

  Rule: 後置（回應）- 回應 Header 包含分頁資訊
    Example: 回應包含完整分頁 Header
      Given 用戶 userId=1 已登入
      When 送出 GET /wp-json/power-docs/v1/users?posts_per_page=2&paged=1
      Then 回應 Header 包含：
        | Header             | Value |
        | X-WP-Total         | 3     |
        | X-WP-TotalPages    | 2     |
        | X-WP-CurrentPage   | 1     |
        | X-WP-PageSize      | 2     |

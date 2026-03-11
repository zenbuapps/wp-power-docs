@ignore
Feature: 排序章節（SortPosts）

  管理員在知識庫編輯頁面的「文章管理」分頁，
  透過拖拉調整章節的順序和父子階層關係。

  Background:
    Given 系統中有以下用戶：
      | userId | name   | email              | role          |
      | 1      | 管理員 | admin@example.com  | administrator |
    And 系統中有以下知識庫結構：
      | postId | title    | post_parent | menu_order |
      | 100    | PHP 入門 | 0           | 0          |
      | 101    | 第一章   | 100         | 0          |
      | 102    | 第二章   | 100         | 1          |
      | 103    | 1-1 節   | 101         | 0          |

  # ========== 前置（參數）==========

  Rule: 前置（參數）- 樹狀結構未變動時不送出請求
    Example: 排序前後結構相同
      Given 用戶 userId=1 已登入
      When 拖拉結束但 from_tree 和 to_tree 結構相同
      Then 不送出 POST /wp-json/powerhouse/v1/posts/sort 請求

  Rule: 前置（參數）- 不可超過最大深度 2 層
    Example: 嘗試將章節拖到第 3 層
      Given 用戶 userId=1 已登入
      When 嘗試將 postId=102 拖拉成為 postId=103 的子章節（深度超過 2）
      Then 顯示錯誤訊息 "超過最大深度，無法執行"
      And 排序操作被取消

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 排序成功更新 menu_order 和 post_parent
    Example: 將第二章移到第一章前面
      Given 用戶 userId=1 已登入
      When 將 postId=102 拖拉到 postId=101 前面
      And 送出 POST /wp-json/powerhouse/v1/posts/sort：
        | from_tree                              | to_tree                              |
        | [{id:101,order:0},{id:102,order:1}]    | [{id:102,order:0},{id:101,order:1}]  |
      Then 排序儲存成功
      And postId=102 的 menu_order 變為 0
      And postId=101 的 menu_order 變為 1

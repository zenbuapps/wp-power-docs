@ignore
Feature: 執行相容性遷移（RunCompatibilityMigration）

  外掛升級完成後，自動為缺少 editor meta 的章節設定正確的編輯器類型。

  Background:
    Given 系統中有以下知識庫章節：
      | postId | title  | _elementor_data | editor |
      | 101    | 第一章 | [有資料]        | (無)   |
      | 102    | 第二章 | (無)            | (無)   |
      | 103    | 第三章 | [有資料]        | elementor    |
      | 104    | 第四章 | (無)            | power-editor |

  # ========== 後置（狀態）==========

  Rule: 後置（狀態）- 有 _elementor_data 但無 editor 的章節設為 elementor
    Example: 遷移舊版 elementor 章節
      When WordPress 觸發 upgrader_process_complete
      Then 文章 postId=101 的 editor meta 設為 "elementor"

  Rule: 後置（狀態）- 無 _elementor_data 且無 editor 的章節設為 power-editor
    Example: 遷移舊版非 elementor 章節
      When WordPress 觸發 upgrader_process_complete
      Then 文章 postId=102 的 editor meta 設為 "power-editor"

  Rule: 後置（狀態）- 已有 editor 的章節不受影響
    Example: 已設定 editor 的章節保持不變
      When WordPress 觸發 upgrader_process_complete
      Then 文章 postId=103 的 editor meta 仍為 "elementor"
      And 文章 postId=104 的 editor meta 仍為 "power-editor"

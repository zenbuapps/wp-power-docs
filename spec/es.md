# Event Storming: Power Docs

> WordPress 知識變現套件 — 知識庫管理、章節排序、購買授權、存取控制、Elementor 整合
> **版本:** 1.2.7 | **文件日期:** 2026-03-11

---

## Actors

- **Admin** [人]: WordPress 管理員，管理知識庫內容、章節結構、商品綁定、用戶授權
- **Subscriber** [人]: 已登入的一般用戶/訂閱者，瀏覽知識庫內容
- **Guest** [人]: 未登入的訪客，瀏覽公開知識庫
- **WooCommerce** [外部系統]: 訂單完成時觸發知識庫授權
- **WordPress** [系統]: 外掛升級時觸發相容性遷移；儲存文章時清除快取

---

## Aggregates

### Doc（知識庫文章）

> CPT `pd_doc`，階層結構：知識庫(root) -> 章節(depth=1) -> 單元(depth=2)

| 屬性 | 說明 |
|------|------|
| `ID` | 文章 ID（WP post ID） |
| `post_title` | 標題 |
| `post_content` | 內容（HTML） |
| `post_status` | 狀態：publish / draft / pending / private |
| `post_parent` | 父文章 ID（0 表示根知識庫） |
| `menu_order` | 排序順序 |
| `post_type` | 固定為 `pd_doc` |
| meta: `editor` | 編輯器類型：`power-editor` / `elementor` / `''`（空字串=知識庫首頁預設版型） |
| meta: `bg_images` | 背景圖片 attachment ID（僅根知識庫） |
| meta: `need_access` | 是否需要購買授權：`yes` / `no`（僅根知識庫） |
| meta: `pd_keywords` | 搜尋關鍵字陣列 `[{id, title}]`（僅根知識庫） |
| meta: `pd_keywords_label` | 關鍵字標籤文字（僅根知識庫） |
| meta: `unauthorized_redirect_url` | 未授權跳轉網址（僅根知識庫） |

### Product（WooCommerce 商品）

> WooCommerce 商品，透過 `bound_docs_data` meta 綁定知識庫授權

| 屬性 | 說明 |
|------|------|
| `ID` | 商品 ID |
| meta: `bound_docs_data` | 綁定的知識庫授權資料陣列 `BoundItemData[]`，每筆包含 post_id、limit_type、expire 等 |

### UserAccess（用戶存取權限）

> 存於 Powerhouse 的 `wp_ph_access_itemmeta` 資料表

| 屬性 | 說明 |
|------|------|
| `post_id` | 知識庫根文章 ID |
| `user_id` | 用戶 ID |
| `meta_key` | 固定為 `expire_date` |
| `meta_value` | 到期日期字串（空字串或日期格式，空 = 永久） |

### User（用戶）

> WordPress 用戶，擴充 `granted_docs` 虛擬欄位

| 屬性 | 說明 |
|------|------|
| `ID` | 用戶 ID |
| `user_login` | 帳號 |
| `user_email` | 電子信箱 |
| `display_name` | 顯示名稱 |
| virtual: `granted_docs` | 已授權的知識庫清單（由 `ph_access_itemmeta` 即時計算） |

---

## Commands

### GrantAccessOnOrderCompleted（訂單完成自動授權）

- **Actor**: WooCommerce（系統觸發）
- **Aggregate**: UserAccess, Product
- **Predecessors**: 顧客完成 WooCommerce 訂單
- **參數**: `order_id` (int)
- **Description**:
  - **What**: 訂單完成時，檢查訂單商品的 `bound_docs_data`，自動將知識庫授權寫入 `ph_access_itemmeta`
  - **Why**: 實現購買知識庫的自動授權流程
  - **When**: `woocommerce_order_status_completed` hook 觸發

#### Rules

- 前置（狀態）: 訂單必須存在且為 `WC_Order` 實例
- 前置（狀態）: 訂單必須有登入用戶（`customer_id` 不為 0）
- 前置（狀態）: 訂單商品必須有 `bound_docs_data` meta
- 後置（狀態）: 對每個綁定的知識庫，呼叫 `BoundItemData::grant_user()` 寫入 `ph_access_itemmeta`

### CreateDoc（建立知識庫/章節）

- **Actor**: Admin
- **Aggregate**: Doc
- **Predecessors**: 無
- **參數**: `post_type` (pd_doc), `post_title`, `post_parent?`, `status?`
- **Description**:
  - **What**: 透過 Powerhouse REST API `POST /wp-json/powerhouse/v1/posts` 建立知識庫或章節
  - **Why**: 管理員需要建立新的知識庫或在知識庫下新增章節/單元
  - **When**: 管理員在後台點擊「新增知識庫」或「新增文章」

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 前置（參數）: `post_type` 必須為 `pd_doc`
- 後置（狀態）: 若為根知識庫（無 `post_parent`），自動建立預設 meta：`pd_keywords_label`、`pd_keywords`、`unauthorized_redirect_url`
- 後置（狀態）: 若為子章節（有 `post_parent`），`editor` 預設為 `power-editor`

### UpdateDoc（更新知識庫/章節）

- **Actor**: Admin
- **Aggregate**: Doc
- **Predecessors**: 知識庫/章節已存在
- **參數**: `id`, `post_title?`, `post_content?`, `status?`, `need_access?`, `bg_images?` (file/delete), `pd_keywords?`, `pd_keywords_label?`, `unauthorized_redirect_url?`, `editor?`
- **Description**:
  - **What**: 透過 Powerhouse REST API `PATCH /wp-json/powerhouse/v1/posts/{id}` 更新知識庫或章節
  - **Why**: 管理員需要編輯知識庫內容、設定存取權限、上傳背景圖等
  - **When**: 管理員在編輯頁面點擊儲存

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 前置（狀態）: 文章必須存在
- 後置（狀態）: 若 `bg_images` 為檔案，上傳並儲存 attachment ID
- 後置（狀態）: 若 `bg_images` 為 `delete`，清除背景圖 meta
- 後置（狀態）: 儲存時清除對應知識庫的 transient 快取
- 後置（狀態）: 若 `editor` 為 `power-editor`，清除所有 `_elementor_*` meta

### DeleteDoc（刪除知識庫/章節）

- **Actor**: Admin
- **Aggregate**: Doc
- **Predecessors**: 知識庫/章節已存在
- **參數**: `ids` (int[])
- **Description**:
  - **What**: 透過 Powerhouse REST API `DELETE /wp-json/powerhouse/v1/posts/{id}` 或批量刪除
  - **Why**: 管理員需要移除不需要的知識庫或章節
  - **When**: 管理員在後台點擊刪除或批量刪除

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 前置（狀態）: 文章必須存在
- 後置（狀態）: 文章被刪除，相關快取被清除

### SortPosts（排序章節）

- **Actor**: Admin
- **Aggregate**: Doc
- **Predecessors**: 知識庫已存在且有子章節
- **參數**: `from_tree` (排序前樹狀結構), `to_tree` (排序後樹狀結構)
- **Description**:
  - **What**: 透過 Powerhouse REST API `POST /wp-json/powerhouse/v1/posts/sort` 更新章節排序和父子關係
  - **Why**: 管理員需要透過拖拉調整章節順序和階層
  - **When**: 管理員在文章管理分頁拖拉排序後自動觸發

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 前置（參數）: 最大深度為 2 層（知識庫 -> 章節 -> 單元）
- 前置（參數）: `from_tree` 和 `to_tree` 不得相同（無變化不送出）
- 後置（狀態）: 更新所有受影響章節的 `menu_order` 和 `post_parent`

### CopyDoc（複製知識庫）

- **Actor**: Admin
- **Aggregate**: Doc
- **Predecessors**: 知識庫已存在
- **參數**: `post_id` (int)
- **Description**:
  - **What**: 透過 Powerhouse REST API 複製知識庫及其所有子章節
  - **Why**: 管理員需要基於現有知識庫快速建立新的知識庫
  - **When**: 管理員在列表頁面點擊複製

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 後置（狀態）: 複製的子文章保持 `post_type` 為 `pd_doc`

### BindDocsToProduct（綁定知識庫到商品）

- **Actor**: Admin
- **Aggregate**: Product
- **Predecessors**: 商品和知識庫都已存在
- **參數**: `product_ids` (int[]), `item_ids` (知識庫 ID[]), `meta_key` = `bound_docs_data`, limit 設定
- **Description**:
  - **What**: 透過 Powerhouse REST API 將知識庫授權資料寫入商品的 `bound_docs_data` meta
  - **Why**: 需要設定購買哪個商品能獲得哪些知識庫的存取權限
  - **When**: 管理員在「知識庫權限綁定」頁面操作

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 前置（參數）: 知識庫必須已設定 `need_access` = `yes`
- 前置（參數）: 若 `limit_type` 為 `follow_subscription`，商品必須為訂閱類型
- 後置（狀態）: 商品的 `bound_docs_data` meta 更新

### UnbindDocsFromProduct（解除商品知識庫綁定）

- **Actor**: Admin
- **Aggregate**: Product
- **Predecessors**: 商品已綁定知識庫
- **參數**: `product_ids` (int[]), `item_ids` (知識庫 ID[]), `meta_key` = `bound_docs_data`
- **Description**:
  - **What**: 移除商品 `bound_docs_data` 中指定的知識庫綁定
  - **Why**: 需要解除商品與知識庫的授權關聯
  - **When**: 管理員在「知識庫權限綁定」頁面操作解除綁定

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 後置（狀態）: 商品的 `bound_docs_data` 中移除指定的知識庫綁定

### UpdateBoundDocs（更新商品知識庫綁定設定）

- **Actor**: Admin
- **Aggregate**: Product
- **Predecessors**: 商品已綁定知識庫
- **參數**: `product_ids` (int[]), `item_ids` (知識庫 ID[]), `meta_key` = `bound_docs_data`, limit 設定
- **Description**:
  - **What**: 更新商品已綁定知識庫的期限設定（limit_type、expire 等）
  - **Why**: 需要調整已綁定知識庫的期限或類型設定
  - **When**: 管理員在「知識庫權限綁定」頁面修改已綁定項目的設定

#### Rules

- 前置（狀態）: 用戶必須為管理員
- 後置（狀態）: 商品的 `bound_docs_data` 中指定項目的 limit 設定更新

### RunCompatibilityMigration（執行相容性遷移）

- **Actor**: WordPress（系統觸發）
- **Aggregate**: Doc
- **Predecessors**: 外掛升級完成
- **參數**: 無
- **Description**:
  - **What**: 為所有缺少 `editor` meta 的章節自動設定：有 `_elementor_data` 的設為 `elementor`，其餘設為 `power-editor`
  - **Why**: 確保舊版本升級後，所有章節都有正確的編輯器類型設定
  - **When**: `upgrader_process_complete` hook 觸發

#### Rules

- 後置（狀態）: 所有有 `_elementor_data` 但無 `editor` 的章節設為 `editor=elementor`
- 後置（狀態）: 所有無 `_elementor_data` 且無 `editor` 的章節設為 `editor=power-editor`

---

## Read Models

### GetUsers（查詢用戶列表）

- **Actor**: Admin
- **Aggregates**: User, UserAccess
- **回傳欄位**: `ID`, `user_login`, `user_email`, `display_name`, `user_nicename`, `granted_docs`（選填 meta_keys）
- **Description**:
  - **What**: 查詢用戶列表，支援搜尋、分頁，及依已授權知識庫篩選
  - **Why**: 管理員需要查看和管理知識庫授權的用戶
  - **When**: 管理員進入「學員管理」頁面

#### Rules

- 前置（參數）: `posts_per_page` 預設 20，`paged` 預設 1
- 前置（參數）: `s` 搜尋欄位：ID、user_login、user_email、user_nicename、display_name
- 前置（參數）: `granted_docs[]` 若提供，篩選同時擁有所有指定知識庫授權的用戶（HAVING COUNT = 指定數量）
- 後置（回應）: 回應 Header 包含分頁資訊：`X-WP-Total`、`X-WP-TotalPages`、`X-WP-CurrentPage`、`X-WP-PageSize`

### GetDocs（查詢知識庫列表）

- **Actor**: Admin
- **Aggregates**: Doc
- **回傳欄位**: `id`, `name`, `slug`, `status`, `date_created`, `date_modified`, `menu_order`, `permalink`, `parent_id`, `images`, `bg_images`, `editor`, `need_access`, `pd_keywords`, `pd_keywords_label`, `unauthorized_redirect_url`
- **Description**:
  - **What**: 透過 Powerhouse REST API `GET /wp-json/powerhouse/v1/posts` 查詢知識庫列表（`post_type=pd_doc`，根層級）
  - **Why**: 管理員需要瀏覽和管理所有知識庫
  - **When**: 管理員進入知識庫列表頁面

#### Rules

- 前置（參數）: `post_type` = `pd_doc`，僅查詢根層級（無 parent）
- 後置（回應）: 包含分頁和排序資訊

### GetDocDetail（查詢知識庫詳情）

- **Actor**: Admin
- **Aggregates**: Doc
- **回傳欄位**: 同 GetDocs 加上 `description`（post_content）, `short_description`, `children`
- **Description**:
  - **What**: 透過 Powerhouse REST API `GET /wp-json/powerhouse/v1/posts/{id}` 查詢知識庫詳情含所有 meta
  - **Why**: 管理員編輯知識庫時需要完整資料
  - **When**: 管理員進入知識庫編輯頁面

#### Rules

- 前置（參數）: `meta_keys` = `['need_access', 'bg_images', 'pd_keywords', 'pd_keywords_label', 'unauthorized_redirect_url']`
- 後置（回應）: `bg_images` 回傳包含圖片完整資訊的陣列（非僅 ID）
- 後置（回應）: 子章節的 `editor` 若為空，預設為 `power-editor`

### GetDocChildren（查詢知識庫子章節）

- **Actor**: Admin
- **Aggregates**: Doc
- **回傳欄位**: 章節/單元的完整資料，含巢狀 children
- **Description**:
  - **What**: 透過 Powerhouse REST API `GET /wp-json/powerhouse/v1/posts?post_type=pd_doc&parent_id={id}` 查詢子章節
  - **Why**: 管理員在「文章管理」分頁需要載入章節樹狀結構
  - **When**: 管理員切換到知識庫編輯的「文章管理」分頁

#### Rules

- 前置（參數）: `post_type` = `pd_doc`, `parent_id` 為知識庫根 ID
- 後置（回應）: 回傳巢狀樹狀結構，最多 2 層深度

### GetProducts（查詢商品列表）

- **Actor**: Admin
- **Aggregates**: Product
- **回傳欄位**: 商品基本資料 + `bound_docs_data`
- **Description**:
  - **What**: 透過 Powerhouse REST API `GET /wp-json/powerhouse/v1/products` 查詢商品列表含知識庫綁定資料
  - **Why**: 管理員在「知識庫權限綁定」頁面需要查看商品及其已綁定的知識庫
  - **When**: 管理員進入「知識庫權限綁定」頁面

#### Rules

- 前置（參數）: `meta_keys` 包含 `bound_docs_data`
- 後置（回應）: 每個商品包含 `bound_docs_data` 陣列（已綁定的知識庫授權資料）

### ViewDocFrontend（前台檢視知識庫）

- **Actor**: Subscriber / Guest / Admin
- **Aggregates**: Doc, UserAccess
- **回傳欄位**: HTML 頁面（landing / detail / search）
- **Description**:
  - **What**: 訪問 `/pd_doc/{slug}`，系統根據文章層級和查詢參數渲染對應版型
  - **Why**: 用戶需要瀏覽知識庫內容
  - **When**: 用戶訪問知識庫前台頁面

#### Rules

- 前置（狀態）: 若 `need_access=yes`，用戶必須已授權且未過期；管理員例外
- 前置（狀態）: 若文章狀態為 `draft`，僅管理員可存取
- 後置（回應）: 根知識庫 -> `doc-landing`（首頁版型）；子章節 -> `doc-detail`（詳情版型含側邊欄 + TOC）；`?search=` -> `doc-search`（搜尋結果版型）
- 後置（回應）: 未授權用戶跳轉至 `unauthorized_redirect_url`

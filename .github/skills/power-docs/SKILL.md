---
name: power-docs
description: "Power Docs — WordPress 知識庫變現外掛開發指引。React 18 + Refine.dev 管理介面、巢狀 CPT 知識庫、WooCommerce 存取控制、Elementor Widget、TOC 生成、Bunny CDN 整合。使用 /power-docs 觸發。"
origin: project-analyze
---

# power-docs — 開發指引

> WordPress Plugin，建立可變現的知識庫系統（`pd_doc` CPT）。後台 Refine.dev SPA 管理文章/章節/用戶授權，前台三種模板頁面（首頁/詳情/搜尋）。

## When to Activate

當使用者在此專案中：
- 修改 `inc/classes/**/*.php`（PHP 後端）
- 修改 `js/src/**/*.tsx`（React/Refine.dev 前端）
- 修改 `inc/templates/**/*.php`（前台模板）
- 詢問 Refine.dev、TOC 生成、存取控制、Elementor Widget 相關問題

## 架構概覽

**技術棧：**
- **語言**: PHP 8.0+（`declare(strict_types=1)`）
- **框架**: WordPress 5.7+、WooCommerce 7.6+、Powerhouse 3.3.11+、Elementor（可選）
- **關鍵依賴**: `kucrut/vite-for-wp ^0.8`、`j7-dev/wp-plugin-trait ^0.2`
- **前端**: React 18 + TypeScript + Refine.dev + Ant Design 5 + TailwindCSS + DaisyUI（pc- prefix）
- **狀態管理**: TanStack Query + Jotai（Atom 狀態）
- **建置**: Vite 6.3
- **代碼風格**: PHPCS（WordPress-Core）、PHPStan、ESLint + Prettier

## 目錄結構

```
power-docs/
├── plugin.php                                      # 主入口（PluginTrait + SingletonTrait）
├── inc/
│   ├── classes/
│   │   ├── Bootstrap.php                           # 初始化所有子域模組
│   │   ├── Admin/Entry.php                         # 全屏管理頁面渲染器
│   │   ├── Domains/
│   │   │   ├── Doc/
│   │   │   │   ├── CPT.php                         # CPT 'pd_doc' 註冊（無限層級）
│   │   │   │   ├── Api.php                         # REST API 擴展（granted_docs meta）
│   │   │   │   ├── Access.php                      # 存取控制邏輯
│   │   │   │   └── Templates.php                   # 模板覆寫（landing/detail/search）
│   │   │   ├── Product/Api.php                     # 商品 Meta 暴露（知識庫綁定）
│   │   │   ├── User/Api.php                        # 自訂用戶端點
│   │   │   └── Elementor/
│   │   │       ├── Loader.php                      # Widget 註冊器
│   │   │       ├── Card.php                        # 知識庫卡片 Widget
│   │   │       └── Search.php                      # 搜尋框 Widget
│   │   ├── Helper/TOCGenerator.php                 # DOMDocument 目錄生成器
│   │   └── Compatibility/Compatibility.php         # 版本升級遷移
│   └── templates/
│       ├── single-pd_doc.php                       # 模板分派器
│       └── pages/
│           ├── doc-landing/index.php               # 知識庫首頁（hero + 卡片列表）
│           ├── doc-detail/
│           │   ├── index.php                       # 三欄詳情頁（側邊欄+內容+TOC）
│           │   ├── sider/index.php                 # 遞迴導航樹（HTML 渲染）
│           │   ├── sider/mobile-menu.php           # 手機版浮層導航
│           │   └── toc/index.php                   # 目錄表（TOCGenerator）
│           └── doc-search/index.php                # 搜尋結果頁
├── js/src/
│   ├── main.tsx                                    # React 掛載入口
│   ├── App1.tsx                                    # Refine 應用 Shell（6 個 dataProvider）
│   ├── resources/index.tsx                         # Refine 資源定義（docs/users/doc-access 等）
│   ├── api/resources/                              # CRUD API 函數
│   ├── components/
│   │   ├── post/PostAction/                        # 文章操作元件
│   │   └── user/UserTable/                         # 可重用用戶表格
│   ├── hooks/
│   │   ├── useEnv.tsx                              # 環境變數訪問（simpleDecrypt）
│   │   ├── useDocSelect.tsx                        # 知識庫選擇（Jotai atom）
│   │   └── useProductsOptions.tsx                  # WooCommerce 商品選項
│   ├── pages/admin/
│   │   ├── Docs/List/                              # 知識庫清單（Ant Design Table）
│   │   ├── Docs/Edit/                              # 三標籤編輯（說明/章節/設定）
│   │   │   └── tabs/SortablePosts/                # 可拖動章節樹
│   │   ├── Users/                                  # 用戶授權管理
│   │   ├── DocAccess/                              # 商品↔知識庫綁定
│   │   ├── WpMediaLibraryPage/                     # WordPress 媒體庫
│   │   └── BunnyMediaLibraryPage/                  # Bunny 流媒體庫
│   ├── types/
│   │   ├── wpRestApi/                              # WordPress REST API 型別
│   │   ├── wcRestApi/                              # WooCommerce REST API 型別
│   │   └── wcStoreApi/                             # WC Store API 型別
│   └── utils/
│       ├── env.tsx                                 # 環境變數解密
│       └── constants.ts                            # 狀態選項、商品類型常數
```

## Refine.dev DataProvider 配置

```typescript
// App1.tsx - 6 個 dataProvider
const dataProviders = {
    default:      dataProvider('/v2/powerhouse'),    // Powerhouse REST API
    'wp-rest':    dataProvider('/wp/v2'),            // WordPress Core REST API
    'wc-rest':    dataProvider('/wc/v3'),            // WooCommerce REST API
    'wc-store':   dataProvider('/wc/store/v1'),      // WC Store API
    'bunny-stream': bunnyProvider,                   // Bunny CDN
    'power-docs': dataProvider(`/${KEBAB}`),         // Power Docs 專屬 API
}
```

## Refine.dev 資源定義

```typescript
export const resources: ResourceProps[] = [
    { name: 'docs',         list: '/docs',              edit: '/docs/edit/:id' },
    { name: 'users',        list: '/users' },
    { name: 'doc-access',   list: '/doc-access' },
    { name: 'media-library', list: '/media-library' },
    { name: 'bunny-media-library', list: '/bunny-media-library' },
]
```

## TOC 生成器

```php
// TOCGenerator - 解析 HTML 內容自動生成目錄
$generator = new TOCGenerator($post_content);
$toc_html = $generator->get_toc();  // 返回目錄 HTML
// 同時給 H2-H4 標題添加 id 屬性供錨點跳轉
```

## 命名慣例

| 類型 | 慣例 | 範例 |
|------|------|------|
| PHP Namespace | PascalCase | `J7\PowerDocs\Domains\Doc` |
| PHP 類別 | PascalCase（final） | `final class CPT` |
| CPT | pd_ 前綴 | `pd_doc` |
| Refine 資源 | kebab-case | `doc-access`、`media-library` |
| CSS 前綴 | pc- | `pc-hero`、`pc-card` |
| Hook | `useXxx` | `useDocSelect`、`useProductsOptions` |
| Text Domain | snake_case | `power_docs` |

## 開發規範

1. 前台模板使用 PHP 純渲染（不用 JavaScript 框架），TOC 透過 PHP DOMDocument 生成
2. 存取控制統一由 `Access.php` 處理，不在模板內直接判斷
3. React 元件使用 Functional Components + Hooks，禁用 Class Components
4. `useQuery`/`useMutation` 統一使用 Refine.dev 的 `useList`/`useOne`/`useUpdate` 等 Hook
5. 環境變數透過 `useEnv()` hook 訪問，不直接操作 `window` 物件

## 常用指令

```bash
composer install           # 安裝 PHP 依賴
pnpm install               # 安裝 Node 依賴
pnpm dev                   # Vite 開發伺服器
pnpm build                 # 建置到 js/dist/
pnpm i18n                  # 生成翻譯模板 power_docs.pot
vendor/bin/phpcs           # PHP 代碼風格檢查
vendor/bin/phpstan analyse # PHPStan 靜態分析
pnpm release               # 發佈 patch 版本
```

## 相關 SKILL

- `wordpress-master` — WordPress Plugin 開發通用指引
- `react-master` — React 前端開發指引
- `refine` — Refine.dev 框架使用指引
- `wp-rest-api` — REST API 設計規範

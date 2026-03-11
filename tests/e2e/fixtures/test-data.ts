/**
 * E2E 測試資料常數
 *
 * Power Docs 知識庫系統測試共用的資料定義。
 */

/* ── WordPress Admin ─────────────────────────── */

export const WP_ADMIN = {
	username: process.env.WP_ADMIN_USERNAME || 'admin',
	password: process.env.WP_ADMIN_PASSWORD || 'password',
}

/* ── 基礎 URL / 端點 ────────────────────────── */

export const BASE_URL = 'http://localhost:8893'

export const API = {
	/** Powerhouse 通用 posts API（知識庫 CRUD） */
	posts: 'powerhouse/v1/posts',
	/** Powerhouse 通用 posts 排序 */
	postsSort: 'powerhouse/v1/posts/sort',
	/** Powerhouse 通用 products API */
	products: 'powerhouse/v1/products',
	/** Power Docs 自訂 users API */
	users: 'power-docs/v1/users',
	/** WooCommerce REST API v3 */
	wcOrders: 'wc/v3/orders',
	wcProducts: 'wc/v3/products',
	/** WordPress 核心 users API */
	wpUsers: 'wp/v2/users',
} as const

/* ── 常用 URL 路徑 ───────────────────────────── */

export const URLS = {
	adminDashboard: '/wp-admin/',
	adminDocs: '/wp-admin/admin.php?page=power-docs',
	login: '/wp-login.php',
} as const

/* ── 測試知識庫資料 ──────────────────────────── */

export const TEST_DOC = {
	name: 'E2E 測試知識庫',
	slug: 'e2e-test-doc',
	description: '<p>這是 E2E 測試用的知識庫。</p>',
	shortDescription: 'E2E 測試知識庫簡介',
	needAccess: 'yes' as const,
	unauthorizedRedirectUrl: '/404',
}

export const TEST_FREE_DOC = {
	name: 'E2E 免費知識庫',
	slug: 'e2e-free-doc',
	description: '<p>免費的 E2E 測試知識庫</p>',
	needAccess: 'no' as const,
}

/* ── 測試章節資料 ────────────────────────────── */

export const TEST_CHAPTERS = {
	chapter1: {
		title: 'E2E 第一章 基礎介紹',
		editor: 'power-editor',
	},
	chapter2: {
		title: 'E2E 第二章 進階內容',
		editor: 'power-editor',
	},
	subChapter1: {
		title: 'E2E 1-1 基礎觀念',
		editor: 'power-editor',
	},
	subChapter2: {
		title: 'E2E 1-2 實作練習',
		editor: 'power-editor',
	},
}

/* ── 測試用戶帳號 ────────────────────────────── */

export const TEST_SUBSCRIBER = {
	username: 'e2e_pd_subscriber',
	password: 'e2e_pd_subscriber_pass',
	email: 'e2e_pd_subscriber@test.local',
	firstName: '測試',
	lastName: '訂閱者',
	displayName: 'PD 測試訂閱者',
}

export const TEST_SUBSCRIBER_NO_ACCESS = {
	username: 'e2e_pd_no_access',
	password: 'e2e_pd_no_access_pass',
	email: 'e2e_pd_no_access@test.local',
	firstName: '無權限',
	lastName: '用戶',
	displayName: 'PD 無權限用戶',
}

/* ── 測試商品資料 ────────────────────────────── */

export const TEST_PRODUCT = {
	name: 'E2E 知識庫商品',
	regularPrice: '1000',
	type: 'simple',
}

export const TEST_SUBSCRIPTION_PRODUCT = {
	name: 'E2E 訂閱商品',
	regularPrice: '500',
	type: 'subscription',
}

/* ── 邊界測試字串 ────────────────────────────── */

export const EDGE_STRINGS = {
	xssScript: '<script>alert("XSS")</script>知識庫',
	xssImgOnerror: '<img onerror="alert(1)" src=x>Doc',
	sqlInjection: "' OR 1=1 --",
	htmlEntities: '&lt;b&gt;Bold&lt;/b&gt;',
	unicode: '你好世界 🌍 こんにちは 🎓 Héllo',
	emoji: '📚🎯🚀💡✅❌🔥 知識庫',
	longString: 'A'.repeat(500),
	emptyString: '',
	specialChars: '!@#$%^&*()_+-=[]{}|;:,.<>?/~`',
	rtlText: 'مرحبا بالعالم',
	newlines: '第一行\n第二行\n第三行',
	tabs: '欄位1\t欄位2\t欄位3',
	nullByte: 'test\x00null',
}

/* ── WooCommerce 結帳資料 ────────────────────── */

export const CHECKOUT_DATA = {
	firstName: '測試',
	lastName: '買家',
	address: '台北市中正區忠孝東路一段1號',
	city: '台北市',
	postcode: '100',
	phone: '0912345678',
	email: 'e2e_pd_buyer@test.local',
}

/* ── Timeout 常數 ────────────────────────────── */

export const TIMEOUTS = {
	apiResponse: 10_000,
	pageNavigation: 15_000,
	fileUpload: 30_000,
}

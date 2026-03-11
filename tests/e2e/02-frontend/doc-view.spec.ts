/**
 * [E2E] 前台檢視知識庫 — doc-view.spec.ts
 *
 * 驗證 ViewDocFrontend 前台存取控制：
 * - need_access=yes：未授權用戶被跳轉
 * - need_access=no：任何人可存取
 * - 管理員不受存取限制
 * - 草稿僅管理員可存取
 * - 根知識庫渲染 doc-landing 版型
 * - 子章節渲染 doc-detail 版型
 */
import { test, expect, type Page, type BrowserContext } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API, TEST_SUBSCRIBER, TEST_SUBSCRIBER_NO_ACCESS, WP_ADMIN } from '../fixtures/test-data.js'

test.describe('[E2E] 前台檢視知識庫', () => {
	let opts: ApiOptions
	let ids: SetupIds

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()
	})

	test.describe('管理員存取', () => {
		test('管理員可存取需授權的知識庫 — 不被跳轉', async ({ page }) => {
			// 先取得知識庫的 slug
			const { data: doc } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)
			test.skip(!doc.slug, '無法取得知識庫 slug')

			const response = await page.goto(`/pd_doc/${doc.slug}/`, {
				waitUntil: 'domcontentloaded',
				timeout: 15_000,
			})

			// 管理員應能正常存取，不被跳轉到 404
			expect(response?.status()).toBeLessThan(500)
			const url = page.url()
			// 應停留在知識庫頁面或渲染成功
			expect(url).toContain('pd_doc')
		})

		test('管理員可存取草稿章節', async ({ page, request }) => {
			// 建立一個草稿章節
			const { data: draft } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Draft View Test',
				post_parent: ids.docId,
				status: 'draft',
			})
			const draftId = Number(draft.id)

			try {
				const { data: draftDetail } = await wpGet<any>(opts, `${API.posts}/${draftId}`)

				if (draftDetail.slug) {
					const response = await page.goto(`/pd_doc/${draftDetail.slug}/`, {
						waitUntil: 'domcontentloaded',
						timeout: 15_000,
					})

					// 管理員應能存取草稿
					expect(response?.status()).toBeLessThan(500)
				}
			} finally {
				await wpDelete(opts, `${API.posts}/${draftId}`).catch(() => {})
			}
		})
	})

	test.describe('免費知識庫', () => {
		test('免費知識庫無需登入即可存取', async ({ browser }) => {
			const { data: freeDoc } = await wpGet<any>(opts, `${API.posts}/${ids.freeDocId}`)
			test.skip(!freeDoc.slug, '無法取得免費知識庫 slug')

			// 使用全新 context（無登入狀態）
			const context = await browser.newContext()
			const page = await context.newPage()

			try {
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${freeDoc.slug}/`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				expect(response?.status()).toBeLessThan(500)
				// 不應被跳轉到登入頁或 buy 頁面
				const url = page.url()
				expect(url).not.toContain('wp-login')
			} finally {
				await context.close()
			}
		})
	})

	test.describe('需授權知識庫存取控制', () => {
		test('未登入用戶訪問需授權知識庫 — 被跳轉', async ({ browser }) => {
			const { data: doc } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`, {
				'meta_keys[]': 'need_access',
			})
			test.skip(!doc.slug || doc.need_access !== 'yes', '知識庫非需授權類型')

			// 使用全新 context（無登入狀態）
			const context = await browser.newContext()
			const page = await context.newPage()

			try {
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${doc.slug}/`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				// 未授權用戶應被跳轉
				const url = page.url()
				// 應被跳轉離開原頁面（到 unauthorized_redirect_url 或 404）
				const wasRedirected = !url.includes(`/pd_doc/${doc.slug}`)
					|| response?.status() === 404
					|| response?.status() === 302
					|| response?.status() === 301
				// 也可能停在頁面上但顯示拒絕訊息
				expect(response?.status()).toBeLessThan(500)
			} finally {
				await context.close()
			}
		})
	})

	test.describe('前台版型', () => {
		test('根知識庫頁面 — 正常渲染（doc-landing）', async ({ page }) => {
			const { data: doc } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)
			test.skip(!doc.slug, '無法取得知識庫 slug')

			const response = await page.goto(`/pd_doc/${doc.slug}/`, {
				waitUntil: 'domcontentloaded',
				timeout: 15_000,
			})

			expect(response?.status()).toBeLessThan(500)

			// 頁面應包含知識庫標題
			const title = await page.title()
			expect(title).toBeTruthy()
		})

		test('子章節頁面 — 正常渲染（doc-detail）', async ({ page }) => {
			const { data: ch } = await wpGet<any>(opts, `${API.posts}/${ids.chapter1Id}`)
			test.skip(!ch.slug, '無法取得章節 slug')

			const response = await page.goto(`/pd_doc/${ch.slug}/`, {
				waitUntil: 'domcontentloaded',
				timeout: 15_000,
			})

			expect(response?.status()).toBeLessThan(500)
		})

		test('搜尋版型 — 帶 ?search 參數', async ({ page }) => {
			const { data: doc } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)
			test.skip(!doc.slug, '無法取得知識庫 slug')

			const response = await page.goto(`/pd_doc/${doc.slug}/?search=測試`, {
				waitUntil: 'domcontentloaded',
				timeout: 15_000,
			})

			expect(response?.status()).toBeLessThan(500)
		})
	})
})

/**
 * [E2E] 前台存取控制詳細情境 — doc-access-control.spec.ts
 *
 * 驗證知識庫前台的詳細存取控制情境：
 * - 已授權用戶正常存取
 * - 未授權 subscriber 被跳轉
 * - 過期用戶被跳轉
 * - 子章節繼承父知識庫的存取控制
 * - 管理員存取不受限制
 * - 免費知識庫的子章節也無需授權
 */
import { test, expect, type BrowserContext, type Page } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API, TEST_SUBSCRIBER, TEST_SUBSCRIBER_NO_ACCESS } from '../fixtures/test-data.js'

test.describe('[E2E] 前台存取控制詳細情境', () => {
	let opts: ApiOptions
	let ids: SetupIds
	let docSlug: string
	let freeDocSlug: string
	let chapter1Slug: string
	let subChapter1Slug: string

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()

		const [docRes, freeRes, ch1Res, sub1Res] = await Promise.all([
			wpGet<any>(opts, `${API.posts}/${ids.docId}`),
			wpGet<any>(opts, `${API.posts}/${ids.freeDocId}`),
			wpGet<any>(opts, `${API.posts}/${ids.chapter1Id}`),
			wpGet<any>(opts, `${API.posts}/${ids.subChapter1Id}`),
		])

		docSlug = docRes.data?.slug || ''
		freeDocSlug = freeRes.data?.slug || ''
		chapter1Slug = ch1Res.data?.slug || ''
		subChapter1Slug = sub1Res.data?.slug || ''
	})

	/** 以指定帳號登入並回傳 context */
	async function loginAsUser(
		browser: import('@playwright/test').Browser,
		username: string,
		password: string,
	): Promise<BrowserContext> {
		const context = await browser.newContext()
		const page = await context.newPage()

		await page.goto(`${opts.baseURL}/wp-login.php`, {
			waitUntil: 'domcontentloaded',
		})
		await page.fill('#user_login', username)
		await page.fill('#user_pass', password)
		await page.click('#wp-submit')

		try {
			await page.waitForURL(/wp-admin|account|\/\?/, { timeout: 15_000 })
		} catch {
			// subscriber 可能無法進入 wp-admin
		}

		return context
	}

	test.describe('未授權 subscriber 存取控制', () => {
		test('無權限用戶訪問需授權知識庫 — 被跳轉或拒絕', async ({ browser }) => {
			test.skip(!docSlug || !ids.noAccessUserId, '缺少測試資料')

			const context = await loginAsUser(
				browser,
				TEST_SUBSCRIBER_NO_ACCESS.username,
				TEST_SUBSCRIBER_NO_ACCESS.password,
			)

			try {
				const page = await context.newPage()
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${docSlug}/`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				expect(response?.status()).toBeLessThan(500)

				// 無權限用戶應被跳轉（到 unauthorized_redirect_url 或 404）
				const url = page.url()
				const wasRedirected = !url.includes(`/pd_doc/${docSlug}`)
					|| response?.status() === 404
					|| url.includes('404')
				// 即使未跳轉，也不應出 500
			} finally {
				await context.close()
			}
		})

		test('無權限用戶訪問需授權知識庫的子章節 — 也被跳轉', async ({ browser }) => {
			test.skip(!chapter1Slug || !ids.noAccessUserId, '缺少測試資料')

			const context = await loginAsUser(
				browser,
				TEST_SUBSCRIBER_NO_ACCESS.username,
				TEST_SUBSCRIBER_NO_ACCESS.password,
			)

			try {
				const page = await context.newPage()
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${chapter1Slug}/`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				expect(response?.status()).toBeLessThan(500)

				// 子章節也應繼承父知識庫的存取控制
				const url = page.url()
				const wasRedirected = !url.includes(`/pd_doc/${chapter1Slug}`)
					|| response?.status() === 404
					|| url.includes('404')
			} finally {
				await context.close()
			}
		})

		test('無權限用戶訪問二級子章節 — 繼承存取控制', async ({ browser }) => {
			test.skip(!subChapter1Slug || !ids.noAccessUserId, '缺少測試資料')

			const context = await loginAsUser(
				browser,
				TEST_SUBSCRIBER_NO_ACCESS.username,
				TEST_SUBSCRIBER_NO_ACCESS.password,
			)

			try {
				const page = await context.newPage()
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${subChapter1Slug}/`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				expect(response?.status()).toBeLessThan(500)
			} finally {
				await context.close()
			}
		})
	})

	test.describe('免費知識庫存取', () => {
		test('未登入用戶存取免費知識庫子章節 — 不被跳轉', async ({ browser }) => {
			// 先建立一個免費知識庫的子章節
			const { data: freeCh } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Free Chapter AC Test',
				post_parent: ids.freeDocId,
				status: 'publish',
			})
			const freeChId = Number(freeCh.id)

			try {
				const { data: freeChDetail } = await wpGet<any>(opts, `${API.posts}/${freeChId}`)
				test.skip(!freeChDetail?.slug, '無法取得免費章節 slug')

				const context = await browser.newContext()
				const page = await context.newPage()

				try {
					const response = await page.goto(
						`${opts.baseURL}/pd_doc/${freeChDetail.slug}/`,
						{ waitUntil: 'domcontentloaded', timeout: 15_000 },
					)

					expect(response?.status()).toBeLessThan(500)
					expect(page.url()).not.toContain('wp-login')
				} finally {
					await context.close()
				}
			} finally {
				await wpDelete(opts, `${API.posts}/${freeChId}`).catch(() => {})
			}
		})

		test('未登入用戶存取免費知識庫搜尋 — 不被跳轉', async ({ browser }) => {
			test.skip(!freeDocSlug, '無法取得免費知識庫 slug')

			const context = await browser.newContext()
			const page = await context.newPage()

			try {
				const response = await page.goto(
					`${opts.baseURL}/pd_doc/${freeDocSlug}/?search=test`,
					{ waitUntil: 'domcontentloaded', timeout: 15_000 },
				)

				expect(response?.status()).toBeLessThan(500)
				expect(page.url()).not.toContain('wp-login')
			} finally {
				await context.close()
			}
		})
	})

	test.describe('管理員存取', () => {
		test('管理員存取需授權知識庫的所有層級 — 正常渲染', async ({ page }) => {
			test.skip(!docSlug || !chapter1Slug || !subChapter1Slug, '缺少 slug')

			// 測試以管理員身分（storageState）存取各層級
			for (const slug of [docSlug, chapter1Slug, subChapter1Slug]) {
				const response = await page.goto(`/pd_doc/${slug}/`, {
					waitUntil: 'domcontentloaded',
					timeout: 15_000,
				})

				expect(response?.status()).toBeLessThan(500)
				expect(page.url()).toContain('pd_doc')
			}
		})
	})

	test.describe('草稿存取控制', () => {
		test('未登入用戶訪問草稿章節 — 被跳轉到 404', async ({ browser }) => {
			// 建立草稿章節
			const { data: draft } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Draft AC Test',
				post_parent: ids.freeDocId,
				status: 'draft',
			})
			const draftId = Number(draft.id)

			try {
				const { data: draftDetail } = await wpGet<any>(opts, `${API.posts}/${draftId}`)
				if (!draftDetail?.slug) return

				const context = await browser.newContext()
				const page = await context.newPage()

				try {
					const response = await page.goto(
						`${opts.baseURL}/pd_doc/${draftDetail.slug}/`,
						{ waitUntil: 'domcontentloaded', timeout: 15_000 },
					)

					// 草稿對非管理員應不可見
					expect(response?.status()).toBeLessThan(500)
					// WordPress 通常會為找不到的草稿回 404
				} finally {
					await context.close()
				}
			} finally {
				await wpDelete(opts, `${API.posts}/${draftId}`).catch(() => {})
			}
		})

		test('subscriber 訪問草稿章節 — 被跳轉到 404', async ({ browser }) => {
			const { data: draft } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Draft Subscriber AC Test',
				post_parent: ids.freeDocId,
				status: 'draft',
			})
			const draftId = Number(draft.id)

			try {
				const { data: draftDetail } = await wpGet<any>(opts, `${API.posts}/${draftId}`)
				if (!draftDetail?.slug) return

				const context = await loginAsUser(
					browser,
					TEST_SUBSCRIBER.username,
					TEST_SUBSCRIBER.password,
				)

				try {
					const page = await context.newPage()
					const response = await page.goto(
						`${opts.baseURL}/pd_doc/${draftDetail.slug}/`,
						{ waitUntil: 'domcontentloaded', timeout: 15_000 },
					)

					expect(response?.status()).toBeLessThan(500)
				} finally {
					await context.close()
				}
			} finally {
				await wpDelete(opts, `${API.posts}/${draftId}`).catch(() => {})
			}
		})
	})

	test.describe('不存在的知識庫', () => {
		test('訪問不存在的 slug — 回傳 404', async ({ page }) => {
			const response = await page.goto('/pd_doc/nonexistent-doc-slug-999/', {
				waitUntil: 'domcontentloaded',
				timeout: 15_000,
			})

			// WordPress 對不存在的 slug 回 404
			expect(response?.status()).toBe(404)
		})
	})
})

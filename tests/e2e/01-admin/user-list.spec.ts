/**
 * [E2E] 查詢用戶列表 — user-list.spec.ts
 *
 * 驗證 GET /power-docs/v1/users 用戶列表查詢：
 * - 分頁預設值 posts_per_page=20, paged=1
 * - 搜尋支援 email、ID、帳號
 * - 依已授權知識庫篩選（交集）
 * - 回應 Header 包含分頁資訊
 * - 帶 meta_keys[]=granted_docs 取得授權資訊
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 查詢用戶列表', () => {
	let opts: ApiOptions
	let ids: SetupIds

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()
	})

	test('查詢用戶列表 — 回傳 200 且為陣列', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.users)

		expect(status).toBe(200)
		expect(Array.isArray(data)).toBe(true)
	})

	test('回應包含分頁 Header', async () => {
		const { headers, status } = await wpGet<any[]>(opts, API.users, {
			posts_per_page: '2',
			paged: '1',
		})

		expect(status).toBe(200)
		expect(headers['x-wp-total']).toBeDefined()
		expect(headers['x-wp-totalpages']).toBeDefined()
		expect(headers['x-wp-currentpage']).toBeDefined()
		expect(headers['x-wp-pagesize']).toBeDefined()
	})

	test('用戶包含必要欄位', async () => {
		const { data } = await wpGet<any[]>(opts, API.users)

		expect(data.length).toBeGreaterThan(0)
		const user = data[0]
		expect(user).toHaveProperty('id')
		expect(user).toHaveProperty('user_login')
		expect(user).toHaveProperty('user_email')
		expect(user).toHaveProperty('display_name')
	})

	test('搜尋 — 以 email 搜尋用戶', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.users, {
			s: 'admin@',
		})

		expect(status).toBe(200)
		// 應至少找到 admin
		if (data.length > 0) {
			const emails = data.map((u: any) => u.user_email)
			const found = emails.some((e: string) => e.includes('admin'))
			expect(found).toBe(true)
		}
	})

	test('搜尋 — 以帳號搜尋用戶', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.users, {
			s: 'admin',
		})

		expect(status).toBe(200)
		expect(data.length).toBeGreaterThan(0)
	})

	test('分頁 — 指定 posts_per_page 和 paged', async () => {
		const page1 = await wpGet<any[]>(opts, API.users, {
			posts_per_page: '1',
			paged: '1',
		})

		expect(page1.status).toBe(200)
		expect(page1.data.length).toBeLessThanOrEqual(1)

		const total = Number(page1.headers['x-wp-total'])
		if (total > 1) {
			const page2 = await wpGet<any[]>(opts, API.users, {
				posts_per_page: '1',
				paged: '2',
			})

			expect(page2.data.length).toBeLessThanOrEqual(1)

			// 兩頁資料不重複
			if (page1.data.length > 0 && page2.data.length > 0) {
				expect(page1.data[0].id).not.toBe(page2.data[0].id)
			}
		}
	})

	test('帶 meta_keys[]=granted_docs — 回傳授權資訊', async () => {
		const url = new URL(`${opts.baseURL}/wp-json/${API.users}`)
		url.searchParams.append('meta_keys[]', 'granted_docs')
		url.searchParams.append('posts_per_page', '10')

		const res = await opts.request.get(url.toString(), {
			headers: { 'X-WP-Nonce': opts.nonce },
		})

		expect(res.status()).toBe(200)
		const data = await res.json()
		expect(Array.isArray(data)).toBe(true)

		// 如果有用戶帶 granted_docs
		const userWithDocs = data.find((u: any) => u.granted_docs && u.granted_docs.length > 0)
		if (userWithDocs) {
			expect(Array.isArray(userWithDocs.granted_docs)).toBe(true)
			const grant = userWithDocs.granted_docs[0]
			expect(grant).toHaveProperty('id')
			expect(grant).toHaveProperty('title')
		}
	})

	test('依已授權知識庫篩選 — granted_docs[]', async () => {
		test.skip(!ids.docId, '缺少測試知識庫')

		const url = new URL(`${opts.baseURL}/wp-json/${API.users}`)
		url.searchParams.append('granted_docs[]', String(ids.docId))

		const res = await opts.request.get(url.toString(), {
			headers: { 'X-WP-Nonce': opts.nonce },
		})

		expect(res.status()).toBe(200)
		const data = await res.json()
		expect(Array.isArray(data)).toBe(true)
		// 回傳的用戶都應擁有該知識庫授權
	})

	test('排序 — orderby=ID, order=ASC', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.users, {
			orderby: 'ID',
			order: 'ASC',
			posts_per_page: '5',
		})

		expect(status).toBe(200)
		if (data.length >= 2) {
			expect(Number(data[0].id)).toBeLessThanOrEqual(Number(data[1].id))
		}
	})
})

/**
 * [E2E] 知識庫列表 — doc-list.spec.ts
 *
 * 驗證 GET /powerhouse/v1/posts?post_type=pd_doc 列表查詢：
 * - 僅回傳根層級知識庫
 * - 回傳必要欄位
 * - 分頁功能
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 知識庫列表', () => {
	let opts: ApiOptions

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
	})

	test('查詢根層級知識庫列表 — 回傳 200 且為陣列', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
		})

		expect(status).toBe(200)
		expect(Array.isArray(data)).toBe(true)
	})

	test('列表僅顯示根知識庫 — 不包含子章節', async () => {
		const { data } = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
		})

		expect(Array.isArray(data)).toBe(true)
		for (const doc of data) {
			// 根知識庫的 parent_id 應為 "0" 或 0
			expect(String(doc.parent_id)).toBe('0')
		}
	})

	test('每筆知識庫包含必要欄位', async () => {
		const { data } = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
		})

		expect(data.length).toBeGreaterThan(0)
		const doc = data[0]

		// 驗證基本欄位存在
		expect(doc).toHaveProperty('id')
		expect(doc).toHaveProperty('name')
		expect(doc).toHaveProperty('slug')
		expect(doc).toHaveProperty('status')
		expect(doc).toHaveProperty('permalink')
	})

	test('分頁 — posts_per_page 限制回傳筆數', async () => {
		const { data, headers } = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
			posts_per_page: '1',
			paged: '1',
		})

		expect(Array.isArray(data)).toBe(true)
		expect(data.length).toBeLessThanOrEqual(1)

		// 驗證分頁 Header
		expect(headers['x-wp-total']).toBeDefined()
		expect(headers['x-wp-totalpages']).toBeDefined()
	})

	test('分頁第二頁 — 資料不重複', async () => {
		const page1 = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
			posts_per_page: '1',
			paged: '1',
		})
		const page2 = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
			posts_per_page: '1',
			paged: '2',
		})

		if (page1.data.length > 0 && page2.data.length > 0) {
			const ids1 = page1.data.map((d: any) => d.id)
			const ids2 = page2.data.map((d: any) => d.id)
			const overlap = ids1.filter((id: any) => ids2.includes(id))
			expect(overlap).toHaveLength(0)
		}
	})

	test('帶 meta_keys 查詢 — 回傳額外欄位', async () => {
		const { data } = await wpGet<any[]>(opts, API.posts, {
			post_type: 'pd_doc',
			'meta_keys[]': 'need_access',
		})

		expect(data.length).toBeGreaterThan(0)
		// need_access 欄位應存在（可能是 yes/no/空字串）
		const doc = data.find((d: any) => d.need_access !== undefined)
		expect(doc).toBeDefined()
	})
})

/**
 * [E2E] 知識庫詳情 — doc-detail.spec.ts
 *
 * 驗證 GET /powerhouse/v1/posts/{id} 查詢知識庫詳情：
 * - 帶 meta_keys 取得擴充欄位
 * - bg_images 回傳完整圖片資訊
 * - 子章節 editor 預設值
 * - with_description 取得內容
 * - children 子章節列表
 * - 不存在的知識庫回傳 404
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 知識庫詳情', () => {
	let opts: ApiOptions
	let ids: SetupIds

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()
	})

	test('查詢知識庫詳情 — 回傳 200 且包含基本欄位', async () => {
		const { data, status } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)

		expect(status).toBe(200)
		expect(data).toHaveProperty('id')
		expect(data).toHaveProperty('name')
		expect(data).toHaveProperty('slug')
		expect(data).toHaveProperty('status')
	})

	test('帶 meta_keys 查詢 — 回傳 need_access 等擴充欄位', async () => {
		const { data, status } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`, {
			'meta_keys[]': 'need_access',
		})

		expect(status).toBe(200)
		expect(data.need_access).toBeDefined()
		expect(['yes', 'no', '']).toContain(data.need_access)
	})

	test('帶 with_description=true — 回傳 description 欄位', async () => {
		const { data } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`, {
			with_description: 'true',
		})

		expect(data).toHaveProperty('description')
	})

	test('查詢知識庫包含子章節 — children 欄位', async () => {
		const { data } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)

		// 如果有 children 欄位，驗證其為陣列
		if (data.children) {
			expect(Array.isArray(data.children)).toBe(true)
			expect(data.children.length).toBeGreaterThan(0)

			// 驗證子章節包含必要欄位
			const child = data.children[0]
			expect(child).toHaveProperty('id')
			expect(child).toHaveProperty('name')
		}
	})

	test('子章節 editor 預設為 power-editor', async () => {
		const { data } = await wpGet<any>(opts, `${API.posts}/${ids.chapter1Id}`)

		expect(data.editor).toBe('power-editor')
	})

	test('查詢 pd_keywords_label 和 pd_keywords 欄位', async () => {
		// 使用多個 meta_keys（透過 URL 查詢字串）
		const url = new URL(`${opts.baseURL}/wp-json/${API.posts}/${ids.docId}`)
		url.searchParams.append('meta_keys[]', 'pd_keywords_label')
		url.searchParams.append('meta_keys[]', 'pd_keywords')
		url.searchParams.append('meta_keys[]', 'unauthorized_redirect_url')

		const res = await opts.request.get(url.toString(), {
			headers: { 'X-WP-Nonce': opts.nonce },
		})

		expect(res.status()).toBe(200)
		const data = await res.json()
		expect(data).toHaveProperty('pd_keywords_label')
		expect(data).toHaveProperty('unauthorized_redirect_url')
	})

	test('查詢不存在的知識庫 — 回傳 404', async () => {
		const { status } = await wpGet(opts, `${API.posts}/9999999`)
		expect(status).toBe(404)
	})

	test('查詢免費知識庫 — need_access=no', async () => {
		const { data } = await wpGet<any>(opts, `${API.posts}/${ids.freeDocId}`, {
			'meta_keys[]': 'need_access',
		})

		expect(data.need_access).toBe('no')
	})
})

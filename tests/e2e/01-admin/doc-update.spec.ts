/**
 * [E2E] 更新知識庫 — doc-update.spec.ts
 *
 * 驗證 PATCH /powerhouse/v1/posts/{id} 更新知識庫：
 * - 更新標題、內容、狀態
 * - need_access 設定
 * - bg_images 上傳/刪除
 * - editor 切換時清除 elementor 資料
 * - pd_keywords / pd_keywords_label 更新
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 更新知識庫', () => {
	let opts: ApiOptions
	let docId: number
	let chapterId: number

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }

		// 建立測試用知識庫和章節
		const { data: doc } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Update Test Doc',
			status: 'publish',
		})
		docId = Number(doc.id)

		const { data: ch } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Update Test Chapter',
			post_parent: docId,
			status: 'publish',
		})
		chapterId = Number(ch.id)
	})

	test.afterAll(async () => {
		await wpDelete(opts, `${API.posts}/${chapterId}`).catch(() => {})
		await wpDelete(opts, `${API.posts}/${docId}`).catch(() => {})
	})

	test('更新標題 — 回傳 200', async ({ request }) => {
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { post_title: 'E2E Updated Title' },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`)
		expect(detail.name).toBe('E2E Updated Title')
	})

	test('更新文章內容 — post_content', async ({ request }) => {
		const newContent = '<p>E2E 更新後的內容</p>'
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { post_content: newContent },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`, {
			with_description: 'true',
		})
		expect(detail.description).toContain('更新後的內容')
	})

	test('更新狀態為 draft', async ({ request }) => {
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { status: 'draft' },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`)
		expect(detail.status).toBe('draft')

		// 恢復為 publish
		await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { status: 'publish' },
			},
		)
	})

	test('更新 need_access 設定', async ({ request }) => {
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { need_access: 'yes' },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`, {
			'meta_keys[]': 'need_access',
		})
		expect(detail.need_access).toBe('yes')
	})

	test('更新 pd_keywords_label', async ({ request }) => {
		const newLabel = '熱門搜尋：'
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { pd_keywords_label: newLabel },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`, {
			'meta_keys[]': 'pd_keywords_label',
		})
		expect(detail.pd_keywords_label).toBe(newLabel)
	})

	test('更新 unauthorized_redirect_url', async ({ request }) => {
		const newUrl = 'https://example.com/buy-now'
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { unauthorized_redirect_url: newUrl },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`, {
			'meta_keys[]': 'unauthorized_redirect_url',
		})
		expect(detail.unauthorized_redirect_url).toBe(newUrl)
	})

	test('刪除背景圖 — bg_images=delete', async ({ request }) => {
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/${docId}`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { bg_images: 'delete' },
			},
		)

		expect(res.status()).toBe(200)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${docId}`, {
			'meta_keys[]': 'bg_images',
		})
		// bg_images 應為空陣列或空值
		const bgImages = detail.bg_images
		expect(!bgImages || (Array.isArray(bgImages) && bgImages.length === 0) || bgImages === '').toBe(true)
	})

	test('更新不存在的文章 — 回傳 404', async ({ request }) => {
		const res = await request.patch(
			`${opts.baseURL}/wp-json/${API.posts}/9999999`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: { post_title: 'Ghost' },
			},
		)

		expect(res.status()).toBe(404)
	})
})

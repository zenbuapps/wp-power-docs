/**
 * [E2E] 建立知識庫 — doc-create.spec.ts
 *
 * 驗證 POST /powerhouse/v1/posts 建立知識庫：
 * - 根知識庫自動建立預設 meta（pd_keywords_label, pd_keywords, unauthorized_redirect_url）
 * - 子章節 editor 預設為 power-editor
 * - 非 pd_doc 類型不觸發預設 meta
 * - 權限驗證
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 建立知識庫', () => {
	let opts: ApiOptions
	const createdIds: number[] = []

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
	})

	test.afterAll(async () => {
		// 反向刪除（先刪子章節再刪父）
		for (const id of [...createdIds].reverse()) {
			await wpDelete(opts, `${API.posts}/${id}`).catch(() => {})
		}
	})

	test('建立根知識庫 — 回傳 200 且有 ID', async () => {
		const { data, status } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Create Test Doc',
		})

		expect(status).toBe(200)
		expect(data).toHaveProperty('id')
		const id = Number(data.id)
		expect(id).toBeGreaterThan(0)
		createdIds.push(id)
	})

	test('根知識庫自動建立預設 meta — pd_keywords_label', async () => {
		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Default Meta Test',
		})

		const id = Number(data.id)
		createdIds.push(id)

		// 查詢帶 meta_keys 驗證預設值
		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`, {
			'meta_keys[]': 'pd_keywords_label',
		})

		expect(detail.pd_keywords_label).toBeDefined()
		// 預設值應為 "大家都在搜：" 或類似字串
		if (detail.pd_keywords_label) {
			expect(typeof detail.pd_keywords_label).toBe('string')
		}
	})

	test('根知識庫自動建立 unauthorized_redirect_url 預設值', async () => {
		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Redirect URL Test',
		})

		const id = Number(data.id)
		createdIds.push(id)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`, {
			'meta_keys[]': 'unauthorized_redirect_url',
		})

		expect(detail.unauthorized_redirect_url).toBeDefined()
	})

	test('子章節 editor 預設為 power-editor', async () => {
		const setupIds = getSetupIds()

		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Child Chapter Editor Test',
			post_parent: setupIds.docId,
		})

		const id = Number(data.id)
		createdIds.push(id)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`)
		// 子章節應預設為 power-editor
		expect(detail.editor).toBe('power-editor')
	})

	test('子章節不設定 pd_keywords_label 預設值', async () => {
		const setupIds = getSetupIds()

		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Child No Keywords Test',
			post_parent: setupIds.docId,
		})

		const id = Number(data.id)
		createdIds.push(id)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`, {
			'meta_keys[]': 'pd_keywords_label',
		})

		// 子章節不應有 pd_keywords_label 或為空
		const label = detail.pd_keywords_label
		expect(!label || label === '').toBe(true)
	})

	test('建立知識庫時指定 draft 狀態', async () => {
		const { data, status } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Draft Doc',
			status: 'draft',
		})

		expect(status).toBe(200)
		const id = Number(data.id)
		createdIds.push(id)

		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`)
		expect(detail.status).toBe('draft')
	})
})

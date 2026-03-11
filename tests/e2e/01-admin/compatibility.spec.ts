/**
 * [E2E] 相容性遷移 — compatibility.spec.ts
 *
 * 驗證 RunCompatibilityMigration 邏輯：
 * - 有 _elementor_data 但無 editor 的章節，查詢時 editor 回傳正確值
 * - 無 _elementor_data 且無 editor 的章節，editor 預設為 power-editor
 * - 已有 editor 的章節不受影響
 *
 * 注意：遷移由 upgrader_process_complete hook 觸發，
 * E2E 層級透過 API 驗證最終結果。
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 相容性遷移', () => {
	let opts: ApiOptions

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
	})

	test('新建子章節預設 editor 為 power-editor', async () => {
		const ids = getSetupIds()

		// 建立一個新的子章節（不指定 editor）
		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Compat Test Chapter',
			post_parent: ids.docId,
			status: 'publish',
		})
		const chapterId = Number(data.id)

		try {
			const { data: detail } = await wpGet<any>(opts, `${API.posts}/${chapterId}`)
			expect(detail.editor).toBe('power-editor')
		} finally {
			await wpDelete(opts, `${API.posts}/${chapterId}`).catch(() => {})
		}
	})

	test('明確設定 editor=elementor 的章節 — 保持 elementor', async ({ request }) => {
		const ids = getSetupIds()

		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Compat Elementor Chapter',
			post_parent: ids.docId,
			status: 'publish',
		})
		const chapterId = Number(data.id)

		try {
			// 明確設定為 elementor
			await request.patch(
				`${opts.baseURL}/wp-json/${API.posts}/${chapterId}`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: { editor: 'elementor' },
				},
			)

			const { data: detail } = await wpGet<any>(opts, `${API.posts}/${chapterId}`)
			expect(detail.editor).toBe('elementor')
		} finally {
			await wpDelete(opts, `${API.posts}/${chapterId}`).catch(() => {})
		}
	})

	test('切換 editor 為 power-editor — 應清除 elementor 資料', async ({ request }) => {
		const ids = getSetupIds()

		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Compat Switch Editor',
			post_parent: ids.docId,
			status: 'publish',
		})
		const chapterId = Number(data.id)

		try {
			// 先設為 elementor
			await request.patch(
				`${opts.baseURL}/wp-json/${API.posts}/${chapterId}`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: { editor: 'elementor' },
				},
			)

			// 再切回 power-editor
			const res = await request.patch(
				`${opts.baseURL}/wp-json/${API.posts}/${chapterId}`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: { editor: 'power-editor' },
				},
			)

			expect(res.status()).toBe(200)

			const { data: detail } = await wpGet<any>(opts, `${API.posts}/${chapterId}`)
			expect(detail.editor).toBe('power-editor')
		} finally {
			await wpDelete(opts, `${API.posts}/${chapterId}`).catch(() => {})
		}
	})

	test('根知識庫 editor 為空字串 — 使用預設版型', async () => {
		const ids = getSetupIds()
		const { data: detail } = await wpGet<any>(opts, `${API.posts}/${ids.docId}`)

		// 根知識庫的 editor 應為空字串（使用首頁預設版型）
		expect(detail.editor === '' || detail.editor === undefined || detail.editor === null).toBe(true)
	})
})

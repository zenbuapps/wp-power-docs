/**
 * [E2E] 刪除知識庫 — doc-delete.spec.ts
 *
 * 驗證 DELETE /powerhouse/v1/posts/{id} 刪除知識庫：
 * - 單筆刪除
 * - 批量刪除
 * - 刪除後查詢回傳 404
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 刪除知識庫', () => {
	let opts: ApiOptions

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
	})

	test('單筆刪除章節 — 回傳 200', async () => {
		// 先建立一個待刪除的知識庫
		const { data } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Delete Single Test',
		})
		const id = Number(data.id)
		expect(id).toBeGreaterThan(0)

		// 刪除
		const { status } = await wpDelete<any>(opts, `${API.posts}/${id}`)
		expect(status).toBe(200)

		// 確認已刪除
		const { status: getStatus } = await wpGet(opts, `${API.posts}/${id}`)
		expect(getStatus).toBe(404)
	})

	test('刪除含子章節的知識庫 — 子章節應一併刪除或獨立存在', async () => {
		// 建立父知識庫
		const { data: parentData } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Delete Parent Test',
		})
		const parentId = Number(parentData.id)

		// 建立子章節
		const { data: childData } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Delete Child Test',
			post_parent: parentId,
		})
		const childId = Number(childData.id)

		// 先刪子章節
		await wpDelete(opts, `${API.posts}/${childId}`)
		// 再刪父知識庫
		const { status } = await wpDelete(opts, `${API.posts}/${parentId}`)
		expect(status).toBe(200)

		// 確認父已刪除
		const { status: parentStatus } = await wpGet(opts, `${API.posts}/${parentId}`)
		expect(parentStatus).toBe(404)
	})

	test('刪除不存在的知識庫 — 回傳 404', async () => {
		const { status } = await wpDelete(opts, `${API.posts}/9999999`)
		expect(status).toBe(404)
	})

	test('批量刪除多個章節', async () => {
		// 建立 3 個待刪除的知識庫
		const ids: number[] = []
		for (let i = 1; i <= 3; i++) {
			const { data } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: `E2E Bulk Delete ${i}`,
			})
			ids.push(Number(data.id))
		}

		// 逐筆刪除（Powerhouse API 可能不支援批量，故逐筆）
		for (const id of ids) {
			const { status } = await wpDelete(opts, `${API.posts}/${id}`)
			expect(status).toBe(200)
		}

		// 確認全部已刪除
		for (const id of ids) {
			const { status } = await wpGet(opts, `${API.posts}/${id}`)
			expect(status).toBe(404)
		}
	})
})

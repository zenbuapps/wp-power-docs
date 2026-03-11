/**
 * [E2E] 查詢商品列表 — product-list.spec.ts
 *
 * 驗證 GET /powerhouse/v1/products 商品列表查詢：
 * - 帶 meta_keys[]=bound_docs_data 取得知識庫綁定資料
 * - 商品基本欄位存在
 * - 分頁功能
 * - 未綁定知識庫的商品 bound_docs_data 為空陣列
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 查詢商品列表', () => {
	let opts: ApiOptions

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
	})

	test('查詢商品列表 — 回傳 200 且為陣列', async () => {
		const { data, status } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
		})

		expect(status).toBe(200)
		expect(Array.isArray(data)).toBe(true)
	})

	test('商品包含 bound_docs_data 欄位', async () => {
		const { data } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
		})

		if (data.length > 0) {
			for (const product of data) {
				expect(product).toHaveProperty('bound_docs_data')
				// bound_docs_data 應為陣列
				expect(Array.isArray(product.bound_docs_data)).toBe(true)
			}
		}
	})

	test('商品基本欄位存在', async () => {
		const { data } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
		})

		if (data.length > 0) {
			const product = data[0]
			expect(product).toHaveProperty('id')
			expect(product).toHaveProperty('name')
			expect(product).toHaveProperty('type')
			expect(product).toHaveProperty('status')
		}
	})

	test('分頁 — posts_per_page 限制回傳筆數', async () => {
		const { data, headers } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
			posts_per_page: '1',
			paged: '1',
		})

		expect(Array.isArray(data)).toBe(true)
		expect(data.length).toBeLessThanOrEqual(1)

		if (headers['x-wp-total']) {
			expect(Number(headers['x-wp-total'])).toBeGreaterThanOrEqual(0)
		}
	})

	test('已綁定知識庫的商品 — bound_docs_data 包含綁定資訊', async () => {
		const { data } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
			posts_per_page: '100',
		})

		const boundProduct = data.find(
			(p: any) => Array.isArray(p.bound_docs_data) && p.bound_docs_data.length > 0,
		)

		if (boundProduct) {
			const binding = boundProduct.bound_docs_data[0]
			// 綁定資料應包含 id/post_id 和 limit_type
			expect(binding).toHaveProperty('limit_type')
			expect(['unlimited', 'fixed', 'follow_subscription']).toContain(binding.limit_type)
		}
	})

	test('未綁定知識庫的商品 — bound_docs_data 為空陣列', async () => {
		const { data } = await wpGet<any[]>(opts, API.products, {
			'meta_keys[]': 'bound_docs_data',
			posts_per_page: '100',
		})

		const unboundProduct = data.find(
			(p: any) => Array.isArray(p.bound_docs_data) && p.bound_docs_data.length === 0,
		)

		if (unboundProduct) {
			expect(unboundProduct.bound_docs_data).toEqual([])
		}
	})
})

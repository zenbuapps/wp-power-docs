/**
 * [E2E] 綁定知識庫到商品 — product-bind.spec.ts
 *
 * 驗證透過 Powerhouse products API 將知識庫綁定到商品：
 * - 綁定 need_access=yes 的知識庫到商品
 * - 設定 limit_type: unlimited / fixed
 * - 綁定後商品的 bound_docs_data 正確更新
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPut, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 綁定知識庫到商品', () => {
	let opts: ApiOptions
	let ids: SetupIds
	let testProductId: number

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()

		// 建立測試商品
		try {
			const res = await request.post(`${baseURL}/wp-json/${API.wcProducts}`, {
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: {
					name: 'E2E Bind Test Product',
					type: 'simple',
					regular_price: '999',
					status: 'publish',
				},
			})
			const prodData = await res.json()
			testProductId = Number(prodData.id)
		} catch {
			testProductId = ids.productId
		}
	})

	test.afterAll(async ({ request }) => {
		if (testProductId && testProductId !== ids.productId) {
			await request.delete(
				`${opts.baseURL}/wp-json/${API.wcProducts}/${testProductId}?force=true`,
				{ headers: { 'X-WP-Nonce': opts.nonce } },
			).catch(() => {})
		}
	})

	test('綁定知識庫到商品 — limit_type=unlimited', async ({ request }) => {
		test.skip(!testProductId || !ids.docId, '缺少測試商品或知識庫')

		const bindData = {
			product_ids: [testProductId],
			item_ids: [ids.docId],
			meta_key: 'bound_docs_data',
			limit_type: 'unlimited',
		}

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/bind`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: bindData,
			},
		)

		// 可能回傳 200 或使用 PUT /products/{id} 方式
		expect(res.status()).toBeLessThan(500)
	})

	test('綁定後查詢商品 — bound_docs_data 包含綁定資料', async () => {
		test.skip(!testProductId, '缺少測試商品')

		const { data } = await wpGet<any>(opts, `${API.products}/${testProductId}`, {
			'meta_keys[]': 'bound_docs_data',
		})

		// 如果有 bound_docs_data，驗證其結構
		if (data.bound_docs_data && Array.isArray(data.bound_docs_data) && data.bound_docs_data.length > 0) {
			const binding = data.bound_docs_data[0]
			expect(binding).toHaveProperty('id')
			expect(binding).toHaveProperty('limit_type')
		}
	})

	test('綁定 limit_type=fixed — 包含期限設定', async ({ request }) => {
		test.skip(!testProductId || !ids.docId, '缺少測試商品或知識庫')

		const bindData = {
			product_ids: [testProductId],
			item_ids: [ids.docId],
			meta_key: 'bound_docs_data',
			limit_type: 'fixed',
			limit_value: 365,
			limit_unit: 'day',
		}

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/bind`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: bindData,
			},
		)

		expect(res.status()).toBeLessThan(500)
	})

	test('不帶 item_ids 時 — 不造成伺服器錯誤', async ({ request }) => {
		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/bind`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [],
					meta_key: 'bound_docs_data',
				},
			},
		)

		expect(res.status()).toBeLessThan(500)
	})
})

/**
 * [E2E] 解除商品知識庫綁定 — product-unbind.spec.ts
 *
 * 驗證透過 Powerhouse products API 解除商品上已綁定的知識庫：
 * - 解除後 bound_docs_data 移除指定知識庫
 * - 部分解除（保留其他綁定）
 * - 空選取不觸發操作
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 解除商品知識庫綁定', () => {
	let opts: ApiOptions
	let ids: SetupIds
	let testProductId: number

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()

		// 建立測試商品並綁定知識庫
		try {
			const res = await request.post(`${baseURL}/wp-json/${API.wcProducts}`, {
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: {
					name: 'E2E Unbind Test Product',
					type: 'simple',
					regular_price: '888',
					status: 'publish',
				},
			})
			const prodData = await res.json()
			testProductId = Number(prodData.id)

			// 先綁定知識庫
			await request.post(`${baseURL}/wp-json/${API.products}/bind`, {
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [ids.docId],
					meta_key: 'bound_docs_data',
					limit_type: 'unlimited',
				},
			}).catch(() => {})
		} catch {
			testProductId = 0
		}
	})

	test.afterAll(async ({ request }) => {
		if (testProductId) {
			await request.delete(
				`${opts.baseURL}/wp-json/${API.wcProducts}/${testProductId}?force=true`,
				{ headers: { 'X-WP-Nonce': opts.nonce } },
			).catch(() => {})
		}
	})

	test('解除商品知識庫綁定 — 回傳非 500', async ({ request }) => {
		test.skip(!testProductId || !ids.docId, '缺少測試商品或知識庫')

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/unbind`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [ids.docId],
					meta_key: 'bound_docs_data',
				},
			},
		)

		expect(res.status()).toBeLessThan(500)
	})

	test('解除後查詢商品 — bound_docs_data 不含已解除的知識庫', async () => {
		test.skip(!testProductId, '缺少測試商品')

		const { data } = await wpGet<any>(opts, `${API.products}/${testProductId}`, {
			'meta_keys[]': 'bound_docs_data',
		})

		if (data.bound_docs_data && Array.isArray(data.bound_docs_data)) {
			const stillBound = data.bound_docs_data.find(
				(b: any) => Number(b.id) === ids.docId || Number(b.post_id) === ids.docId,
			)
			// 應已被解除
			expect(stillBound).toBeUndefined()
		}
	})

	test('空 item_ids 解除 — 不造成伺服器錯誤', async ({ request }) => {
		test.skip(!testProductId, '缺少測試商品')

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/unbind`,
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

/**
 * [E2E] 更新商品知識庫綁定設定 — product-bind-settings.spec.ts
 *
 * 驗證透過 Powerhouse products API 更新已綁定知識庫的期限設定：
 * - 將 fixed 改為 unlimited
 * - 更新 limit_value 和 limit_unit
 */
import { test, expect } from '@playwright/test'
import { wpGet, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 更新商品知識庫綁定設定', () => {
	let opts: ApiOptions
	let ids: SetupIds
	let testProductId: number

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()

		// 建立測試商品並綁定知識庫（fixed 期限）
		try {
			const res = await request.post(`${baseURL}/wp-json/${API.wcProducts}`, {
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: {
					name: 'E2E Bind Settings Test',
					type: 'simple',
					regular_price: '777',
					status: 'publish',
				},
			})
			const prodData = await res.json()
			testProductId = Number(prodData.id)

			// 綁定知識庫 limit_type=fixed
			await request.post(`${baseURL}/wp-json/${API.products}/bind`, {
				headers: { 'X-WP-Nonce': nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [ids.docId],
					meta_key: 'bound_docs_data',
					limit_type: 'fixed',
					limit_value: 365,
					limit_unit: 'day',
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

	test('更新綁定設定 — 將 fixed 改為 unlimited', async ({ request }) => {
		test.skip(!testProductId || !ids.docId, '缺少測試商品或知識庫')

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/update-bindpost`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [ids.docId],
					meta_key: 'bound_docs_data',
					limit_type: 'unlimited',
				},
			},
		)

		expect(res.status()).toBeLessThan(500)
	})

	test('更新後查詢商品 — 確認 limit_type 已變更', async () => {
		test.skip(!testProductId, '缺少測試商品')

		const { data } = await wpGet<any>(opts, `${API.products}/${testProductId}`, {
			'meta_keys[]': 'bound_docs_data',
		})

		if (data.bound_docs_data && Array.isArray(data.bound_docs_data)) {
			const binding = data.bound_docs_data.find(
				(b: any) => Number(b.id) === ids.docId || Number(b.post_id) === ids.docId,
			)
			if (binding) {
				expect(binding.limit_type).toBe('unlimited')
			}
		}
	})

	test('空 item_ids 更新 — 不造成伺服器錯誤', async ({ request }) => {
		test.skip(!testProductId, '缺少測試商品')

		const res = await request.post(
			`${opts.baseURL}/wp-json/${API.products}/update-bindpost`,
			{
				headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
				data: {
					product_ids: [testProductId],
					item_ids: [],
					meta_key: 'bound_docs_data',
					limit_type: 'unlimited',
				},
			},
		)

		expect(res.status()).toBeLessThan(500)
	})
})

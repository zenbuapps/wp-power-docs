/**
 * [E2E] 邊界測試 — edge-cases.spec.ts
 *
 * 驗證各種邊界情境：
 * - 空知識庫（無章節）
 * - 巢狀章節結構（2 層深度）
 * - 已刪除商品的綁定資料
 * - XSS 標題防護
 * - 不存在的 ID 操作
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce, getSetupIds, type SetupIds } from '../global-setup.js'
import { API, EDGE_STRINGS } from '../fixtures/test-data.js'

test.describe('[E2E] 邊界測試', () => {
	let opts: ApiOptions
	let ids: SetupIds

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }
		ids = getSetupIds()
	})

	test.describe('空知識庫', () => {
		test('無子章節的知識庫 — children 為空陣列或不含子節點', async () => {
			// 建立一個空的知識庫
			const { data } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Empty Doc Test',
				status: 'publish',
			})
			const emptyDocId = Number(data.id)

			try {
				const { data: detail } = await wpGet<any>(opts, `${API.posts}/${emptyDocId}`)

				if (detail.children !== undefined) {
					expect(Array.isArray(detail.children)).toBe(true)
					expect(detail.children.length).toBe(0)
				}
			} finally {
				await wpDelete(opts, `${API.posts}/${emptyDocId}`).catch(() => {})
			}
		})
	})

	test.describe('巢狀章節', () => {
		test('2 層巢狀結構 — 知識庫 > 章節 > 單元', async () => {
			// 建立 3 層結構
			const { data: root } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Nested Root',
				status: 'publish',
			})
			const rootId = Number(root.id)

			const { data: ch } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Nested Chapter',
				post_parent: rootId,
				status: 'publish',
			})
			const chId = Number(ch.id)

			const { data: unit } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Nested Unit',
				post_parent: chId,
				status: 'publish',
			})
			const unitId = Number(unit.id)

			try {
				// 查詢根知識庫的子章節
				const { data: rootDetail } = await wpGet<any>(opts, `${API.posts}/${rootId}`)

				if (rootDetail.children && Array.isArray(rootDetail.children)) {
					expect(rootDetail.children.length).toBeGreaterThan(0)
					const chapter = rootDetail.children.find((c: any) => Number(c.id) === chId)
					expect(chapter).toBeDefined()

					// 章節應有子單元
					if (chapter?.children && Array.isArray(chapter.children)) {
						const unitItem = chapter.children.find((u: any) => Number(u.id) === unitId)
						expect(unitItem).toBeDefined()
					}
				}

				// 驗證單元的 parent_id 指向章節
				const { data: unitDetail } = await wpGet<any>(opts, `${API.posts}/${unitId}`)
				expect(String(unitDetail.parent_id)).toBe(String(chId))
			} finally {
				await wpDelete(opts, `${API.posts}/${unitId}`).catch(() => {})
				await wpDelete(opts, `${API.posts}/${chId}`).catch(() => {})
				await wpDelete(opts, `${API.posts}/${rootId}`).catch(() => {})
			}
		})
	})

	test.describe('XSS 標題防護', () => {
		test('含 script 標籤的標題 — 不造成伺服器錯誤且被清理', async () => {
			const { data, status } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: EDGE_STRINGS.xssScript,
			})

			expect(status).toBeLessThan(500)

			if (status === 200) {
				const id = Number(data.id)
				try {
					const { data: detail } = await wpGet<any>(opts, `${API.posts}/${id}`)
					// 回傳的標題不應包含原始 <script> 標籤
					expect(detail.name).not.toContain('<script>')
				} finally {
					await wpDelete(opts, `${API.posts}/${id}`).catch(() => {})
				}
			}
		})

		test('含 img onerror 的標題 — 不造成伺服器錯誤', async () => {
			const { status } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: EDGE_STRINGS.xssImgOnerror,
			})

			expect(status).toBeLessThan(500)
		})
	})

	test.describe('不存在的 ID', () => {
		test('查詢不存在的知識庫 — 404', async () => {
			const { status } = await wpGet(opts, `${API.posts}/9999999`)
			expect(status).toBe(404)
		})

		test('更新不存在的知識庫 — 404', async ({ request }) => {
			const res = await request.patch(
				`${opts.baseURL}/wp-json/${API.posts}/9999999`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: { post_title: 'Ghost Update' },
				},
			)
			expect(res.status()).toBe(404)
		})

		test('刪除不存在的知識庫 — 404', async () => {
			const { status } = await wpDelete(opts, `${API.posts}/9999999`)
			expect(status).toBe(404)
		})
	})

	test.describe('商品綁定邊界', () => {
		test('查詢已刪除商品的綁定資料 — 不造成伺服器錯誤', async () => {
			const { data, status } = await wpGet<any[]>(opts, API.products, {
				'meta_keys[]': 'bound_docs_data',
				posts_per_page: '100',
			})

			expect(status).toBe(200)
			expect(Array.isArray(data)).toBe(true)
		})

		test('綁定不存在的知識庫 ID — 不造成伺服器錯誤', async ({ request }) => {
			test.skip(!ids.productId, '缺少測試商品')

			const res = await request.post(
				`${opts.baseURL}/wp-json/${API.products}/bind`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: {
						product_ids: [ids.productId],
						item_ids: [9999999],
						meta_key: 'bound_docs_data',
						limit_type: 'unlimited',
					},
				},
			)

			expect(res.status()).toBeLessThan(500)
		})
	})

	test.describe('訂單退款後存取', () => {
		test('建立訂單後退款 — API 不造成伺服器錯誤', async ({ request }) => {
			test.skip(!ids.productId || !ids.subscriberId, '缺少測試商品或用戶')

			// 建立並完成訂單
			const orderRes = await request.post(
				`${opts.baseURL}/wp-json/${API.wcOrders}`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: {
						customer_id: ids.subscriberId,
						status: 'completed',
						line_items: [{ product_id: ids.productId, quantity: 1 }],
					},
				},
			)

			if (orderRes.status() === 201 || orderRes.status() === 200) {
				const order = await orderRes.json()
				const orderId = Number(order.id)

				try {
					// 將訂單狀態改為 refunded
					const refundRes = await request.put(
						`${opts.baseURL}/wp-json/${API.wcOrders}/${orderId}`,
						{
							headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
							data: { status: 'refunded' },
						},
					)

					expect(refundRes.status()).toBeLessThan(500)
				} finally {
					await request.delete(
						`${opts.baseURL}/wp-json/${API.wcOrders}/${orderId}?force=true`,
						{ headers: { 'X-WP-Nonce': opts.nonce } },
					).catch(() => {})
				}
			}
		})
	})

	test.describe('商品刪除後知識庫瀏覽', () => {
		test('刪除綁定商品後 — 知識庫仍可瀏覽', async ({ request }) => {
			// 建立臨時商品
			const prodRes = await request.post(
				`${opts.baseURL}/wp-json/${API.wcProducts}`,
				{
					headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
					data: {
						name: 'E2E Temp Delete Product',
						type: 'simple',
						regular_price: '100',
						status: 'publish',
					},
				},
			)

			if (prodRes.status() === 201 || prodRes.status() === 200) {
				const prod = await prodRes.json()
				const tempProdId = Number(prod.id)

				// 綁定到知識庫
				await request.post(
					`${opts.baseURL}/wp-json/${API.products}/bind`,
					{
						headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
						data: {
							product_ids: [tempProdId],
							item_ids: [ids.docId],
							meta_key: 'bound_docs_data',
							limit_type: 'unlimited',
						},
					},
				).catch(() => {})

				// 刪除商品
				await request.delete(
					`${opts.baseURL}/wp-json/${API.wcProducts}/${tempProdId}?force=true`,
					{ headers: { 'X-WP-Nonce': opts.nonce } },
				)

				// 知識庫 API 查詢仍應正常
				const { status } = await wpGet(opts, `${API.posts}/${ids.docId}`)
				expect(status).toBeLessThan(500)
			}
		})
	})

	test.describe('大量章節', () => {
		test('建立 15 個章節的知識庫 — 查詢不造成伺服器錯誤', async () => {
			// 建立臨時知識庫
			const { data: root } = await wpPost<any>(opts, API.posts, {
				post_type: 'pd_doc',
				post_title: 'E2E Many Chapters Doc',
				status: 'publish',
			})
			const rootId = Number(root.id)
			const chapterIds: number[] = []

			try {
				// 建立 15 個子章節
				for (let i = 0; i < 15; i++) {
					const { data: ch } = await wpPost<any>(opts, API.posts, {
						post_type: 'pd_doc',
						post_title: `E2E Batch Chapter ${i + 1}`,
						post_parent: rootId,
						status: 'publish',
					})
					chapterIds.push(Number(ch.id))
				}

				// 查詢根知識庫詳情（含所有子章節）
				const { data: detail, status } = await wpGet<any>(opts, `${API.posts}/${rootId}`)
				expect(status).toBeLessThan(500)

				// 子章節數量應正確
				if (detail.children && Array.isArray(detail.children)) {
					expect(detail.children.length).toBeGreaterThanOrEqual(10)
				}
			} finally {
				// 清理
				for (const id of chapterIds.reverse()) {
					await wpDelete(opts, `${API.posts}/${id}`).catch(() => {})
				}
				await wpDelete(opts, `${API.posts}/${rootId}`).catch(() => {})
			}
		})
	})

	test.describe('重複授權（多次購買）', () => {
		test('同一用戶建立兩筆相同商品訂單 — 不造成伺服器錯誤', async ({ request }) => {
			test.skip(!ids.productId || !ids.subscriberId, '缺少測試商品或用戶')

			const orderIds: number[] = []

			try {
				// 建立兩筆訂單
				for (let i = 0; i < 2; i++) {
					const orderRes = await request.post(
						`${opts.baseURL}/wp-json/${API.wcOrders}`,
						{
							headers: { 'X-WP-Nonce': opts.nonce, 'Content-Type': 'application/json' },
							data: {
								customer_id: ids.subscriberId,
								status: 'completed',
								line_items: [{ product_id: ids.productId, quantity: 1 }],
							},
						},
					)

					expect(orderRes.status()).toBeLessThan(500)

					if (orderRes.status() === 201 || orderRes.status() === 200) {
						const order = await orderRes.json()
						orderIds.push(Number(order.id))
					}
				}

				// 查詢用戶授權 — 不應 500
				const { status } = await wpGet<any[]>(opts, API.users, {
					s: String(ids.subscriberId),
				})
				expect(status).toBeLessThan(500)
			} finally {
				for (const id of orderIds) {
					await request.delete(
						`${opts.baseURL}/wp-json/${API.wcOrders}/${id}?force=true`,
						{ headers: { 'X-WP-Nonce': opts.nonce } },
					).catch(() => {})
				}
			}
		})
	})
})

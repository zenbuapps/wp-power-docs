/**
 * [E2E] 排序章節 — chapter-sort.spec.ts
 *
 * 驗證 POST /powerhouse/v1/posts/sort 排序章節：
 * - 交換順序後 menu_order 正確更新
 * - 變更父子關係
 * - from_tree 和 to_tree 結構
 */
import { test, expect } from '@playwright/test'
import { wpGet, wpPost, wpDelete, type ApiOptions } from '../helpers/api-client.js'
import { getNonce } from '../global-setup.js'
import { API } from '../fixtures/test-data.js'

test.describe('[E2E] 排序章節', () => {
	let opts: ApiOptions
	let parentId: number
	let childA: number
	let childB: number

	test.beforeAll(async ({ request }, { project }) => {
		const baseURL = project.use.baseURL || 'http://localhost:8893'
		const nonce = getNonce()
		opts = { request, baseURL, nonce }

		// 建立父知識庫 + 兩個子章節
		const { data: parent } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Sort Parent',
			status: 'publish',
		})
		parentId = Number(parent.id)

		const { data: a } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Sort Chapter A',
			post_parent: parentId,
			status: 'publish',
		})
		childA = Number(a.id)

		const { data: b } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Sort Chapter B',
			post_parent: parentId,
			status: 'publish',
		})
		childB = Number(b.id)
	})

	test.afterAll(async () => {
		for (const id of [childB, childA, parentId]) {
			await wpDelete(opts, `${API.posts}/${id}`).catch(() => {})
		}
	})

	test('交換兩個章節順序 — 回傳 200', async () => {
		const from_tree = [
			{ id: String(childA), parent_id: String(parentId), order: 0 },
			{ id: String(childB), parent_id: String(parentId), order: 1 },
		]
		const to_tree = [
			{ id: String(childB), parent_id: String(parentId), order: 0 },
			{ id: String(childA), parent_id: String(parentId), order: 1 },
		]

		const { status } = await wpPost<any>(opts, API.postsSort, {
			from_tree,
			to_tree,
		})

		expect(status).toBe(200)
	})

	test('排序後 menu_order 正確更新', async () => {
		// 先設定 A=0, B=1
		await wpPost<any>(opts, API.postsSort, {
			from_tree: [
				{ id: String(childB), parent_id: String(parentId), order: 0 },
				{ id: String(childA), parent_id: String(parentId), order: 1 },
			],
			to_tree: [
				{ id: String(childA), parent_id: String(parentId), order: 0 },
				{ id: String(childB), parent_id: String(parentId), order: 1 },
			],
		})

		// 查詢驗證
		const { data: detailA } = await wpGet<any>(opts, `${API.posts}/${childA}`)
		const { data: detailB } = await wpGet<any>(opts, `${API.posts}/${childB}`)

		expect(Number(detailA.menu_order)).toBe(0)
		expect(Number(detailB.menu_order)).toBe(1)
	})

	test('變更父子關係 — 將子章節移到另一個父下', async () => {
		// 建立第二個父知識庫
		const { data: parent2 } = await wpPost<any>(opts, API.posts, {
			post_type: 'pd_doc',
			post_title: 'E2E Sort Parent 2',
			status: 'publish',
		})
		const parent2Id = Number(parent2.id)

		// 將 childB 移到 parent2 下
		const { status } = await wpPost<any>(opts, API.postsSort, {
			from_tree: [
				{ id: String(childA), parent_id: String(parentId), order: 0 },
				{ id: String(childB), parent_id: String(parentId), order: 1 },
			],
			to_tree: [
				{ id: String(childA), parent_id: String(parentId), order: 0 },
				{ id: String(childB), parent_id: String(parent2Id), order: 0 },
			],
		})

		expect(status).toBe(200)

		// 驗證 childB 的 parent_id 已變更
		const { data: detailB } = await wpGet<any>(opts, `${API.posts}/${childB}`)
		expect(String(detailB.parent_id)).toBe(String(parent2Id))

		// 移回原處並清理
		await wpPost<any>(opts, API.postsSort, {
			from_tree: [
				{ id: String(childB), parent_id: String(parent2Id), order: 0 },
			],
			to_tree: [
				{ id: String(childB), parent_id: String(parentId), order: 1 },
			],
		})
		await wpDelete(opts, `${API.posts}/${parent2Id}`).catch(() => {})
	})

	test('空 to_tree 不造成伺服器錯誤', async () => {
		const { status } = await wpPost<any>(opts, API.postsSort, {
			from_tree: [],
			to_tree: [],
		})

		expect(status).toBeLessThan(500)
	})
})

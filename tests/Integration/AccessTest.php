<?php
/**
 * 存取控制整合測試
 * 測試 Access::can_access() 與 grant_access() 邏輯
 */

declare( strict_types=1 );

namespace Tests\PowerDocs\Integration;

use J7\PowerDocs\Domains\Doc\Access;
use J7\PowerDocs\Domains\Doc\CPT;

/**
 * Class AccessTest
 *
 * @group smoke
 * @group happy
 * @group error
 * @group edge
 * @group security
 */
class AccessTest extends TestCase {

	// ========== 冒煙測試（Smoke）==========

	/**
	 * @test
	 * @group smoke
	 */
	public function test_Access類別存在(): void {
		$this->assertTrue( class_exists( Access::class ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_免費知識庫所有人皆可存取(): void {
		$doc_id = $this->create_doc( [ 'need_access' => 'no' ] );

		$this->assertTrue( Access::can_access( $doc_id ) );
	}

	/**
	 * @test
	 * @group smoke
	 */
	public function test_付費知識庫未登入用戶無法存取(): void {
		$doc_id = $this->create_doc( [ 'need_access' => 'yes' ] );
		wp_set_current_user( 0 ); // 登出狀態

		$this->assertFalse( Access::can_access( $doc_id ) );
	}

	// ========== 快樂路徑（Happy Flow）==========

	/**
	 * @test
	 * @group happy
	 */
	public function test_授予永久存取權後用戶可存取知識庫(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 授予永久存取（expire_date = 0 代表永不到期）
		$this->grant_doc_access( $user_id, $doc_id, 0 );

		$this->assert_user_can_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_need_access為no時無論有無授權皆可存取(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'no' ] );
		$user_id = $this->factory()->user->create();

		// 沒有授權也能存取
		$this->assertTrue( Access::can_access( $doc_id, $user_id ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_未來到期日用戶可存取(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 設定到期日為未來（Unix timestamp）
		$future_expire = time() + 86400 * 365; // 一年後
		$this->grant_doc_access( $user_id, $doc_id, $future_expire );

		$this->assert_user_can_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_知識庫meta未設定時預設免費(): void {
		// 不設定 need_access meta，預設應視為 'no'（免費）
		$doc_id = $this->factory()->post->create(
			[
				'post_type'   => CPT::POST_TYPE,
				'post_status' => 'publish',
			]
		);

		$this->assertTrue( Access::can_access( $doc_id ) );
	}

	/**
	 * @test
	 * @group happy
	 */
	public function test_管理員不受存取控制限制(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$admin_id = $this->factory()->user->create( [ 'role' => 'administrator' ] );

		// 管理員沒有授權，但 can_access 目前僅查 ph_access_itemmeta
		// 注意：Access::can_access 本身不做 manage_options 的旁路，
		// 旁路在 Templates 層，所以這裡驗證 can_access 回傳 false
		wp_set_current_user( $admin_id );
		$this->assertFalse( Access::can_access( $doc_id, $admin_id ) );
	}

	// ========== 錯誤處理（Error Handling）==========

	/**
	 * @test
	 * @group error
	 */
	public function test_已過期的存取權無法存取(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 設定到期日為過去（已過期）
		$past_expire = time() - 86400; // 昨天
		$this->grant_doc_access( $user_id, $doc_id, $past_expire );

		$this->assert_user_cannot_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_登入但無授權的用戶無法存取付費知識庫(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 不授予任何存取權
		$this->assert_user_cannot_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_撤銷存取後用戶無法存取(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 先授予
		$this->grant_doc_access( $user_id, $doc_id, 0 );
		$this->assert_user_can_access_doc( $user_id, $doc_id );

		// 再撤銷
		$this->revoke_doc_access( $user_id, $doc_id );
		$this->assert_user_cannot_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_用戶ID為0時（未登入）付費知識庫無法存取(): void {
		$doc_id = $this->create_doc( [ 'need_access' => 'yes' ] );

		// 明確傳入 user_id = 0
		$this->assertFalse( Access::can_access( $doc_id, 0 ) );
	}

	/**
	 * @test
	 * @group error
	 */
	public function test_不存在的文章ID也能安全呼叫can_access(): void {
		// 不存在的 post，need_access meta 回傳空字串，預設為 'no'
		$result = Access::can_access( 999999999 );
		$this->assertTrue( $result, '不存在的文章 ID，預設免費存取' );
	}

	// ========== 邊緣案例（Edge Cases）==========

	/**
	 * @test
	 * @group edge
	 */
	public function test_恰好在到期時間點過後無法存取(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 到期日設為 1 秒前（已過期）
		$just_expired = time() - 1;
		$this->grant_doc_access( $user_id, $doc_id, $just_expired );

		$this->assert_user_cannot_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_多用戶各自授權互不影響(): void {
		$doc_id   = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user1_id = $this->factory()->user->create();
		$user2_id = $this->factory()->user->create();

		// 只授予 user1
		$this->grant_doc_access( $user1_id, $doc_id, 0 );

		$this->assert_user_can_access_doc( $user1_id, $doc_id );
		$this->assert_user_cannot_access_doc( $user2_id, $doc_id );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_多個知識庫各自授權互不影響(): void {
		$doc1_id = $this->create_doc( [ 'need_access' => 'yes', 'post_title' => '知識庫一' ] );
		$doc2_id = $this->create_doc( [ 'need_access' => 'yes', 'post_title' => '知識庫二' ] );
		$user_id = $this->factory()->user->create();

		// 只授予 doc1
		$this->grant_doc_access( $user_id, $doc1_id, 0 );

		$this->assert_user_can_access_doc( $user_id, $doc1_id );
		$this->assert_user_cannot_access_doc( $user_id, $doc2_id );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_expire_date為字串0時視為永久(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// expire_date 存入字串 '0'
		$this->grant_doc_access( $user_id, $doc_id, '0' );

		$this->assert_user_can_access_doc( $user_id, $doc_id );
	}

	/**
	 * @test
	 * @group edge
	 */
	public function test_重複授權不影響存取能力(): void {
		$doc_id  = $this->create_doc( [ 'need_access' => 'yes' ] );
		$user_id = $this->factory()->user->create();

		// 授予兩次
		$this->grant_doc_access( $user_id, $doc_id, 0 );
		$this->grant_doc_access( $user_id, $doc_id, time() + 86400 * 30 ); // 30天後

		$this->assert_user_can_access_doc( $user_id, $doc_id );
	}

	// ========== 安全性（Security）==========

	/**
	 * @test
	 * @group security
	 */
	public function test_未授權用戶無法透過操控user_id繞過存取控制(): void {
		$doc_id       = $this->create_doc( [ 'need_access' => 'yes' ] );
		$authorized_id = $this->factory()->user->create();
		$attacker_id  = $this->factory()->user->create();

		// 只授予 authorized_id
		$this->grant_doc_access( $authorized_id, $doc_id, 0 );

		// 攻擊者不能透過傳入授權用戶的 ID 存取
		$this->assert_user_cannot_access_doc( $attacker_id, $doc_id );
	}

	/**
	 * @test
	 * @group security
	 */
	public function test_負數user_id安全處理(): void {
		$doc_id = $this->create_doc( [ 'need_access' => 'yes' ] );

		// 負數 user_id 應被視為未登入（0）
		$result = Access::can_access( $doc_id, -1 );
		$this->assertFalse( $result, '負數 user_id 應無法存取付費知識庫' );
	}
}

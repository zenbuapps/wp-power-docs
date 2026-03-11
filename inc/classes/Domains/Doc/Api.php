<?php

declare(strict_types=1);

namespace J7\PowerDocs\Domains\Doc;

use J7\WpUtils\Classes\ApiBase;
use J7\Powerhouse\Domains\Limit\Models\GrantedItems;
use J7\WpUtils\Classes\WP;

/** Class Api */
final class Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'power-docs';

	/** @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [];

	/** Constructor */
	public function __construct() {
		parent::__construct();

		\add_filter( 'powerhouse/user/get_meta_keys_array', [ __CLASS__, 'extend_user_meta_keys' ], 10, 2 );
		\add_filter( 'powerhouse/post/get_meta_keys_array', [ __CLASS__, 'extend_post_meta_keys' ], 10, 2 );
		\add_filter( 'powerhouse/post/separator_body_params', [ __CLASS__, 'extra_file_upload' ], 10, 2 );
		\add_filter( 'powerhouse/post/create_post_args', [ __CLASS__, 'add_default_meta_keys' ], 10, 1 );
		\add_filter('powerhouse/copy/children_post_args', [ __CLASS__, 'copy_children_post_args' ], 10, 5);
	}

	/**
	 * 擴充用戶的 meta keys
	 *
	 * @param array<string, mixed> $meta_keys 用戶的 meta keys.
	 * @param \WP_User             $user 用戶.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_user_meta_keys( array $meta_keys, \WP_User $user ): array {
		if (!isset($meta_keys['granted_docs'])) {
			return $meta_keys;
		}

		$granted_items             = new GrantedItems( $user->ID );
		$meta_keys['granted_docs'] = $granted_items->get_granted_items(
			[
				'post_type' => CPT::POST_TYPE,
			]
			);
		return $meta_keys;
	}

	/**
	 * 擴充文章的 meta keys
	 *
	 * @param array<string, mixed> $meta_keys 文章的 meta keys.
	 * @param \WP_Post             $post 文章.
	 *
	 * @return array<string, mixed>
	 */
	public static function extend_post_meta_keys( array $meta_keys, \WP_Post $post ): array {

		if (CPT::POST_TYPE !== $post->post_type) {
			return $meta_keys;
		}

		// 設定預設 editor
		$editor = \get_post_meta( $post->ID, 'editor', true );
		// 如果是沒有 post_parent ，那麼此 $post 就是知識庫， editor 如果為 '' 代表為預設版型，不需要預設為 'power-editor'
		// 有 post_parent ，代表是章節/子文章，那麼 editor ，需要為 'power-editor'
		if ($post->post_parent) {
			$editor = $editor ?: 'power-editor';
		}
		$meta_keys['editor'] = $editor;

		// 只有頂層需要 bg_images
		if ($post->post_parent) {
			return $meta_keys;
		}

		if (isset($meta_keys['bg_images'])) {
			$bg_images_id           = \get_post_meta( $post->ID, 'bg_images', true );
			$image_info             = WP::get_image_info( (int) $bg_images_id);
			$meta_keys['bg_images'] = $image_info ? [ $image_info ] : [];
		}

		return $meta_keys;
	}

	/**
	 * 額外處理文件上傳
	 *
	 * @param array<string, mixed> $body_params 表單參數.
	 * @param \WP_REST_Request     $request 請求.
	 *
	 * @return array<string, mixed>
	 * @throws \Exception 上傳失敗
	 */
	public static function extra_file_upload( array $body_params, \WP_REST_Request $request ): array {
		$image_names = [ 'bg_images' ];
		$file_params = $request->get_file_params();

		foreach ($image_names as $image_name) {
			if (isset($file_params[ $image_name ])) {
				try {
					$upload_results = WP::upload_files( $file_params[ $image_name ] );
					// db 儲存 image id
					$body_params[ $image_name ] = $upload_results[0]['id'];
				} catch (\Throwable $th) {
					\J7\WpUtils\Classes\WC::log( $th->getMessage(), 'upload_files error' );
				}
			}
		}

		foreach ($image_names as $image_name) {
			// 如果前端傳 delete 過來，則刪除 db 的 image id
			if ('delete' === ( $body_params[ $image_name ] ?? '' )) {
				$body_params[ $image_name ] = '';
				continue;
			}
			// 如果不是 delete 也不是數字，那代表沒有動作，那也不用傳給 db
			if (!\is_numeric($body_params[ $image_name ])) {
				unset($body_params[ $image_name ]);
			}
		}
		return $body_params;
	}

	/**
	 * 文章創建時，先創建好預設的 meta keys
	 *
	 * @param array<string, mixed> $args 文章參數.
	 *
	 * @return array<string, mixed>
	 */
	public static function add_default_meta_keys( array $args ): array {
		if (CPT::POST_TYPE !== $args['post_type']) {
			return $args;
		}

		// 只有頂層需要 pd_keywords_label，不是頂層，有 post_parent 的話就離開
		if ($args['post_parent']) {
			return $args;
		}

		if (!isset($args['meta_input'])) {
			$args['meta_input'] = [];
		}

		$args['meta_input']['pd_keywords_label']         = '大家都在搜：'; // @phpstan-ignore-line
		$args['meta_input']['pd_keywords']               = [ // @phpstan-ignore-line
			[
				'id'    => 'some_keyword_id',
				'title' => '某個關鍵字',
			],
		];
		$args['meta_input']['unauthorized_redirect_url'] = \site_url('404'); // @phpstan-ignore-line

		return $args;
	}


	/**
	 * 複製子文章時，需指定 post_type
	 *
	 * @param array<string, mixed> $default_args 文章參數.
	 * @param int                  $post_id 文章 ID.
	 * @param int                  $new_id 新文章 ID.
	 * @param int                  $override_post_parent 覆蓋父文章 ID.
	 * @param int                  $depth 深度.
	 *
	 * @return array<string, mixed>
	 */
	public static function copy_children_post_args( $default_args, $post_id, $new_id, $override_post_parent, $depth ): array {
		$post_type = \get_post_type($post_id);
		if (CPT::POST_TYPE !== $post_type) {
			return $default_args;
		}
		$default_args['post_type'] = CPT::POST_TYPE;
		return $default_args;
	}
}

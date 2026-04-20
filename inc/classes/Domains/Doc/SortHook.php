<?php
/**
 * SortHook — Powerhouse `posts/sort` REST endpoint 的後處理 hook
 *
 * 職責：批次 SQL UPDATE 繞過 `save_post_pd_doc`，故
 * `power_docs_get_children_posts_html_{top_parent_id}` transient
 * 永遠陳舊。在此 hook 精準清除 pd_doc 受影響 root 的 transient。
 *
 * 注意：
 * - Only 套用於 post_type = pd_doc 的節點（避免影響其他 Powerhouse 消費者）
 * - 只在 REST 回應非錯誤時執行
 */

declare( strict_types=1 );

namespace J7\PowerDocs\Domains\Doc;

use J7\Powerhouse\Domains\Post\Utils\CRUD as PostUtils;

/**
 * Class SortHook
 */
final class SortHook {
	use \J7\WpUtils\Traits\SingletonTrait;

	/**
	 * 目標 REST 路由（Powerhouse 共用層提供）
	 *
	 * @var string
	 */
	public const TARGET_ROUTE = '/v2/powerhouse/posts/sort';

	/** Constructor */
	public function __construct() {
		\add_filter( 'rest_request_after_callbacks', [ __CLASS__, 'after_sort' ], 10, 3 );
	}

	/**
	 * REST request-after-callbacks filter：
	 * 在 Powerhouse `posts/sort` 成功後清除受影響 root pd_doc 的 transient 快取。
	 *
	 * @param \WP_REST_Response|\WP_Error|mixed $response REST response.
	 * @param array                             $handler  Route handler.
	 * @param \WP_REST_Request                  $request  Request instance.
	 *
	 * @return \WP_REST_Response|\WP_Error|mixed
	 */
	public static function after_sort( $response, $handler, $request ) {
		if ( ! ( $request instanceof \WP_REST_Request ) ) {
			return $response;
		}
		if ( self::TARGET_ROUTE !== $request->get_route() ) {
			return $response;
		}

		if ( $response instanceof \WP_Error ) {
			return $response;
		}
		if ( is_object( $response ) && method_exists( $response, 'is_error' ) && $response->is_error() ) {
			return $response;
		}

		$body    = $request->get_json_params();
		$to_tree = isset( $body['to_tree'] ) && is_array( $body['to_tree'] ) ? $body['to_tree'] : [];
		if ( empty( $to_tree ) ) {
			return $response;
		}

		// 收集受影響 pd_doc 的 root ID
		$affected_root_ids = [];
		foreach ( $to_tree as $node ) {
			if ( ! isset( $node['id'] ) ) {
				continue;
			}
			$post_id = (int) $node['id'];
			if ( $post_id <= 0 ) {
				continue;
			}

			if ( CPT::POST_TYPE !== \get_post_type( $post_id ) ) {
				continue;
			}

			$top_id = PostUtils::get_top_post_id( $post_id );
			if ( $top_id > 0 ) {
				$affected_root_ids[ $top_id ] = true;
			}
		}

		foreach ( array_keys( $affected_root_ids ) as $root_id ) {
			\delete_transient( Utils::get_cache_key( $root_id ) );
		}

		return $response;
	}
}

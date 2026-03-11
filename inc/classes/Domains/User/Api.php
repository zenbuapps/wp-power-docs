<?php

declare(strict_types=1);

namespace J7\PowerDocs\Domains\User;

use J7\WpUtils\Classes\ApiBase;
use J7\WpUtils\Classes\WP;
use J7\Powerhouse\Domains\User\Model\User;

/** Class Api */
final class Api extends ApiBase {
	use \J7\WpUtils\Traits\SingletonTrait;

	/** @var string Namespace */
	protected $namespace = 'power-docs';

	/**  @var array{endpoint:string,method:string,permission_callback: ?callable }[] APIs */
	protected $apis = [
		[
			'endpoint'            => 'users',
			'method'              => 'get',
			'permission_callback' => null,
		],
	];



	/**
	 * Get users callback
	 * 通用的用戶查詢，因為需要用 "已開通知識庫" 查詢用戶，所以需要獨立寫
	 *
	 * @param \WP_REST_Request $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_users_callback( $request ): \WP_REST_Response {
		$params = $request->get_query_params();
		$params = WP::sanitize_text_field_deep( $params, false );

		$posts_per_page = intval($params['posts_per_page'] ?? 20); // @phpstan-ignore-line
		$paged          = intval($params['paged'] ?? 1); // @phpstan-ignore-line
		$offset         = ( $paged - 1 ) * $posts_per_page;
		$search         = (string) ( $params['s'] ?? $params['search'] ?? '' ); // @phpstan-ignore-line
		$orderby        = (string) ( $params['orderby'] ?? 'ID' ); // @phpstan-ignore-line
		$order          = (string) ( $params['order'] ?? 'DESC' ); // @phpstan-ignore-line

		global $wpdb;

		// 基礎 SQL
		$select_sql = 'SELECT DISTINCT u.ID';
		$count_sql  = 'SELECT COUNT(DISTINCT u.ID)';
		$from_sql   = " FROM {$wpdb->users} u";
		$where_sql  = ' WHERE 1=1';

		// 搜尋條件，'ID', 'user_login', 'user_email', 'user_nicename', 'display_name'
		if ($search) {
			$search     = '%' . $wpdb->esc_like($search) . '%';
			$where_sql .= " AND (u.ID LIKE '{$search}' OR u.user_login LIKE '{$search}' OR u.user_email LIKE '{$search}' OR u.user_nicename LIKE '{$search}' OR u.display_name LIKE '{$search}')";
		}

		if (isset($params['granted_docs'])) {
			$granted_docs = is_array($params['granted_docs']) ? $params['granted_docs'] : [];
			if ($granted_docs) {
				$im_table_name = $wpdb->prefix . 'ph_access_itemmeta';
				$from_sql     .= " LEFT JOIN {$im_table_name} im ON u.ID = im.user_id";
				$placeholders  = implode(',', array_map(fn( $id ) => "'" . (string) $id . "'", $granted_docs));
				$where_sql    .= " AND im.meta_key = 'expire_date' AND im.post_id IN ({$placeholders}) ";
			}
		}

		// Meta 查詢
		// TODO 可以再優化更複雜的查詢
		if (isset($params['meta_key'])) {
			$meta_key   = (string) $params['meta_key'];
			$from_sql  .= " LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id";
			$where_sql .= " AND um.meta_key = {$meta_key} ";

			if (isset($params['meta_value'])) {
				$meta_value = (string) $params['meta_value'];
				$where_sql .= " AND um.meta_value = {$meta_value} ";
			}
		}

		if (isset($params['granted_docs'])) {
			$granted_docs = is_array($params['granted_docs']) ? $params['granted_docs'] : [];
			if ($granted_docs) {
				$count_granted_docs = count($granted_docs);
				$where_sql         .= " GROUP BY u.ID HAVING COUNT(DISTINCT im.post_id) = {$count_granted_docs} ";
			}
		}

		// 排序
		$order_sql = " ORDER BY u.{$orderby} {$order}";

		// 分頁
		$limit_sql = $wpdb->prepare(' LIMIT %d OFFSET %d', $posts_per_page, $offset);

		// 執行查詢
		$total_sql = $count_sql . $from_sql . $where_sql;
		$totals    = $wpdb->get_col(\wp_unslash($wpdb->prepare($total_sql))); // phpcs:ignore
		// 將每個數值加總
		$total = array_sum($totals);

		$user_ids = $wpdb->get_col(\wp_unslash($select_sql . $from_sql . $where_sql . $order_sql . $limit_sql)); // phpcs:ignore

		$total_pages = ceil($total / $posts_per_page);

		/** @var array<string> $meta_keys 要暴露的 meta keys */
		$meta_keys = $params['meta_keys'] ?? [];

		$formatted_users = [];
		foreach ($user_ids as $user_id) {
			$formatted_users[] = User::instance( (int) $user_id )->to_array('list', $meta_keys);
		}
		$formatted_users = array_filter( $formatted_users );

		$response = new \WP_REST_Response( $formatted_users );

		// set pagination in header
		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) $total_pages );
		$response->header( 'X-WP-CurrentPage', (string) $paged );
		$response->header( 'X-WP-PageSize', (string) $posts_per_page );

		return $response;
	}
}

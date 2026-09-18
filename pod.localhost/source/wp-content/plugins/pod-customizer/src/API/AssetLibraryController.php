<?php

namespace PodCustomizer\API;

use PodCustomizer\Contracts\HandlerInterface;
use PodCustomizer\Database\Repositories\IconCategoryRepository;
use PodCustomizer\Database\Repositories\IconRepository;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Class AssetLibraryController
 * Exposes REST API endpoints for managing and retrieving Icon Categories and Icons.
 */
class AssetLibraryController implements HandlerInterface {

    public const NAMESPACE = 'pod-customizer/v1';

    /**
     * {@inheritdoc}
     */
    public function register_hooks(): void {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    /**
     * Register REST routes.
     */
    public function register_routes(): void {
        // Public / Storefront routes
        register_rest_route(self::NAMESPACE, '/asset-library/categories', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_categories'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/categories/(?P<slug>[a-zA-Z0-9_-]+)/icons', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_icons_by_category'],
            'permission_callback' => '__return_true',
        ]);

        // Admin Management Routes
        register_rest_route(self::NAMESPACE, '/asset-library/categories', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_category'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/categories/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_category'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/categories/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_category'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/icons', [
            'methods'             => 'POST',
            'callback'            => [$this, 'create_or_bulk_icons'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/icons/(?P<id>\d+)', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update_icon'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);

        register_rest_route(self::NAMESPACE, '/asset-library/icons/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'delete_icon'],
            'permission_callback' => [$this, 'check_admin_permission'],
        ]);
    }

    /**
     * Check if user has admin privileges.
     */
    public function check_admin_permission(): bool {
        return current_user_can('manage_woocommerce') || current_user_can('manage_options');
    }

    /**
     * GET /asset-library/categories
     */
    public function get_categories(WP_REST_Request $request): WP_REST_Response {
        $terms = get_terms([
            'taxonomy'   => 'pod_icon_category',
            'hide_empty' => false,
        ]);

        if (!empty($terms) && !is_wp_error($terms)) {
            $categories = array_map(function($term) {
                return (object)[
                    'id'          => $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'description' => $term->description,
                    'icon_count'  => (int)$term->count,
                    'is_active'   => 1,
                ];
            }, $terms);

            return new WP_REST_Response([
                'success' => true,
                'data'    => $categories,
            ], 200);
        }

        $active_only = !$this->check_admin_permission();
        $categories = IconCategoryRepository::get_all($active_only);

        return new WP_REST_Response([
            'success' => true,
            'data'    => $categories,
        ], 200);
    }

    /**
     * GET /asset-library/categories/{slug}/icons
     */
    public function get_icons_by_category(WP_REST_Request $request): WP_REST_Response {
        $slug = sanitize_title($request->get_param('slug'));
        $term = get_term_by('slug', $slug, 'pod_icon_category');

        if ($term && !is_wp_error($term)) {
            $posts = get_posts([
                'post_type'      => 'pod_icon',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'tax_query'      => [
                    [
                        'taxonomy' => 'pod_icon_category',
                        'field'    => 'term_id',
                        'terms'    => $term->term_id,
                    ],
                ],
                'orderby'        => 'menu_order title',
                'order'          => 'ASC',
            ]);

            $icons = array_map(function($p) use ($term) {
                $thumb_id = get_post_thumbnail_id($p->ID);
                $thumb_url = $thumb_id ? wp_get_attachment_url($thumb_id) : get_post_meta($p->ID, '_pod_thumbnail_url', true);
                $print_url = get_post_meta($p->ID, '_pod_print_url', true) ?: $thumb_url;
                $is_vector = get_post_meta($p->ID, '_pod_is_vector', true);

                return (object)[
                    'id'            => $p->ID,
                    'category_id'   => $term->term_id,
                    'title'         => $p->post_title,
                    'slug'          => $p->post_name,
                    'thumbnail_url' => $thumb_url,
                    'print_url'     => $print_url,
                    'is_vector'     => (int)$is_vector,
                    'status'        => 'active',
                ];
            }, $posts);

            return new WP_REST_Response([
                'success'  => true,
                'category' => (object)[
                    'id'          => $term->term_id,
                    'name'        => $term->name,
                    'slug'        => $term->slug,
                    'description' => $term->description,
                ],
                'data'     => $icons,
            ], 200);
        }

        // Fallback to custom table repository if term not found
        $category = IconCategoryRepository::get_by_slug($slug);
        if (!$category) {
            return new WP_REST_Response([
                'success' => false,
                'error'   => 'Category not found',
            ], 404);
        }

        $active_only = !$this->check_admin_permission();
        $icons = IconRepository::get_by_category((int)$category->id, $active_only);

        return new WP_REST_Response([
            'success'  => true,
            'category' => $category,
            'data'     => $icons,
        ], 200);
    }

    /**
     * POST /asset-library/categories
     */
    public function create_category(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_body_params();
        $name = sanitize_text_field($params['name'] ?? '');

        if (empty($name)) {
            return new WP_REST_Response(['success' => false, 'error' => 'Category name is required'], 400);
        }

        $id = IconCategoryRepository::create([
            'name'        => $name,
            'slug'        => $params['slug'] ?? '',
            'description' => $params['description'] ?? '',
            'sort_order'  => (int)($params['sort_order'] ?? 0),
            'is_active'   => isset($params['is_active']) ? (int)(bool)$params['is_active'] : 1,
        ]);

        if (!$id) {
            return new WP_REST_Response(['success' => false, 'error' => 'Failed to create category'], 500);
        }

        $created = IconCategoryRepository::get_by_id($id);
        return new WP_REST_Response(['success' => true, 'data' => $created], 201);
    }

    /**
     * PUT /asset-library/categories/{id}
     */
    public function update_category(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request->get_param('id');
        $params = $request->get_json_params() ?: $request->get_body_params();

        $updated = IconCategoryRepository::update($id, $params);
        if (!$updated) {
            return new WP_REST_Response(['success' => false, 'error' => 'Failed to update category or no changes'], 400);
        }

        $category = IconCategoryRepository::get_by_id($id);
        return new WP_REST_Response(['success' => true, 'data' => $category], 200);
    }

    /**
     * DELETE /asset-library/categories/{id}
     */
    public function delete_category(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request->get_param('id');
        $deleted = IconCategoryRepository::delete($id);

        return new WP_REST_Response(['success' => $deleted], $deleted ? 200 : 400);
    }

    /**
     * POST /asset-library/icons
     * Handles single icon creation or bulk icons addition.
     */
    public function create_or_bulk_icons(WP_REST_Request $request): WP_REST_Response {
        $params = $request->get_json_params() ?: $request->get_body_params();

        // Check if bulk items array is sent
        if (isset($params['items']) && is_array($params['items'])) {
            $category_id = (int)($params['category_id'] ?? 0);
            if (!$category_id) {
                return new WP_REST_Response(['success' => false, 'error' => 'category_id is required'], 400);
            }

            $count = IconRepository::bulk_create($category_id, $params['items']);
            return new WP_REST_Response([
                'success' => true,
                'count'   => $count,
                'message' => sprintf('Successfully added %d icons.', $count),
            ], 201);
        }

        // Single icon creation
        $id = IconRepository::create($params);
        if (!$id) {
            return new WP_REST_Response(['success' => false, 'error' => 'Failed to create icon. Check required fields.'], 400);
        }

        $created = IconRepository::get_by_id($id);
        return new WP_REST_Response(['success' => true, 'data' => $created], 201);
    }

    /**
     * PUT /asset-library/icons/{id}
     */
    public function update_icon(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request->get_param('id');
        $params = $request->get_json_params() ?: $request->get_body_params();

        $updated = IconRepository::update($id, $params);
        if (!$updated) {
            return new WP_REST_Response(['success' => false, 'error' => 'Failed to update icon'], 400);
        }

        $icon = IconRepository::get_by_id($id);
        return new WP_REST_Response(['success' => true, 'data' => $icon], 200);
    }

    /**
     * DELETE /asset-library/icons/{id}
     */
    public function delete_icon(WP_REST_Request $request): WP_REST_Response {
        $id = (int)$request->get_param('id');
        $deleted = IconRepository::delete($id);

        return new WP_REST_Response(['success' => $deleted], $deleted ? 200 : 400);
    }
}

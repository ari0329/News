<?php
/**
 * Plugin Name: Custom News Manager
 * Plugin URI: http://example.com/custom-news-manager
 * Description: A simple news management plugin for WordPress with shortcode support (Network Sites Compatible)
 * Version: 2.0.2
 * Author: ari0329
 * Text Domain: custom-news-manager
 */

if (!defined('ABSPATH')) {
    exit;
}

// Using CNEWS prefix for all constants and functions
define('CNEWS_VERSION', '2.0.2');
define('CNEWS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('CNEWS_PLUGIN_URL', plugin_dir_url(__FILE__));

function cnews_register_news_post_type() {
    $labels = array(
        'name'               => __('News', 'custom-news-manager'),
        'singular_name'      => __('News', 'custom-news-manager'),
        'menu_name'          => __('News', 'custom-news-manager')
    );

    $args = array(
        'labels'              => $labels,
        'public'              => true,
        'show_ui'             => true,
        'supports'            => array('title', 'editor', 'thumbnail', 'excerpt'),
        'menu_icon'           => 'dashicons-format-aside',
        'taxonomies'          => array('news_category') // Add support for custom taxonomy
    );

    register_post_type('news', $args);
    
    register_post_meta('news', 'cnews_show_author', array(
        'type' => 'boolean',
        'description' => 'Show author name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    register_post_meta('news', 'cnews_show_date', array(
        'type' => 'boolean',
        'description' => 'Show publication date',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    register_post_meta('news', 'cnews_show_site', array(
        'type' => 'boolean',
        'description' => 'Show site name',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
    
    register_post_meta('news', 'cnews_homepage_request', array(
        'type' => 'boolean',
        'description' => 'Request to display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
}
add_action('init', 'cnews_register_news_post_type');

// Register custom taxonomy for news categories
function cnews_register_news_category_taxonomy() {
    $labels = array(
        'name'              => _x('News Categories', 'taxonomy general name', 'custom-news-manager'),
        'singular_name'     => _x('News Category', 'taxonomy singular name', 'custom-news-manager'),
        'search_items'      => __('Search News Categories', 'custom-news-manager'),
        'all_items'         => __('All News Categories', 'custom-news-manager'),
        'parent_item'       => __('Parent News Category', 'custom-news-manager'),
        'parent_item_colon' => __('Parent News Category:', 'custom-news-manager'),
        'edit_item'         => __('Edit News Category', 'custom-news-manager'),
        'update_item'       => __('Update News Category', 'custom-news-manager'),
        'add_new_item'      => __('Add New News Category', 'custom-news-manager'),
        'new_item_name'     => __('New News Category Name', 'custom-news-manager'),
        'menu_name'         => __('News Categories', 'custom-news-manager'),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array('slug' => 'news-category'),
        'show_in_rest'      => true,
    );

    register_taxonomy('news_category', array('news'), $args);
}
add_action('init', 'cnews_register_news_category_taxonomy');

// Add meta box for display options
function cnews_add_meta_boxes() {
    add_meta_box(
        'cnews_display_options',
        __('Display Options', 'custom-news-manager'),
        'cnews_display_options_callback',
        'news',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'cnews_add_meta_boxes');

function cnews_get_meta_display_options($post_id) {
    return array(
        'show_author' => get_post_meta($post_id, 'cnews_show_author', true) === '1',
        'show_date' => get_post_meta($post_id, 'cnews_show_date', true) === '1',
        'show_site' => get_post_meta($post_id, 'cnews_show_site', true) === '1'
    );
}

// Helper function to truncate text
function cnews_truncate_text($text, $length = 20) {
    if (mb_strlen($text) > $length) {
        return mb_substr($text, 0, $length) . '...';
    }
    return $text;
}

// Modified Shortcode Function for admin site
function cnews_display_news_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => -1,
        'site_id' => get_current_blog_id(),
    ), $atts, 'display_news');
    
    $site_id = intval($atts['site_id']);
    $limit = intval($atts['limit']);
    
    $switch_site = is_multisite() && $site_id !== get_current_blog_id();
    
    if ($switch_site) {
        switch_to_blog($site_id);
    }
    
    $query_args = array(
        'post_type' => 'news', 
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($query_args);
    $output = '';
    
    if ($query->have_posts()) {
        $output .= '<div class="cnews-news-list">';
        
        $output .= '<ul>';
        $items = array();
        
        while ($query->have_posts()) {
            $query->the_post();
            $title = cnews_truncate_text(get_the_title(), 20);
            $item = '<li>';
            $item .= '<a href="' . get_permalink() . '">' . esc_html($title) . '</a>';
            $item .= '</li>';
            $items[] = $item;
        }
        
        $output .= implode('', $items);
        $output .= '</ul>';
        $output .= '</div>';
    } else {
        $output .= '<div class="cnews-news-list">';
        $output .= '<p>No news found.</p>';
        $output .= '</div>';
    }
    
    wp_reset_postdata();
    
    if ($switch_site) {
        restore_current_blog();
    }
    
    $output .= '<style>
        .cnews-news-list ul li::before {
            content: "";
            display: inline-block;
            width: 0;
            height: 0;
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
            border-left: 5px solid #6c757d;
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.4s ease;
            z-index: 1;
        }
        .cnews-news-list ul {
            list-style: none;
            padding-left: 20px;
        }
        .cnews-news-list ul li {
            position: relative;
            margin-bottom: 10px;
        }
        .cnews-news-list ul li a {
            color: #333;
            text-decoration: none;
        }
        .cnews-news-list ul li a:hover {
            text-decoration: underline;
        }
    </style>';
    return $output;
}
add_shortcode('display_news', 'cnews_display_news_shortcode');

// New Shortcode for displaying news by category
function cnews_display_news_by_category_shortcode($atts) {
    $atts = shortcode_atts(array(
        'limit' => -1,
        'site_id' => get_current_blog_id(),
        'category' => '', // Comma-separated category slugs
    ), $atts, 'display_news_by_category');
    
    $site_id = intval($atts['site_id']);
    $limit = intval($atts['limit']);
    $categories = !empty($atts['category']) ? array_map('trim', explode(',', $atts['category'])) : array();
    
    if (empty($categories)) {
        return '<div class="cnews-news-list"><p>Please specify at least one category slug.</p></div>';
    }
    
    $switch_site = is_multisite() && $site_id !== get_current_blog_id();
    
    if ($switch_site) {
        switch_to_blog($site_id);
    }
    
    $query_args = array(
        'post_type' => 'news',
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC',
        'tax_query' => array(
            array(
                'taxonomy' => 'news_category',
                'field' => 'slug',
                'terms' => $categories,
                'operator' => 'IN',
            ),
        ),
    );
    
    $query = new WP_Query($query_args);
    $output = '';
    
    if ($query->have_posts()) {
        $output .= '<div class="cnews-news-list">';
        
        $output .= '<ul>';
        $items = array();
        
        while ($query->have_posts()) {
            $query->the_post();
            $title = cnews_truncate_text(get_the_title(), 20);
            $item = '<li>';
            $item .= '<a href="' . get_permalink() . '">' . esc_html($title) . '</a>';
            $item .= '</li>';
            $items[] = $item;
        }
        
        $output .= implode('', $items);
        $output .= '</ul>';
        $output .= '</div>';
    } else {
        $output .= '<div class="cnews-news-list">';
        $output .= '<p>No news found in the specified categories.</p></div>';
    }
    
    wp_reset_postdata();
    
    if ($switch_site) {
        restore_current_blog();
    }
    
    $output .= '<style>
        .cnews-news-list ul li::before {
            content: "";
            display: inline-block;
            width: 0;
            height: 0;
            border-top: 5px solid transparent;
            border-bottom: 5px solid transparent;
            border-left: 5px solid #6c757d;
            position: absolute;
            left: 5px;
            top: 50%;
            transform: translateY(-50%);
            transition: all 0.4s ease;
            z-index: 1;
        }
        .cnews-news-list ul {
            list-style: none;
            padding-left: 20px;
        }
        .cnews-news-list ul li {
            position: relative;
            margin-bottom: 10px;
        }
        .cnews-news-list ul li a {
            color: #333;
            text-decoration: none;
        }
        .cnews-news-list ul li a:hover {
            text-decoration: underline;
        }
    </style>';
    return $output;
}
add_shortcode('display_news_by_category', 'cnews_display_news_by_category_shortcode');

// Network shortcode for displaying news from specified site ID
function cnews_display_network_site_news_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
        'site_id' => 0,
    ), $atts, 'display_site_news');
    
    $limit = intval($atts['limit']);
    $site_id = intval($atts['site_id']);
    
    if ($site_id <= 0) {
        return '<p>Please specify a valid site_id parameter.</p>';
    }
    
    ob_start();
    echo '<div class="news-list">';
    
    switch_to_blog($site_id);
    
    $query_args = array(
        'post_type' => 'news', 
        'posts_per_page' => $limit,
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($query_args);
    
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            
            echo '<div class="news-item">';
            
            if (has_post_thumbnail()) {
                echo '<div class="news-image">';
                the_post_thumbnail('thumbnail');
                echo '</div>';
            }
            
            echo '<div class="news-content">';
            
            $title = cnews_truncate_text(get_the_title(), 20);
            echo '<h3 class="news-title"><a href="' . get_permalink() . '">' . $title . '</a></h3>';
            
            $meta_info = array();
            if (get_post_meta($post_id, 'cnews_show_author', true)) {
                $meta_info[] = 'Author: ' . get_the_author();
            }
            if (get_post_meta($post_id, 'cnews_show_date', true)) {
                $meta_info[] = 'Date: ' . get_the_date();
            }
            if (get_post_meta($post_id, 'cnews_show_site', true)) {
                $meta_info[] = 'Dept: ' . cnews_get_custom_site_name();
            }
            
            if (!empty($meta_info)) {
                echo '<div style="font-size: 9px;" class="news-meta">' . implode(' | ', $meta_info) . '</div>';
            }
            
            $excerpt = get_the_excerpt();
            $excerpt = cnews_truncate_text($excerpt, 100);
            echo '<div class="news-excerpt">' . $excerpt . '</div>';
            
            echo '<a href="' . get_permalink() . '" class="cnews-read-more">Read More</a>';
            echo '</div>';
            echo '</div>';
        }
    } else {
        echo '<p>No news found for this site.</p>';
    }
    
    wp_reset_postdata();
    restore_current_blog();
    
    echo '</div>';
    
    echo '<style>
        .news-list {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        .news-item {
            flex: 1;
            min-width: 300px;
            max-width: 380px;
            margin-bottom: 20px;
            background: #ffffff;
            border-radius: 0;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            flex-direction: column;
            position: relative;
        }
        .news-image img {
            width: 100%;
            height: auto;
            display: block;
        }
        .news-content {
            padding: 15px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        .news-title {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        .news-title a {
            color: #333;
            text-decoration: none;
        }
        .news-excerpt {
            color: #555;
            margin-bottom: 15px;
            font-size: 14px;
            flex-grow: 1;
        }
        .news-meta {
            font-size: 0.85em;
            color: #777;
            margin-bottom: 10px;
        }
        .cnews-read-more {
            display: inline-block;
            padding: 5px 10px;
            background: #993333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            align-self: flex-start;
        }
        .cnews-read-more:hover {
            background: rgb(95, 32, 32);
        }
        @media (max-width: 768px) {
            .news-list {
                flex-direction: column;
            }
            .news-item {
                max-width: 100%;
            }
        }
    </style>';
    
    return ob_get_clean();
}
add_shortcode('display_site_news', 'cnews_display_network_site_news_shortcode');

// Network shortcode for displaying news from all sites
function cnews_display_network_news_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
        'sites' => '',
        'homepage_requests_only' => 'no',
    ), $atts, 'display_network_news');
    
    $limit = intval($atts['limit']);
    $sites_list = !empty($atts['sites']) ? array_map('intval', explode(',', $atts['sites'])) : array();
    $homepage_requests_only = ($atts['homepage_requests_only'] === 'yes');
    
    $sites = get_sites(array(
        'site__in' => !empty($sites_list) ? $sites_list : array(),
    ));
    
    ob_start();
    echo '<div class="cnews-network-news">';
    
    if (!empty($sites)) {
        $all_news = array();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $query_args = array(
                'post_type' => 'news',
                'posts_per_page' => $limit,
                'orderby' => 'date',
                'order' => 'DESC'
            );
            
            if ($homepage_requests_only) {
                $query_args['meta_query'] = array(
                    array(
                        'key' => 'cnews_homepage_request',
                        'value' => '1',
                        'compare' => '='
                    )
                );
            }
            
            $query = new WP_Query($query_args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    
                    $all_news[] = array(
                        'ID' => $post_id,
                        'title' => get_the_title(),
                        'permalink' => get_permalink(),
                        'date' => get_the_date('Y-m-d H:i:s'),
                        'excerpt' => get_the_excerpt(),
                        'author' => get_the_author(),
                        'site_name' => get_bloginfo('name'),
                        'site_id' => $site->blog_id,
                        'show_author' => get_post_meta($post_id, 'cnews_show_author', true),
                        'show_date' => get_post_meta($post_id, 'cnews_show_date', true),
                        'show_site' => get_post_meta($post_id, 'cnews_show_site', true),
                        'homepage_request' => get_post_meta($post_id, 'cnews_homepage_request', true),
                        'timestamp' => get_post_time('U', true),
                        'has_thumbnail' => has_post_thumbnail(),
                        'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                    );
                }
            }
            
            wp_reset_postdata();
            restore_current_blog();
        }
        
        usort($all_news, function($a, $b) {
            return $b['timestamp'] - $a['timestamp'];
        });
        
        $all_news = array_slice($all_news, 0, $limit);
        
        if (!empty($all_news)) {
            foreach ($all_news as $news) {
                echo '<div class="cnews-news-item">';
                
                if ($news['has_thumbnail']) {
                    echo '<div class="cnews-news-thumbnail">';
                    echo $news['thumbnail_html'];
                    echo '</div>';
                }
                
                $title = cnews_truncate_text($news['title'], 20);
                echo '<h3 class="cnews-news-title"><a href="' . esc_url($news['permalink']) . '">' . esc_html($title) . '</a></h3>';
                
                $meta_info = array();
                if ($news['show_author']) {
                    $meta_info[] = 'Author: ' . esc_html($news['author']);
                }
                if ($news['show_date']) {
                    $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $news['timestamp']);
                }
                if ($news['show_site']) {
                    $meta_info[] = 'Dept: ' . cnews_get_custom_site_name();
                }
                
                if (!empty($meta_info)) {
                    echo '<div style="font-size: 9px;" class="cnews-news-meta">' . implode(' | ', $meta_info) . '</div>';
                }
                
                $excerpt = cnews_truncate_text($news['excerpt'], 100);
                echo '<div class="cnews-news-excerpt">' . wp_kses_post($excerpt) . '</div>';
                echo '<a href="' . esc_url($news['permalink']) . '" class="cnews-read-more">Read More</a>';
                echo '</div>';
            }
        } else {
            echo '<p>No news found.</p>';
        }
    } else {
        echo '<p>No sites found.</p>';
    }
    
    echo '</div>';
    return ob_get_clean();
}
add_shortcode('display_network_news', 'cnews_display_network_news_shortcode');

function cnews_enqueue_styles() {
    wp_enqueue_style('cnews-styles', CNEWS_PLUGIN_URL . 'assets/css/style.css', array(), CNEWS_VERSION);
}
add_action('wp_enqueue_scripts', 'cnews_enqueue_styles');

function cnews_admin_styles() {
    wp_enqueue_style('dashicons');
}
add_action('admin_enqueue_scripts', 'cnews_admin_styles');

function cnews_admin_notice() {
    $screen = get_current_screen();
    if ($screen && $screen->post_type === 'news') {
        ?>
        <div class="notice notice-info">
            <p><strong>News Tips:</strong></p>
            <p>Use shortcode <code>[display_news]</code> to display news from this site.</p>
            <p>Use shortcode <code>[display_news_by_category category="slug1,slug2"]</code> to display news from specific categories.</p>
            <?php if (is_multisite()): ?>
                <p>For multisite networks:</p>
                <ul>
                    <li>Use <code>[display_homepage_news]</code> to show news only that are approved by admin network sites.</li>
                    <li>Use <code>[display_network_news]</code> to show news from all network sites.</li>
                    <li>Use <code>[display_site_news site_id="<?php echo get_current_blog_id(); ?>"]</code> to show news from this specific site like a post (With the title description and Thumbnail).</li>
                </ul>
                <p>Check "Request Homepage Display" if you want this news to be eligible for display on the network homepage.</p>
            <?php endif; ?>
            <p>Control which metadata shows (author, date, site) using the Display Options in the sidebar.</p>
        </div>
        <?php
    }
}
add_action('admin_notices', 'cnews_admin_notice');

function cnews_add_network_admin_menu() {
    if (is_multisite() && is_super_admin()) {
        add_menu_page(
            __('Network News', 'custom-news-manager'),
            __('Network News', 'custom-news-manager'),
            'manage_network',
            'network-news',
            'cnews_network_news_page',
            'dashicons-format-aside',
            25
        );
    }
}
add_action('network_admin_menu', 'cnews_add_network_admin_menu');

function cnews_activate() {
    cnews_register_news_post_type();
    cnews_register_news_category_taxonomy(); // Register taxonomy on activation
    cnews_create_folders();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'cnews_activate');

function cnews_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cnews_deactivate');

function cnews_register_approval_meta() {
    register_post_meta('news', 'cnews_homepage_approved', array(
        'type' => 'boolean',
        'description' => 'Approved for display on network homepage',
        'single' => true,
        'default' => false,
        'show_in_rest' => true,
    ));
}
add_action('init', 'cnews_register_approval_meta');

function cnews_network_news_page() {
    if (!is_super_admin()) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    if (isset($_POST['cnews_approve_selected']) && isset($_POST['cnews_news']) && is_array($_POST['cnews_news'])) {
        $approved_count = 0;
        
        foreach ($_POST['cnews_news'] as $news) {
            list($site_id, $post_id) = explode('_', $news);
            $site_id = intval($site_id);
            $post_id = intval($post_id);
            
            if ($site_id > 0 && $post_id > 0) {
                switch_to_blog($site_id);
                update_post_meta($post_id, 'cnews_homepage_approved', '1');
                restore_current_blog();
                $approved_count++;
            }
        }
        
        if ($approved_count > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . 
                sprintf(_n('%d news approved for homepage display.', '%d news approved for homepage display.', $approved_count, 'custom-news-manager'), $approved_count) . 
                '</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Network News Overview', 'custom-news-manager'); ?></h1>
        
        <h2><?php _e('Homepage News Requests', 'custom-news-manager'); ?></h2>
        <p><?php _e('These news have been requested for display on the network homepage.', 'custom-news-manager'); ?></p>
        
        <?php
        $sites = get_sites();
        $homepage_requests = array();
        
        foreach ($sites as $site) {
            switch_to_blog($site->blog_id);
            
            $query_args = array(
                'post_type' => 'news',
                'posts_per_page' => -1,
                'meta_query' => array(
                    array(
                        'key' => 'cnews_homepage_request',
                        'value' => '1',
                        'compare' => '='
                    )
                )
            );
            
            $query = new WP_Query($query_args);
            
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $post_id = get_the_ID();
                    $homepage_approved = get_post_meta($post_id, 'cnews_homepage_approved', true);
                    
                    $homepage_requests[] = array(
                        'id' => $post_id,
                        'title' => get_the_title(),
                        'site_id' => $site->blog_id,
                        'site_name' => get_bloginfo('name'),
                        'date' => get_the_date(),
                        'permalink' => get_permalink(),
                        'author' => get_the_author(),
                        'approved' => $homepage_approved
                    );
                }
            }
            
            wp_reset_postdata();
            restore_current_blog();
        }
        
        if (!empty($homepage_requests)) {
            ?>
            <form method="post" action="">
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="cnews-select-all"></th>
                            <th><?php _e('Title', 'custom-news-manager'); ?></th>
                            <th><?php _e('Site', 'custom-news-manager'); ?></th>
                            <th><?php _e('Author', 'custom-news-manager'); ?></th>
                            <th><?php _e('Date', 'custom-news-manager'); ?></th>
                            <th><?php _e('Status', 'custom-news-manager'); ?></th>
                            <th><?php _e('Actions', 'custom-news-manager'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($homepage_requests as $request): ?>
                        <tr>
                            <td>
                                <?php if ($request['approved'] != '1'): ?>
                                <input type="checkbox" name="cnews_news[]" value="<?php echo $request['site_id'] . '_' . $request['id']; ?>">
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html($request['title']); ?></td>
                            <td><?php echo esc_html($request['site_name']); ?></td>
                            <td><?php echo esc_html($request['author']); ?></td>
                            <td><?php echo esc_html($request['date']); ?></td>
                            <td>
                                <?php if ($request['approved'] == '1'): ?>
                                <span style="color:green;"><span class="dashicons dashicons-yes"></span> <?php _e('Approved', 'custom-news-manager'); ?></span>
                                <?php else: ?>
                                <span style="color:orange;"><span class="dashicons dashicons-clock"></span> <?php _e('Pending Approval', 'custom-news-manager'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url($request['permalink']); ?>" target="_blank"><?php _e('View', 'custom-news-manager'); ?></a>
                                <?php if ($request['approved'] != '1'): ?>
                                | <a href="<?php echo get_admin_url($request['site_id'], 'post.php?post=' . $request['id'] . '&action=edit'); ?>" target="_blank"><?php _e('Edit', 'custom-news-manager'); ?></a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <input type="submit" name="cnews_approve_selected" id="cnews_approve_selected" class="button button-primary" value="<?php _e('Approve Selected', 'custom-news-manager'); ?>">
                    </div>
                </div>
            </form>
            
            <script>
            jQuery(document).ready(function($) {
                $('#cnews-select-all').on('change', function() {
                    $('input[name="cnews_news[]"]').prop('checked', $(this).prop('checked'));
                });
            });
            </script>
            
            <h3><?php _e('Approved Homepage News', 'custom-news-manager'); ?></h3>
            <p><?php _e('These news are currently approved for display on the network homepage.', 'custom-news-manager'); ?></p>
            
            <?php
            $approved_count = 0;
            foreach ($homepage_requests as $request) {
                if ($request['approved'] == '1') {
                    $approved_count++;
                }
            }
            
            if ($approved_count > 0) {
                ?>
                <p><?php echo sprintf(_n('There is %d approved news.', 'There are %d approved news.', $approved_count, 'custom-news-manager'), $approved_count); ?></p>
                <?php
            } else {
                ?>
                <p><?php _e('No news have been approved yet.', 'custom-news-manager'); ?></p>
                <?php
            }
            ?>
            
            <h3><?php _e('Homepage Display Shortcode', 'custom-news-manager'); ?></h3>
            <p><?php _e('Use this shortcode on your network homepage to display the approved news:', 'custom-news-manager'); ?></p>
            <code>[display_homepage_news limit="3"]</code>
            <?php
        } else {
            echo '<p>' . __('No homepage news requests found.', 'custom-news-manager') . '</p>';
        }
        ?>
    </div>
    <?php
}

function cnews_display_options_callback($post) {
    wp_nonce_field('cnews_save_meta_box_data', 'cnews_meta_box_nonce');
    
    $show_author = get_post_meta($post->ID, 'cnews_show_author', true) ? '1' : '0';
    $show_date = get_post_meta($post->ID, 'cnews_show_date', true) ? '1' : '0';
    $show_site = get_post_meta($post->ID, 'cnews_show_site', true) ? '1' : '0';
    $homepage_request = get_post_meta($post->ID, 'cnews_homepage_request', true) ? '1' : '0';
    $homepage_approved = get_post_meta($post->ID, 'cnews_homepage_approved', true) ? '1' : '0';
    
    $network_post_id = get_post_meta($post->ID, 'cnews_network_post_id', true);
    $network_site_id = get_post_meta($post->ID, 'cnews_network_site_id', true);
    $original_site_name = get_post_meta($post->ID, 'cnews_original_site_name', true);
    $original_author = get_post_meta($post->ID, 'cnews_original_author', true);
    
    $is_main_site_admin = false;
    if (is_multisite()) {
        $current_site_id = get_current_blog_id();
        $is_main_site = ($current_site_id == 1);
        if ($is_main_site) {
            $is_main_site_admin = current_user_can('manage_options');
        }
    }
    
    if ($is_main_site && !empty($network_post_id) && !empty($network_site_id)) {
        switch_to_blog($network_site_id);
        $expected_site_name = get_bloginfo('name');
        restore_current_blog();

        if ($original_site_name !== $expected_site_name) {
            error_log("CNEWS DEBUG: Site name mismatch for post {$post->ID}. Stored: {$original_site_name}, Expected: {$expected_site_name}. Using expected name.");
            $original_site_name = $expected_site_name;
            update_post_meta($post->ID, 'cnews_original_site_name', $original_site_name);
        }

        echo '<div class="cnews-original-post-info" style="background-color: #f8f8f8; padding: 10px; margin-bottom: 15px; border-left: 4px solid #0073aa;">';
        echo '<h4 style="margin-top: 0;">' . __('Original News Information', 'custom-news-manager') . '</h4>';
        if (!empty($original_site_name)) {
            echo '<p><strong>' . __('Original Site:', 'custom-news-manager') . '</strong> ' . esc_html($original_site_name) . '</p>';
        }
        if (!empty($original_author)) {
            echo '<p><strong>' . __('Original Author:', 'custom-news-manager') . '</strong> ' . esc_html($original_author) . '</p>';
        }
        echo '<p><strong>' . __('Network Site ID:', 'custom-news-manager') . '</strong> ' . intval($network_site_id) . '</p>';
        echo '<p><strong>' . __('Original Post ID:', 'custom-news-manager') . '</strong> ' . intval($network_post_id) . '</p>';
        echo '<p><a href="' . esc_url(get_admin_url($network_site_id, 'post.php?post=' . $network_post_id . '&action=edit')) . '" target="_blank" class="button button-secondary">';
        echo __('View Original Post', 'custom-news-manager');
        echo '</a></p>';
        echo '</div>';
    }
    
    ?>
    <p>
        <label>
            <input type="checkbox" name="cnews_show_author" value="1" <?php checked($show_author, '1'); ?> />
            <?php _e('Show Author Name', 'custom-news-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="cnews_show_date" value="1" <?php checked($show_date, '1'); ?> />
            <?php _e('Show Publication Date', 'custom-news-manager'); ?>
        </label>
    </p>
    <p>
        <label>
            <input type="checkbox" name="cnews_show_site" value="1" <?php checked($show_site, '1'); ?> />
            <?php _e('Show Site Name', 'custom-news-manager'); ?>
        </label>
    </p>
    <?php if (is_multisite()): ?>
    <hr>
    <p>
        <label>
            <input type="checkbox" name="cnews_homepage_request" value="1" <?php checked($homepage_request, '1'); ?> 
                   id="cnews_homepage_request_checkbox" />
            <?php _e('Request Homepage Display', 'custom-news-manager'); ?>
        </label>
        <br>
        <small><?php _e('Request this news to be displayed on the network homepage', 'custom-news-manager'); ?></small>
    </p>
    
    <?php if ($is_main_site_admin): ?>
    <p>
        <label>
            <input type="checkbox" name="cnews_homepage_approved" value="1" <?php checked($homepage_approved, '1'); ?> />
            <?php _e('Approve for Homepage Display', 'custom-news-manager'); ?>
        </label>
        <br>
        <small><?php _e('Approve this news for display on the network homepage (main site admin only)', 'custom-news-manager'); ?></small>
    </p>
    <?php elseif ($homepage_approved == '1'): ?>
    <p>
        <span class="dashicons dashicons-yes" style="color:green;"></span>
        <?php _e('Approved for homepage display', 'custom-news-manager'); ?>
    </p>
    <?php elseif ($homepage_request == '1'): ?>
    <p>
        <span class="dashicons dashicons-clock" style="color:orange;"></span>
        <?php _e('Pending approval for homepage display', 'custom-news-manager'); ?>
    </p>
    <?php endif; ?>
    
    <?php endif; 
    if (is_multisite() && !$is_main_site_admin): ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var initialRequestState = $('#cnews_homepage_request_checkbox').is(':checked');
        $('form#post').on('submit', function() {
            if ($('#cnews_homepage_request_checkbox').is(':checked') && !initialRequestState) {
                localStorage.setItem('cnews_new_homepage_request', 'true');
            }
        });
    });
    </script>
    <?php endif;
}

function cnews_handle_homepage_request_removal($post_id, $post, $update) {
    if ($post->post_type !== 'news' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if (!is_multisite() || get_current_blog_id() == 1) {
        return;
    }
    $old_homepage_request = get_post_meta($post_id, 'cnews_homepage_request', true);
    $new_homepage_request = get_post_meta($post_id, 'cnews_homepage_request', true);
    if ($new_homepage_request === '0' && $old_homepage_request === '1') {
        $current_site_id = get_current_blog_id();
        error_log("CNEWS DEBUG: Post-save check - Homepage request removed for post $post_id on site $current_site_id");
        switch_to_blog(1);
        $existing_posts = get_posts([
            'post_type'   => 'news',
            'meta_query'  => [
                [
                    'key'     => 'cnews_network_post_id',
                    'value'   => $post_id,
                    'compare' => '='
                ],
                [
                    'key'     => 'cnews_network_site_id',
                    'value'   => $current_site_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status'    => 'any'
        ]);
        if (!empty($existing_posts)) {
            $main_post_id = $existing_posts[0]->ID;
            wp_delete_post($main_post_id, true);
            error_log("CNEWS DEBUG: Post-save deletion - Removed post $main_post_id from main site.");
        } else {
            error_log("CNEWS DEBUG: Post-save check - No synced post found on main site for $post_id.");
        }
        restore_current_blog();
    }
}
add_action('save_post', 'cnews_handle_homepage_request_removal', 20, 3);

function cnews_save_meta_box_data($post_id) {
    if (!isset($_POST['cnews_meta_box_nonce'])) {
        return;
    }
    if (!wp_verify_nonce($_POST['cnews_meta_box_nonce'], 'cnews_save_meta_box_data')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    update_post_meta($post_id, 'cnews_show_author', isset($_POST['cnews_show_author']) ? '1' : '0');
    update_post_meta($post_id, 'cnews_show_date', isset($_POST['cnews_show_date']) ? '1' : '0');
    update_post_meta($post_id, 'cnews_show_site', isset($_POST['cnews_show_site']) ? '1' : '0');
    
    if (is_multisite()) {
        $old_homepage_request = get_post_meta($post_id, 'cnews_homepage_request', true);
        $new_homepage_request = isset($_POST['cnews_homepage_request']) ? '1' : '0';
        
        update_post_meta($post_id, 'cnews_homepage_request', $new_homepage_request);
        
        $current_site_id = get_current_blog_id();
        $is_main_site = ($current_site_id == 1);
        
        error_log("CNEWS DEBUG: Saving post $post_id on site $current_site_id. Old request: $old_homepage_request, New request: $new_homepage_request");
        
        if (!$is_main_site) {
            if ($new_homepage_request === '1' && $old_homepage_request !== '1') {
                error_log("CNEWS DEBUG: New homepage request for post $post_id on site $current_site_id");
                $site_name = get_bloginfo('name');
                $post_title = get_the_title($post_id);
                $post_link = get_permalink($post_id);
                $author_id = get_post_field('post_author', $post_id);
                $author_name = get_the_author_meta('display_name', $author_id);
                
                $subject = sprintf(__('[%s] New Homepage News Request', 'custom-news-manager'), $site_name);
                $message = sprintf(
                    __('A new news has been requested for the network homepage:\n\nTitle: %s\nSite: %s\nAuthor: %s\nLink: %s\n\nPlease review this request in the Network News section.', 'custom-news-manager'),
                    $post_title,
                    $site_name,
                    $author_name,
                    $post_link
                );
                
                switch_to_blog(1);
                $admin_email = get_option('admin_email');
                restore_current_blog();
                
                wp_mail($admin_email, $subject, $message);
            }
            
            if ($new_homepage_request === '0' && $old_homepage_request === '1') {
                error_log("CNEWS DEBUG: Homepage request removed for post $post_id on site $current_site_id. Attempting to delete from main site.");
                
                switch_to_blog(1);
                
                $existing_posts = get_posts([
                    'post_type'   => 'news',
                    'meta_query'  => [
                        [
                            'key'     => 'cnews_network_post_id',
                            'value'   => $post_id,
                            'compare' => '='
                        ],
                        [
                            'key'     => 'cnews_network_site_id',
                            'value'   => $current_site_id,
                            'compare' => '='
                        ]
                    ],
                    'posts_per_page' => 1,
                    'post_status'    => 'any'
                ]);
                
                if (!empty($existing_posts)) {
                    $main_post_id = $existing_posts[0]->ID;
                    error_log("CNEWS DEBUG: Found synced post $main_post_id on main site. Deleting...");
                    
                    $result = wp_delete_post($main_post_id, true);
                    if ($result) {
                        error_log("CNEWS DEBUG: Successfully deleted post $main_post_id from main site.");
                    } else {
                        error_log("CNEWS DEBUG: Failed to delete post $main_post_id from main site.");
                    }
                } else {
                    error_log("CNEWS DEBUG: No synced post found on main site for post $post_id from site $current_site_id.");
                }
                
                restore_current_blog();
            }
        }
        
        $is_main_site_admin = $is_main_site && current_user_can('manage_options');
        if ($is_main_site_admin) {
            update_post_meta($post_id, 'cnews_homepage_approved', isset($_POST['cnews_homepage_approved']) ? '1' : '0');
        }
    }
}
add_action('save_post', 'cnews_save_meta_box_data');

add_shortcode('display_homepage_news', 'cnews_display_homepage_news_shortcode');

function cnews_sync_news_to_main($post_id, $post, $update) {
    if ($post->post_type !== 'news' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        $post_id = wp_get_post_parent_id($post_id);
    }
    $homepage_request = get_post_meta($post_id, 'cnews_homepage_request', true);
    if ($homepage_request != '1') {
        return;
    }
    if ($post->post_status !== 'publish') {
        return;
    }
    $currently_syncing = get_transient('cnews_syncing_' . $post_id);
    if ($currently_syncing) {
        return;
    }
    set_transient('cnews_syncing_' . $post_id, true, 30);
    $current_blog_id = get_current_blog_id();
    if ($current_blog_id == 1) {
        delete_transient('cnews_syncing_' . $post_id);
        return;
    }
    $original_site_name = cnews_get_custom_site_name();
    $all_meta = get_post_meta($post_id);
    $show_author = isset($all_meta['cnews_show_author'][0]) ? $all_meta['cnews_show_author'][0] : '0';
    $show_date = isset($all_meta['cnews_show_date'][0]) ? $all_meta['cnews_show_date'][0] : '0';
    $show_site = isset($all_meta['cnews_show_site'][0]) ? $all_meta['cnews_show_site'][0] : '0';
    switch_to_blog(1);
    $existing_posts = get_posts([
        'post_type'   => 'news',
        'meta_query'  => [
            [
                'key'   => 'cnews_network_post_id',
                'value' => $post_id,
                'compare' => '='
            ],
            [
                'key'   => 'cnews_network_site_id',
                'value' => $current_blog_id,
                'compare' => '='
            ]
        ],
        'posts_per_page' => 1
    ]);
    if (!empty($existing_posts)) {
        $main_post_id = $existing_posts[0]->ID;
        wp_update_post([
            'ID'           => $main_post_id,
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'pending'
        ]);
        update_post_meta($main_post_id, 'cnews_show_author', $show_author);
        update_post_meta($main_post_id, 'cnews_show_date', $show_date);
        update_post_meta($main_post_id, 'cnews_show_site', $show_site);
        update_post_meta($main_post_id, 'cnews_homepage_request', '1');
        $homepage_approved = isset($all_meta['cnews_homepage_approved'][0]) ? $all_meta['cnews_homepage_approved'][0] : '0';
        update_post_meta($main_post_id, 'cnews_homepage_approved', $homepage_approved);
        update_post_meta($main_post_id, 'cnews_original_site_name', $original_site_name);
        $author_id = $post->post_author;
        $author_name = get_the_author_meta('display_name', $author_id);
        update_post_meta($main_post_id, 'cnews_original_author', $author_name);
    } else {
        $main_post_id = wp_insert_post([
            'post_title'   => $post->post_title,
            'post_content' => $post->post_content,
            'post_excerpt' => $post->post_excerpt,
            'post_status'  => 'pending',
            'post_type'    => 'news',
            'post_author'  => 1,
        ]);
        if (!is_wp_error($main_post_id)) {
            update_post_meta($main_post_id, 'cnews_network_post_id', $post_id);
            update_post_meta($main_post_id, 'cnews_network_site_id', $current_blog_id);
            update_post_meta($main_post_id, 'cnews_show_author', $show_author);
            update_post_meta($main_post_id, 'cnews_show_date', $show_date);
            update_post_meta($main_post_id, 'cnews_show_site', $show_site);
            update_post_meta($main_post_id, 'cnews_homepage_request', '1');
            update_post_meta($main_post_id, 'cnews_original_site_name', $original_site_name);
            $author_id = $post->post_author;
            $author_name = get_the_author_meta('display_name', $author_id);
            update_post_meta($main_post_id, 'cnews_original_author', $author_name);
        }
    }
    if (has_post_thumbnail($post_id)) {
        $thumbnail_id = get_post_thumbnail_id($post_id);
        $attachment_path = get_attached_file($thumbnail_id);
        if ($attachment_path && file_exists($attachment_path)) {
            error_log("CNEWS DEBUG: Attempting to sync featured image for post $post_id from site $current_blog_id to main site.");
            $existing_attachment_id = get_post_meta($main_post_id, 'cnews_synced_thumbnail_id', true);
            if (!$existing_attachment_id) {
                $file_array = array(
                    'name' => basename($attachment_path),
                    'tmp_name' => $attachment_path,
                );
                require_once(ABSPATH . 'wp-admin/includes/file.php');
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                require_once(ABSPATH . 'wp-admin/includes/media.php');
                $attachment_id = media_handle_sideload($file_array, $main_post_id);
                if (!is_wp_error($attachment_id)) {
                    $result = set_post_thumbnail($main_post_id, $attachment_id);
                    if ($result) {
                        update_post_meta($main_post_id, 'cnews_synced_thumbnail_id', $attachment_id);
                        error_log("CNEWS DEBUG: Successfully synced featured image for post $main_post_id. New attachment ID: $attachment_id");
                    } else {
                        error_log("CNEWS DEBUG: Failed to set thumbnail for post $main_post_id with attachment ID $attachment_id");
                    }
                } else {
                    error_log("CNEWS DEBUG: Failed to sideload image for post $main_post_id: " . $attachment_id->get_error_message());
                }
            } else {
                $result = set_post_thumbnail($main_post_id, $existing_attachment_id);
                if ($result) {
                    error_log("CNEWS DEBUG: Re-attached existing image ID $existing_attachment_id to post $main_post_id");
                } else {
                    error_log("CNEWS DEBUG: Failed to re-attach existing image ID $existing_attachment_id to post $main_post_id");
                }
            }
        } else {
            error_log("CNEWS DEBUG: Featured image path not found or inaccessible for post $post_id: $attachment_path");
            $image_url = wp_get_attachment_url($thumbnail_id);
            if ($image_url) {
                $response = wp_remote_get($image_url);
                if (!is_wp_error($response) && wp_remote_retrieve_response_code($response) == 200) {
                    $file_array = array(
                        'name' => basename($attachment_path),
                        'tmp_name' => wp_tempnam(),
                    );
                    file_put_contents($file_array['tmp_name'], wp_remote_retrieve_body($response));
                    $attachment_id = media_handle_sideload($file_array, $main_post_id);
                    if (!is_wp_error($attachment_id)) {
                        $result = set_post_thumbnail($main_post_id, $attachment_id);
                        if ($result) {
                            update_post_meta($main_post_id, 'cnews_synced_thumbnail_id', $attachment_id);
                            error_log("CNEWS DEBUG: Successfully synced featured image via URL for post $main_post_id. New attachment ID: $attachment_id");
                        }
                    } else {
                        error_log("CNEWS DEBUG: Failed to sideload image via URL for post $main_post_id: " . $attachment_id->get_error_message());
                    }
                    @unlink($file_array['tmp_name']);
                } else {
                    error_log("CNEWS DEBUG: Failed to fetch image via URL for post $post_id: " . ($response->get_error_message() ?: 'HTTP error'));
                }
            }
        }
    }
    restore_current_blog();
    update_post_meta($post_id, 'cnews_sent_to_main', '1');
    delete_transient('cnews_syncing_' . $post_id);
}
add_action('save_post', 'cnews_sync_news_to_main', 10, 3);

function cnews_display_homepage_news_shortcode($atts) {
    if (!is_multisite()) {
        return '<p>This shortcode is designed for multisite networks only.</p>';
    }
    
    $atts = shortcode_atts(array(
        'limit' => 5,
    ), $atts, 'display_homepage_news');
    
    $limit = intval($atts['limit']);
    
    ob_start();
    echo '<div class="cnews-homepage-news">';
    
    $sites = get_sites();
    $approved_news = array();
    
    foreach ($sites as $site) {
        switch_to_blog($site->blog_id);
        
        $query_args = array(
            'post_type' => 'news',
            'posts_per_page' => $limit,
            'meta_query' => array(
                array(
                    'key' => 'cnews_homepage_approved',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $query = new WP_Query($query_args);
        
        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                
                $approved_news[] = array(
                    'ID' => $post_id,
                    'title' => get_the_title(),
                    'permalink' => get_permalink(),
                    'date' => get_the_date('Y-m-d H:i:s'),
                    'excerpt' => get_the_excerpt(),
                    'author' => get_the_author(),
                    'site_name' => get_bloginfo('name'),
                    'site_id' => $site->blog_id,
                    'show_author' => get_post_meta($post_id, 'cnews_show_author', true),
                    'show_date' => get_post_meta($post_id, 'cnews_show_date', true),
                    'show_site' => get_post_meta($post_id, 'cnews_show_site', true),
                    'timestamp' => get_post_time('U', true),
                    'has_thumbnail' => has_post_thumbnail(),
                    'thumbnail_html' => get_the_post_thumbnail(null, 'thumbnail')
                );
            }
        }
        
        wp_reset_postdata();
        restore_current_blog();
    }
    
    usort($approved_news, function($a, $b) {
        return $b['timestamp'] - $a['timestamp'];
    });
    
    $approved_news = array_slice($approved_news, 0, $limit);
    
    if (!empty($approved_news)) {
        echo '<div class="cnews-news-grid">';
        foreach ($approved_news as $news) {
            echo '<div class="cnews-news-card">';
            
            if ($news['has_thumbnail']) {
                echo '<div class="cnews-news-thumbnail">';
                echo $news['thumbnail_html'];
                echo '</div>';
            }
            
            echo '<h5 class="cnews-news-title"><a href="' . esc_url($news['permalink']) . '">' . esc_html($news['title']) . '</a></h5>';
            
            $meta_info = array();
            if ($news['show_author']) {
                $meta_info[] = 'Author: ' . esc_html($news['author']);
            }
            if ($news['show_date']) {
                $meta_info[] = 'Date: ' . date_i18n(get_option('date_format'), $news['timestamp']);
            }
            if ($news['show_site']) {
                $meta_info[] = 'Dept: ' . cnews_get_custom_site_name();
            }
            
            if (!empty($meta_info)) {
                echo '<div style="font-size: 9px;" class="cnews-news-meta">' . implode(' | ', $meta_info) . '</div>';
            }
            
            echo '<div class="cnews-news-excerpt">' . wp_kses_post(cnews_truncate_text($news['excerpt'], 100)) . '</div>';
            echo '<a href="' . esc_url($news['permalink']) . '" class="cnews-read-more">Read More</a>';
            echo '</div>';
        }
        echo '</div>';
    } else {
        echo '<p>No approved homepage news found.</p>';
    }
    
    echo '</div>';
    
    echo '<style>
        .cnews-news-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .cnews-news-card {
            background: #fff;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        .cnews-news-thumbnail img {
            width: 100%;
            height: auto;
            display: block;
        }
        .cnews-news-title {
            margin: 15px 15px 10px;
            font-size: 18px;
        }
        .cnews-news-title a {
            color: #333;
            text-decoration: none;
        }
        .cnews-news-meta {
            margin: 0 15px 10px;
            font-size: 9px;
            color: #666;
        }
        .cnews-news-excerpt {
            margin: 0 15px 15px;
            font-size: 15px;
            line-height: 1.5;
            flex-grow: 1;
        }
        .cnews-read-more {
            display: inline-block;
            margin: 0 15px 15px;
            padding: 8px 15px;
            background: #993333;
            color: #fff;
            text-decoration: none;
            border-radius: 3px;
            font-size: 14px;
            align-self: flex-start;
        }
        .cnews-read_more:hover {
            background: #7a2828;
        }
        @media (max-width: 768px) {
            .cnews-news-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>';
    
    return ob_get_clean();
}

function cnews_create_folders() {
    $css_dir = CNEWS_PLUGIN_DIR . 'assets/css';
    if (!file_exists($css_dir)) {
        wp_mkdir_p($css_dir);
    }
    
    $css_file = $css_dir . '/style.css';
    if (!file_exists($css_file)) {
        $default_css = "/* Custom News Manager Styles */\n\n.cnews-news-meta {\n    font-size: 9px;\n}\n";
        file_put_contents($css_file, $default_css);
    }
}

function cnews_sortable_news_columns($columns) {
    $columns['homepage_request'] = 'homepage_request';
    $columns['homepage_approved'] = 'homepage_approved';
    return $columns;
}
add_filter('manage_edit-news_sortable_columns', 'cnews_sortable_news_columns');

function cnews_news_request_orderby($query) {
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    $orderby = $query->get('orderby');
    
    if ('homepage_request' === $orderby) {
        $query->set('meta_key', 'cnews_homepage_request');
        $query->set('orderby', 'meta_value_num');
    }
    
    if ('homepage_approved' === $orderby) {
        $query->set('meta_key', 'cnews_homepage_approved');
        $query->set('orderby', 'meta_value_num');
    }
}
add_action('pre_get_posts', 'cnews_news_request_orderby');

function cnews_filter_news_content($content) {
    global $post;
    
    if (!is_singular('news') || !is_main_query()) {
        return $content;
    }
    
    $is_main_site = (is_multisite() && get_current_blog_id() == 1);
    $network_post_id = get_post_meta($post->ID, 'cnews_network_post_id', true);
    $network_site_id = get_post_meta($post->ID, 'cnews_network_site_id', true);
    $is_synced_post = $is_main_site && !empty($network_post_id) && !empty($network_site_id);
    
    $show_author = get_post_meta($post->ID, 'cnews_show_author', true);
    $show_date = get_post_meta($post->ID, 'cnews_show_date', true);
    $show_site = get_post_meta($post->ID, 'cnews_show_site', true);
    
    if ($is_synced_post) {
        $site_name = get_post_meta($post->ID, 'cnews_original_site_name', true);
        if (empty($site_name)) {
            $site_name = cnews_get_custom_site_name($network_site_id);
        }
    } else {
        $site_name = cnews_get_custom_site_name(get_current_blog_id());
    }
    
    $filtered_content = '<div class="cnews-single-news">' . $content . '</div>';
    
    $css = '<style>
        .cnews-single-news .entry-meta,
        .cnews-single-news .post-meta,
        .cnews-single-news .entry-header .posted-on,
        .cnews-single-news .entry-header .byline {
            display: none !important;
        }
    </style>';
    
    $meta_html = '';
    $meta_items = array();
    
    if ($show_author == '1') {
        $meta_items[] = 'Author: ' . get_the_author();
    }
    
    if ($show_date == '1') {
        $meta_items[] = 'Date: ' . get_the_date();
    }
    
    if ($show_site == '1' && is_multisite()) {
        $meta_items[] = 'Dept: ' . esc_html($site_name);
    }
    
    if (!empty($meta_items)) {
        $meta_html = '<div style="font-size: 9px;" class="cnews-news-meta">' . implode(' | ', $meta_items) . '</div>';
    }
    
    return $css . $meta_html . $filtered_content;
}
add_filter('the_content', 'cnews_filter_news_content');

function cnews_hide_news_meta($content) {
    if (is_singular('news')) {
        $style = '<style>
            .entry-meta, 
            .post-meta, 
            .entry-footer,
            .posted-on,
            .byline,
            .post-author,
            .post-date { 
                display: none !important; 
            }
        </style>';
        
        $pattern = '/By\s+\w+\s+\|\s+[A-Za-z]+\s+\d+,\s+\d{4}\s+\|/';
        $content = preg_replace($pattern, '', $content);
        
        $pattern2 = '/Author:\s+\w+\s+\|\s+Dept:.+/';
        $content = preg_replace($pattern2, '', $content);
        
        return $style . $content;
    }
    return $content;
}
add_filter('the_content', 'cnews_hide_news_meta', 1);

function cnews_add_settings_menu() {
    add_submenu_page(
        'edit.php?post_type=news',
        __('News Settings', 'custom-news-manager'),
        __('Settings', 'custom-news-manager'),
        'manage_options',
        'news-settings',
        'cnews_settings_page_callback'
    );
}
add_action('admin_menu', 'cnews_add_settings_menu');

function cnews_settings_page_callback() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['cnews_settings_submit']) && check_admin_referer('cnews_settings_nonce')) {
        $site_name = isset($_POST['cnews_custom_site_name']) ? 
                    sanitize_text_field($_POST['cnews_custom_site_name']) : 
                    get_bloginfo('name');
        update_option('cnews_custom_site_name', $site_name);
        
        $default_limit = isset($_POST['cnews_default_limit']) ? 
                        intval($_POST['cnews_default_limit']) : 
                        5;
        update_option('cnews_default_limit', $default_limit);
        
        $custom_css = isset($_POST['cnews_custom_css']) ? 
                     wp_strip_all_tags($_POST['cnews_custom_css']) : 
                     '';
        update_option('cnews_custom_css', $custom_css);
        
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Settings saved successfully!', 'custom-news-manager') . '</p></div>';
    }
    
    $current_site_name = get_option('cnews_custom_site_name', get_bloginfo('name'));
    $default_limit = get_option('cnews_default_limit', 5);
    $custom_css = get_option('cnews_custom_css', '');
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('News Settings', 'custom-news-manager'); ?></h1>
        
        <form method="post" action="">
            <?php wp_nonce_field('cnews_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Custom Site Name', 'custom-news-manager'); ?></th>
                    <td>
                        <input type="text" name="cnews_custom_site_name" value="<?php echo esc_attr($current_site_name); ?>" class="regular-text">
                        <p class="description">
                            <?php echo esc_html__('This name will be displayed when an author checks "Show Site Name" for a news.', 'custom-news-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Number of News', 'custom-news-manager'); ?></th>
                    <td>
                        <input type="number" name="cnews_default_limit" value="<?php echo esc_attr($default_limit); ?>" min="1" max="20">
                        <p class="description">
                            <?php echo esc_html__('Default number of news to display when using shortcodes.', 'custom-news-manager'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('Custom CSS', 'custom-news-manager'); ?></th>
                    <td>
                        <textarea name="cnews_custom_css" rows="10" cols="50" class="large-text code"><?php echo esc_textarea($custom_css); ?></textarea>
                        <p class="description">
                            <?php echo esc_html__('Add custom CSS to style the [display_news] shortcode output.', 'custom-news-manager'); ?>
                            <br>
                            <?php echo esc_html__('Example: .cnews-news-list { background: #f5f5f5; padding: 15px; }', 'custom-news-manager'); ?>
                        </p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="cnews_settings_submit" class="button button-primary" value="<?php echo esc_attr__('Save Settings', 'custom-news-manager'); ?>">
            </p>
        </form>
    </div>
    <?php
}

function cnews_get_custom_site_name($site_id = null) {
    if ($site_id && is_multisite()) {
        switch_to_blog($site_id);
        $site_name = get_option('cnews_custom_site_name', get_bloginfo('name'));
        restore_current_blog();
        return $site_name;
    }
    return get_option('cnews_custom_site_name', get_bloginfo('name'));
}

function cnews_display_custom_site_name($site_name) {
    if (is_main_site() && get_option('cnews_custom_site_name')) {
        return get_option('cnews_custom_site_name');
    }
    return $site_name;
}
add_filter('bloginfo', 'cnews_display_custom_site_name', 10, 1);

function cnews_output_custom_css() {
    $custom_css = get_option('cnews_custom_css');
    if (!empty($custom_css)) {
        echo '<style type="text/css">' . "\n";
        echo esc_html($custom_css) . "\n";
        echo '</style>' . "\n";
    }
}
add_action('wp_head', 'cnews_output_custom_css');

function cnews_add_news_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['site_name'] = __('Site Name', 'custom-news-manager');
            $new_columns['shortcode'] = __('Shortcode', 'custom-news-manager');
            if (is_multisite()) {
                $new_columns['homepage_request'] = __('Homepage Request', 'custom-news-manager');
                if (is_super_admin()) {
                    $new_columns['homepage_approved'] = __('Approval Status', 'custom-news-manager');
                }
            }
        }
    }
    return $new_columns;
}
add_filter('manage_news_posts_columns', 'cnews_add_news_columns');

function cnews_display_shortcode_column($column, $post_id) {
    $is_main_site = is_multisite() && get_current_blog_id() == 1;
    $network_site_id = get_post_meta($post_id, 'cnews_network_post_id', true);
    $is_synced_post = $is_main_site && !empty($network_site_id);

    if ($column === 'site_name') {
        if ($is_synced_post) {
            $site_name = get_post_meta($post_id, 'cnews_original_site_name', true);
            if (empty($site_name)) {
                $site_name = cnews_get_custom_site_name($network_site_id);
            }
            echo esc_html($site_name);
        } else {
            echo esc_html(cnews_get_custom_site_name());
        }
    }

    if ($column === 'shortcode') {
        echo '<code>[display_news]</code>';
        if (is_multisite()) {
            $site_id = $is_synced_post ? $network_site_id : get_current_blog_id();
            echo '<br><code>[display_site_news site_id="' . esc_attr($site_id) . '"]</code>';
        }
    }

    if ($column === 'homepage_request' && is_multisite()) {
        $homepage_request = get_post_meta($post_id, 'cnews_homepage_request', true);
        if ($homepage_request == '1') {
            echo '<span style="color:green;">' . __('Requested', 'custom-news-manager') . '</span>';
        } else {
            echo '—';
        }
    }

    if ($column === 'homepage_approved' && is_multisite() && is_super_admin()) {
        $homepage_approved = get_post_meta($post_id, 'cnews_homepage_approved', true);
        if ($homepage_approved == '1') {
            echo '<span style="color:green;">' . __('Approved', 'custom-news-manager') . '</span>';
        } else {
            echo '<span style="color:orange;">' . __('Pending', 'custom-news-manager') . '</span>';
        }
    }
}
add_action('manage_news_posts_custom_column', 'cnews_display_shortcode_column', 10, 2);
?>
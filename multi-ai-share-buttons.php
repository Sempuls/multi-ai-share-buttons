<?php
/**
 * Plugin Name: Multi AI Share Buttons (Simple Version - Fixed)
 * Description: Aplikacja do nauki AI
 * Version: 3.9.0
 * Author: sempuls.com
 * Text Domain: multi-share-buttons
 * Domain Path: /languages
 * GitHub Plugin URI: https://github.com/sempuls/multi-ai-share-buttons
 * GitHub Branch: main
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Auto-updates from GitHub
require_once plugin_dir_path(__FILE__) . 'includes/class-update-checker.php';

if (class_exists('MultiAI_Update_Checker')) {
    new MultiAI_Update_Checker(
        __FILE__,
        'TWOJE_KONTO',           // Zamień na swoją nazwę użytkownika GitHub
        'multi-ai-share-buttons'  // Nazwa repozytorium
    );
}

add_filter('the_content', 'multi_share_buttons', 999); // Late priority to avoid conflicts

function multi_share_buttons($content) {
    // Prevent infinite loop - check if we're already processing
    static $is_processing = false;
    if ($is_processing) {
        return $content;
    }

    if (!is_singular()) return $content;

    $options = get_option('multi_share_buttons_settings', []);
    $post_type = get_post_type();

    if (empty($options['enabled_types'])) {
        $options['enabled_types'] = ['post' => 1, 'page' => 1, 'product' => 1];
        update_option('multi_share_buttons_settings', $options);
    }

    if (!empty($options['disable_home']) && is_front_page()) {
        return $content;
    }

    if (!empty($options['exclude_pages'])) {
        $exclude_list = array_filter(array_map('trim', explode(',', $options['exclude_pages'])));
        $current_id = (string) get_the_ID();
        $current_slug = basename(get_permalink());

        if (in_array($current_id, $exclude_list, true) || in_array($current_slug, $exclude_list, true)) {
            return $content;
        }
    }

    if (empty($options['enabled_types'][$post_type])) return $content;

    // Set flag to prevent recursion
    $is_processing = true;
    
    // Direct rendering - no lazy loading
    $buttons_html = multi_share_buttons_html();
    
    // Reset flag
    $is_processing = false;

    $position = $options['position'] ?? 'after';
    switch ($position) {
        case 'before':
            return $buttons_html . $content;
        case 'both':
            return $buttons_html . $content . $buttons_html;
        case 'after':
        default:
            return $content . $buttons_html;
    }
}

function multi_share_buttons_html() {
    global $post;
    
    if (!$post) {
        return '';
    }

    $options = get_option('multi_share_buttons_settings', []);
    $post_url   = esc_url(get_permalink($post));
    $post_title = esc_html(get_the_title($post));
    
    // FIXED: Remove filter temporarily to prevent infinite loop
    remove_filter('the_content', 'multi_share_buttons', 999);
    remove_filter('get_the_excerpt', 'wp_trim_excerpt');
    
    // Get excerpt safely
    $excerpt_text = '';
    if (!empty($post->post_excerpt)) {
        $excerpt_text = $post->post_excerpt;
    } else {
        // Manual excerpt from content without triggering filters
        $excerpt_text = wp_strip_all_tags(strip_shortcodes($post->post_content));
        $excerpt_text = wp_trim_words($excerpt_text, 55, '');
    }
    $excerpt = esc_html($excerpt_text);
    
    // Re-add filter
    add_filter('the_content', 'multi_share_buttons', 999);

    // Cache tagów
    static $tag_cache = [];
    $keywords = '';
    if (!empty($options['include_keywords'])) {
        if (!isset($tag_cache[$post->ID])) {
            $tags = wp_get_post_tags($post->ID, ['fields' => 'names']);
            $tag_cache[$post->ID] = is_array($tags) ? implode(', ', $tags) : '';
        }
        $keywords = esc_html($tag_cache[$post->ID]);
    }

    // Szablon zapytania
    $template = $options['query_template'] ?? 'Streść ten wpis: {url}';
    if (!empty($options['include_title'])) {
        $template .= " Tytuł: {title}";
    }
    if (!empty($options['include_keywords']) && !empty($keywords)) {
        $template .= " Słowa kluczowe: {keywords}";
    }
    if (!empty($excerpt)) {
        $template .= " Streszczenie: {excerpt}";
    }
    if (!empty($options['prefer_citations'])) {
        $template .= " Użyj cytatów z treści i podaj źródło: {url}";
    }

    $query = str_replace(
        ['{url}', '{title}', '{keywords}', '{excerpt}'],
        [$post_url, $post_title, $keywords, $excerpt],
        $template
    );

    $links = [
        'ChatGPT'     => 'https://chat.openai.com/?q=' . rawurlencode($query),
        'Perplexity'  => 'https://www.perplexity.ai/search/new?q=' . rawurlencode($query),
        'Grok'        => 'https://x.com/i/grok?text=' . rawurlencode($query),
        'Google AI'   => 'https://www.google.com/search?udm=50&aep=11&q=' . rawurlencode($query),
        'Gemini AI'   => 'https://gemini.google.com/app?query=' . rawurlencode($query),
        'Copilot'     => 'https://copilot.microsoft.com/?q=' . rawurlencode($query),
        'Claude'      => 'https://claude.ai/new?q=' . rawurlencode($query),
        'Meta AI'     => 'https://www.meta.ai/?q=' . rawurlencode($query),
        'Mistral'     => 'https://chat.mistral.ai/chat?q=' . rawurlencode($query),
        'DeepSeek'    => 'https://chat.deepseek.com/?q=' . rawurlencode($query),
    ];

    $class_map = [
        'ChatGPT'     => 'chatgpt',
        'Perplexity'  => 'perplexity',
        'Grok'        => 'grok',
        'Google AI'   => 'googleai',
        'Gemini AI'   => 'gemini',
        'Copilot'     => 'copilot',
        'Claude'      => 'claude',
        'Meta AI'     => 'metaai',
        'Mistral'     => 'mistral',
        'DeepSeek'    => 'deepseek',
    ];

    $buttons = '';
    foreach ($links as $name => $url) {
        $css_class = isset($class_map[$name]) ? $class_map[$name] : sanitize_html_class(strtolower(str_replace(' ', '', $name)));

        $buttons .= sprintf(
            '<a href="%s" target="_blank" rel="nofollow noopener noreferrer" class="btn track-click %s" data-sempuls-conversion-target data-postid="%s" data-button="%s" title="%s">%s</a>',
            esc_url($url),
            esc_attr($css_class),
            esc_attr($post->ID),
            esc_attr($name),
            esc_attr(sprintf(__('Wyślij zapytanie do %s', 'multi-share-buttons'), $name)),
            esc_html($name)
        );
    }

    $class = !empty($options['centered']) ? 'multi-share-buttons centered' : 'multi-share-buttons';
    $button_style = $options['button_style'] ?? 'premium';
    
    if ($button_style === 'minimal') {
        $style_class = 'minimal-style';
    } elseif ($button_style === 'minimal-colored') {
        $style_class = 'minimal-colored-style';
    } else {
        $style_class = 'premium-style';
    }
    
    $class .= ' ' . $style_class;
    
    $header_text = !empty($options['header_text']) 
        ? esc_html($options['header_text']) 
        : esc_html__('Przeanalizuj i cytuj treść przez sztuczną inteligencję:', 'multi-share-buttons');

    return sprintf(
        '<div class="%s"><strong>%s</strong><div class="multi-share-container">%s</div></div>',
        esc_attr($class),
        $header_text,
        $buttons
    );
}

/**
 * Shortcode
 */
add_shortcode('multi_share_buttons', function($atts) {
    // Prevent recursion in shortcode too
    static $shortcode_processing = false;
    if ($shortcode_processing) {
        return '';
    }
    
    $shortcode_processing = true;
    $output = multi_share_buttons_html();
    $shortcode_processing = false;
    
    return $output;
});

/**
 * Scripts + Styles
 */
add_action('wp_enqueue_scripts', function() {
    $options = get_option('multi_share_buttons_settings', []);

    if (empty($options['inline_css'])) {
        wp_enqueue_style(
            'multi-share-buttons', 
            plugin_dir_url(__FILE__) . 'assets/style.css', 
            [], 
            '3.9.0'
        );
    } else {
        add_action('wp_head', function() {
            static $css_cache = null;
            if ($css_cache === null) {
                $css_file = plugin_dir_path(__FILE__) . 'assets/style.css';
                if (file_exists($css_file)) {
                    $css_cache = file_get_contents($css_file);
                }
            }
            if ($css_cache) {
                echo '<style id="multi-share-buttons-inline-css">' . $css_cache . '</style>';
            }
        }, 5);
    }

    // Simple tracking script
    wp_add_inline_script('jquery', '
        jQuery(document).ready(function($) {
            $(document).on("click", ".multi-share-buttons .btn", function() {
                var buttonName = $(this).data("button");
                var postId = $(this).data("postid");
                
                if (typeof gtag !== "undefined") {
                    gtag("event", "klikniecie_ai_przycisku", {
                        event_category: "AI Buttons",
                        event_label: buttonName,
                        value: postId
                    });
                }
            });
        });
    ');
});

/**
 * Panel admina
 */
add_action('admin_menu', function() {
    add_options_page(
        __('Share Buttons Settings', 'multi-share-buttons'),
        __('Share Buttons', 'multi-share-buttons'),
        'manage_options',
        'multi-share-buttons',
        'multi_share_buttons_settings_page'
    );
});

add_action('admin_init', function() {
    register_setting('multi_share_buttons_settings_group', 'multi_share_buttons_settings', [
        'sanitize_callback' => 'multi_share_buttons_sanitize',
    ]);
});

function multi_share_buttons_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }

    $options = get_option('multi_share_buttons_settings', []);
    ?>
    <div class="wrap">
        <h1><?php _e('Ustawienia przycisków AI', 'multi-share-buttons'); ?></h1>
        <form method="post" action="options.php">
            <?php settings_fields('multi_share_buttons_settings_group'); ?>
            <?php do_settings_sections('multi_share_buttons_settings_group'); ?>

            <table class="form-table">
                <tr>
                    <th><?php _e('Pokazuj na typach wpisów', 'multi-share-buttons'); ?></th>
                    <td>
                        <?php
                        $post_types = get_post_types(['public' => true], 'objects');
                        foreach ($post_types as $type) {
                            if (in_array($type->name, ['attachment', 'revision', 'nav_menu_item'], true)) {
                                continue;
                            }

                            $checked = !empty($options['enabled_types'][$type->name]) ? 'checked' : '';
                            printf(
                                '<label><input type="checkbox" name="multi_share_buttons_settings[enabled_types][%s]" value="1" %s> %s</label><br>',
                                esc_attr($type->name),
                                $checked,
                                esc_html($type->labels->singular_name)
                            );
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Wykluczone strony (ID lub slug, oddzielone przecinkami)', 'multi-share-buttons'); ?></th>
                    <td>
                        <input type="text" name="multi_share_buttons_settings[exclude_pages]" value="<?php echo esc_attr($options['exclude_pages'] ?? ''); ?>" class="regular-text">
                        <p class="description"><?php _e('np. 123, 456, kontakt, o-nas', 'multi-share-buttons'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Pozycja przycisków', 'multi-share-buttons'); ?></th>
                    <td>
                        <select name="multi_share_buttons_settings[position]">
                            <option value="before" <?php selected($options['position'] ?? 'after', 'before'); ?>><?php _e('Przed treścią', 'multi-share-buttons'); ?></option>
                            <option value="after" <?php selected($options['position'] ?? 'after', 'after'); ?>><?php _e('Po treści', 'multi-share-buttons'); ?></option>
                            <option value="both" <?php selected($options['position'] ?? 'after', 'both'); ?>><?php _e('Przed i po treści', 'multi-share-buttons'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Wyłącz na stronie głównej', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[disable_home]" value="1" <?php checked($options['disable_home'] ?? 0, 1); ?>>
                            <?php _e('Nie pokazuj przycisków na stronie głównej', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Wyśrodkuj przyciski', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[centered]" value="1" <?php checked($options['centered'] ?? 0, 1); ?>>
                            <?php _e('Wyśrodkuj przyciski na stronie', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Styl przycisków', 'multi-share-buttons'); ?></th>
                    <td>
                        <select name="multi_share_buttons_settings[button_style]">
                            <option value="premium" <?php selected($options['button_style'] ?? 'premium', 'premium'); ?>><?php _e('Premium (gradienty i animacje)', 'multi-share-buttons'); ?></option>
                            <option value="minimal" <?php selected($options['button_style'] ?? 'premium', 'minimal'); ?>><?php _e('Minimalistyczny (czysty i prosty)', 'multi-share-buttons'); ?></option>
                            <option value="minimal-colored" <?php selected($options['button_style'] ?? 'premium', 'minimal-colored'); ?>><?php _e('Minimalistyczny Kolorowy (kolory brand)', 'multi-share-buttons'); ?></option>
                        </select>
                        <p class="description"><?php _e('Wybierz styl wizualny przycisków AI', 'multi-share-buttons'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Dodawaj tytuł wpisu', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[include_title]" value="1" <?php checked($options['include_title'] ?? 0, 1); ?>>
                            <?php _e('Dołącz tytuł wpisu do zapytania AI', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Dodawaj słowa kluczowe (tagi)', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[include_keywords]" value="1" <?php checked($options['include_keywords'] ?? 0, 1); ?>>
                            <?php _e('Dołącz tagi wpisu do zapytania AI', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Preferuj cytowanie', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[prefer_citations]" value="1" <?php checked($options['prefer_citations'] ?? 0, 1); ?>>
                            <?php _e('Poproś AI o cytowanie źródła', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Ładuj CSS inline', 'multi-share-buttons'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="multi_share_buttons_settings[inline_css]" value="1" <?php checked($options['inline_css'] ?? 0, 1); ?>>
                            <?php _e('Wstaw CSS bezpośrednio w HTML (bez zewnętrznego pliku)', 'multi-share-buttons'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Tekst nagłówka', 'multi-share-buttons'); ?></th>
                    <td>
                        <input type="text" name="multi_share_buttons_settings[header_text]" value="<?php echo esc_attr($options['header_text'] ?? ''); ?>" class="regular-text" placeholder="<?php esc_attr_e('Przeanalizuj i cytuj treść przez sztuczną inteligencję:', 'multi-share-buttons'); ?>">
                        <p class="description"><?php _e('Zostaw puste, aby użyć domyślnego tekstu', 'multi-share-buttons'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><?php _e('Szablon zapytania', 'multi-share-buttons'); ?></th>
                    <td>
                        <textarea name="multi_share_buttons_settings[query_template]" rows="4" class="large-text"><?php echo esc_textarea($options['query_template'] ?? 'Streść ten wpis: {url}'); ?></textarea>
                        <p class="description">
                            <?php _e('Dostępne zmienne: {url}, {title}, {keywords}, {excerpt}', 'multi-share-buttons'); ?><br>
                            <?php _e('Shortcode: [multi_share_buttons]', 'multi-share-buttons'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function multi_share_buttons_sanitize($input) {
    $output = [];

    $output['exclude_pages']    = sanitize_text_field($input['exclude_pages'] ?? '');
    $output['position']         = in_array($input['position'] ?? 'after', ['before', 'after', 'both'], true) ? $input['position'] : 'after';
    $output['disable_home']     = !empty($input['disable_home']) ? 1 : 0;
    $output['centered']         = !empty($input['centered']) ? 1 : 0;
    $output['button_style']     = in_array($input['button_style'] ?? 'premium', ['premium', 'minimal', 'minimal-colored'], true) ? $input['button_style'] : 'premium';
    $output['include_title']    = !empty($input['include_title']) ? 1 : 0;
    $output['include_keywords'] = !empty($input['include_keywords']) ? 1 : 0;
    $output['prefer_citations'] = !empty($input['prefer_citations']) ? 1 : 0;
    $output['inline_css']       = !empty($input['inline_css']) ? 1 : 0;
    $output['query_template']   = sanitize_textarea_field($input['query_template'] ?? '');
    $output['header_text']      = sanitize_text_field($input['header_text'] ?? '');

    $output['enabled_types'] = [];
    if (!empty($input['enabled_types']) && is_array($input['enabled_types'])) {
        foreach ($input['enabled_types'] as $type => $val) {
            $output['enabled_types'][sanitize_key($type)] = 1;
        }
    }

    return $output;
}

/**
 * Link do ustawień na liście wtyczek
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function($links) {
    $settings_link = '<a href="' . esc_url(admin_url('options-general.php?page=multi-share-buttons')) . '">' . __('Ustawienia', 'multi-share-buttons') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
});

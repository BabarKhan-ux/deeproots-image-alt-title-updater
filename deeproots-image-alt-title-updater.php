<?php
/**
 * Plugin Name: Deeproots Image Alt+Title Updater
 * Description: Upload a CSV with image URLs and alt texts to batch update media library.
 * Author: Deeproots Partners
 * Author URI: https://deeproots.io/
 */

add_action('admin_menu', function () {
    add_menu_page('Alt Updater', 'Alt Updater', 'manage_options', 'alt-updater', 'alt_updater_page');
});

add_action('admin_enqueue_scripts', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'alt-updater') {
        wp_enqueue_style('deeproots-alt-updater-style', plugin_dir_url(__FILE__) . 'css/admin-style.css');
    }
});

function alt_updater_page()
{
    ?>
    <div class="wrap">
        <h1>Image Alt Batch Updater</h1>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required>
            <button class="button button-primary" type="submit">Upload</button>
        </form>
        <div id="alt-update-progress"></div>
        <?php if (isset($_FILES['csv_file'])) process_csv_upload(); ?>
    </div>
    <?php
}

function process_csv_upload()
{
    if (!function_exists('wp_handle_upload')) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
    }

    $uploaded = wp_handle_upload($_FILES['csv_file'], ['test_form' => false]);

    if (isset($uploaded['file'])) {
        $file = fopen($uploaded['file'], 'r');
        $header = fgetcsv($file);
        $results = [
            'updated' => [],
            'skipped' => [],
        ];

        while (($line = fgetcsv($file)) !== false) {
            $csv_url = sanitize_url(trim($line[0]));
            $alt_text = sanitize_text_field(trim($line[1]));

            // Normalize URL (remove double slashes after domain)
            $csv_url = preg_replace('#(?<!:)//+#', '/', $csv_url);

            $found = false;

            $args = [
                'post_type' => 'attachment',
                'post_status' => 'inherit',
                'posts_per_page' => -1,
            ];

            $query = new WP_Query($args);

            foreach ($query->posts as $attachment) {
                $attachment_url = wp_get_attachment_url($attachment->ID);
                $normalized_url = preg_replace('#(?<!:)//+#', '/', $attachment_url);

                if (strpos($normalized_url, $csv_url) !== false) {
                    update_post_meta($attachment->ID, '_wp_attachment_image_alt', $alt_text);
                    $results['updated'][] = $csv_url;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                $results['skipped'][] = $csv_url;
            }
        }

        fclose($file);

        echo '<h3>✅ ' . count($results['updated']) . ' images updated with alt texts.</h3>';
        echo '<h3>⚠️ ' . count($results['skipped']) . ' images could not be matched.</h3>';
        if (!empty($results['skipped'])) {
            echo '<strong>Skipped URLs:</strong><br><pre>' . implode(PHP_EOL, $results['skipped']) . '</pre>';
        }
    } else {
        echo '<div class="notice notice-error"><p>Error uploading CSV.</p></div>';
    }
}


// Add instructions and download link to UI
add_action('admin_notices', function() {
    if (!isset($_GET['page']) || $_GET['page'] !== 'alt-updater') return;
    echo '<div class="notice notice-info"><p><strong>Instructions:</strong> Upload a CSV file with the following columns: <code>image_url</code>, <code>alt_text</code>, and optionally <code>title_text</code>. If <code>title_text</code> is left empty, alt text will be used as title text (if enabled).</p>';
    echo '<p><a href="' . plugin_dir_url(__FILE__) . 'sample.csv" class="button">Download Sample CSV</a></p></div>';
});

<?php

namespace pjnorberg;

class BatchConvert {

    private $settings = null;

    public function __construct() {

        $this->settings = unserialize(get_option('wpb-field-settings'));

        add_action('admin_menu', array($this, 'register_page'));
        add_action('template_redirect', array($this, 'output_csv'));
    }

    public function register_page() {
        add_submenu_page('tools.php', 'Batch settings', 'Batch settings', 'read', 'wbc-batch-settings', array($this, 'render_settings_page'));
        add_submenu_page('tools.php', 'Batch export posts', 'Batch export posts', 'read', 'wbc-export-posts', array($this, 'render_export_page'));
        add_submenu_page('tools.php', 'Batch import posts', 'Batch import posts', 'read', 'wbc-import-posts', array($this, 'render_import_page'));
    }

    public function render_settings_page() {

        echo __('<p>Select which post types to be included in export/import:</p>');

        $post_types = get_post_types(array(
            'public' => true,
        ));

        $settings = null;

        if (!empty($_POST)) {

            $_POST['meta_keys'] = explode(',', $_POST['meta_keys']);

            update_option('wpb-field-settings', serialize($_POST));
        }

        $settings = unserialize(get_option('wpb-field-settings'));

        echo '<form action="'.$_SERVER['REQUEST_URI'].'" method="post">';

        echo '<ul>';
        foreach ($post_types as $post_type) {
            echo '<li><label><input type="checkbox"'.($settings && in_array($post_type, $settings['post_types']) ? ' checked="checked"' : '').' name="post_types[]" value="'.$post_type.'"><strong>'.$post_type.'</strong></label></li>';
        }
        echo '</ul>';

        echo __('<p>Select which post statuses to be included in export/import:</p>');

        $post_statuses = get_post_statuses();

        echo '<ul>';
        foreach ($post_statuses as $post_status => $post_status_label) {
            echo '<li><label><input type="checkbox"'.($settings && in_array($post_status, $settings['post_statuses']) ? ' checked="checked"' : '').' name="post_statuses[]" value="'.$post_status.'"><strong>'.$post_status_label.'</strong></label></li>';
        }
        echo '</ul>';

        echo __('<p>Which post meta keys do wish you to include? <strong>Separate by comma (","):</strong></p>');

        $meta_keys = '';

        if ($settings && $settings['meta_keys']) {
            foreach ($settings['meta_keys'] as $meta_key) {
                $meta_keys .= "$meta_key,";
            }
            $meta_keys = rtrim($meta_keys, ',');
        }

        echo '<input name="meta_keys" value="'.$meta_keys.'" size="50">';

        echo '<p><input type="submit" value="Save batch settings" class="button"></p>';

        echo '</form>';
    }

    public function render_export_page() {

        echo __('<p>This plugin will export all selected posts with the following post meta fields (with current value if available):</p>');

        echo '<ul>';

        foreach ($this->settings['meta_keys'] as $meta_key) {
            echo "<li><strong>$meta_key</strong></li>";
        }

        echo '</ul>';

        echo '<p><a href="/downloads/wp-posts-export.csv" class="button">'.__('Generate CSV file').'</a></p>';
    }

    public function render_import_page() {

        if (isset($_FILES['userfile'])) {

            $filename = $_FILES['userfile']['tmp_name'];

            $fh = fopen($filename, 'r');
            $counter = 0;
            $success = 0;

            while($data = fgetcsv($fh, 0, ';')) {

                if ($counter) {

                    wp_update_post(array(
                        'ID' => $data[0],
                        'post_title' => utf8_encode($data[1]),
                    ));

                    $meta_key_counter = 3;
                    $success++;

                    foreach ($this->settings['meta_keys'] as $meta_key) {
                        update_post_meta($data[0], $meta_key, utf8_encode($data[$meta_key_counter]));
                        $meta_key_counter++;
                    }
                }

                $counter++;
            }

            echo '<p>'.__('Successfully updated <strong>')." $success ".__('posts')."!</strong></p>";

            fclose($fh);

        } else {
            echo '<p><form enctype="multipart/form-data" action="" method="POST">';
            echo '<input type="hidden" name="MAX_FILE_SIZE" value="30000">';
            echo __('Choose CSV file to upload: ').'<br><input name="userfile" type="file"><br>';
            echo '<input type="submit" class="button" value="'.__('Upload file').'">';
            echo '</form></p>';
        }
    }

    public function output_csv() {

        if (is_user_logged_in() && $_SERVER['REQUEST_URI'] == '/downloads/wp-posts-export.csv') {

            header("Content-type: text/csv; charset=utf-8",true,200);
            header("Content-Disposition: attachment; filename=wp-posts-export.csv");
            header("Pragma: no-cache");
            header("Expires: 0");

            $posts = get_posts(array(
                'posts_per_page' => -1,
                'post_status' => $this->settings['post_statuses'],
                'post_type' => $this->settings['post_types']
            ));

            $headings = array('post_id', 'post_title', 'post_modified');

            if (!empty($this->settings['meta_keys'])) {

                foreach ($this->settings['meta_keys'] as $meta_key) {
                    $headings[] = $meta_key;
                }
            }

            $i = 0;

            foreach ($posts as $post) {
                
                $array[$i][0] = $post->ID;
                $array[$i][1] = utf8_decode($post->post_title);
                $array[$i][2] = $post->post_modified;

                $meta_key_counter = 3;

                foreach ($this->settings['meta_keys'] as $meta_key) {
                    $array[$i][$meta_key_counter] = utf8_decode(get_post_meta($post->ID, $meta_key, true));
                    $meta_key_counter++;
                }

                $i++;
            }
             
            $fh = fopen('php://output', 'w');

            ob_start();

            fputcsv($fh, $headings, ';');
            
            if (!empty($array)) {
                foreach ($array as $item) {
                    fputcsv($fh, $item, ';');
                }
            }

            $string = ob_get_clean();

            fclose($fh);
             
            exit($string);
        }    
    }
}
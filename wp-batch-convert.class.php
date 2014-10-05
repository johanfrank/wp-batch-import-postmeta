<?php

namespace pjnorberg;

class BatchConvert {

    private $settings = null;

    public function __construct($settings) {

        $this->settings = $settings;

        add_action('admin_menu', array($this, 'register_page'));
        add_action('template_redirect', array($this, 'output_csv'));
    }

    public function register_page() {
        add_submenu_page('tools.php', 'Batch export posts', 'Batch export posts', 'read', 'wbc-export-posts', array($this, 'render_export_page'));
        add_submenu_page('tools.php', 'Batch import posts', 'Batch import posts', 'read', 'wbc-import-posts', array($this, 'render_import_page'));
    }

    public function render_export_page() {

        if (!$this->settings) {
            echo '<p>'.__('You haven\'t configured your plugin.').'</p>';
        }

        echo __('<p>This plugin will export all posts with the following post meta fields empty:</p>');

        echo '<ul>';

        foreach ($this->settings['meta_keys'] as $meta_key) {
            echo "<li><strong>$meta_key</strong></li>";
        }

        echo '</ul>';

        echo '<p><a href="/downloads/wp-posts-export.csv" class="button">'.__('Generate CSV file').'</a></p>';
    }

    public function render_import_page() {

        if (!$this->settings) {
            echo '<p>'.__('You haven\'t configured your plugin.').'</p>';
        }

        if (isset($_FILES['userfile'])) {

            $filename = $_FILES['userfile']['tmp_name'];

            $fh = fopen($filename, 'r');
            $counter = 0;

            while($data = fgetcsv($fh, 0, ';')) {

                if ($counter) {

                    wp_update_post(array(
                        'ID' => $data[0],
                        'post_title' => utf8_encode($data[1]),
                    ));

                    //echo '<pre>'.print_r($data, true).'</pre>';

                    $meta_key_counter = 3;

                    foreach ($this->settings['meta_keys'] as $meta_key) {
                        update_post_meta($data[0], $meta_key, utf8_encode($data[$meta_key_counter]));
                        $meta_key_counter++;
                    }
                }

                $counter++;
            }

            echo '<p>'.__('Successfully updated <strong>')." $counter ".__('posts')."!</strong></p>";

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
                'post_status' => $this->settings['post_status'],
                'post_type' => $this->settings['post_types']
            ));

            $headings = array('post_id', 'post_title', 'post_modified');

            if (!empty($this->settings['meta_keys'])) {

                foreach ($this->settings['meta_keys'] as $meta_key) {
                    $headings[] = $meta_key;
                }
            }

            foreach ($posts as $post) {
                $array[] = array(
                    $post->ID,
                    utf8_decode($post->post_title),
                    $post->post_modified,
                );
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
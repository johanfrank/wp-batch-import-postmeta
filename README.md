WP Batch import postmeta
========================

Export and import your Wordpress posts of any type to a handy CSV file, with your own post meta fields definitions.

Setup your plugin by defining what post types, post statuses and post meta keys:

    $BatchConvert = new pjnorberg\BatchConvert(array(
        'post_status' => array(
            'draft',
        ),
        'post_types' => array(
            'post',
            'page',
        ),
        'meta_keys' => array(
            'my_test_key',
            'my_other_test_key',
        )
    ));
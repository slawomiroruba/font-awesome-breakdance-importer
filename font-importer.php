<?php
/*
Plugin Name: Simple Zip Importer
Description: Importer plugin for ZIP files to check for LICENCE.txt.
Version: 1.0
*/

// Hook for adding admin menus
add_action('admin_menu', 'zip_importer_menu');

// action function for above hook
function zip_importer_menu()
{
    // Add a new submenu under Settings:
    add_options_page('Zip Importer', 'Zip Importer', 'manage_options', 'zipimporter', 'zip_importer_page');
}

function findDirectory($where, $what)
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($where, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isDir() && strtolower($file->getFilename()) === strtolower($what)) {
            // Return the full path to the directory
            return $file->getPathname();
        }
    }

    return null; // return null if no directory is found
}


global $wpdb;

function zip_importer_page()
{
    global $wpdb;
    if (isset($_FILES['zip_file']) && current_user_can('manage_options')) {
        $file = $_FILES['zip_file'];
        $upload_dir = wp_upload_dir();
        $extract_path = $upload_dir['basedir'] . '/zip-importer/';
        $iconSetName = sanitize_text_field($_POST['icon_set_name']);

        if ($file['type'] == 'application/zip' || $file['type'] == 'application/x-zip-compressed') {
            $zip = new ZipArchive;
            $res = $zip->open($file['tmp_name']);
            if ($res === TRUE) {
                $zip->extractTo($extract_path);
                $zip->close();
                echo '<div>Zip file has been extracted to ' . $extract_path . '</div>';
                // Define the path to the svgs directory
                $svgs_path = findDirectory($extract_path, 'svgs');
                echo "<div>SVGs path: $svgs_path</div>";
                // Check if svgs directory exists
                if (is_dir($svgs_path)) {
                    $icon_directories = array();
                    $dirIterator = new DirectoryIterator($svgs_path);
                    foreach ($dirIterator as $dir) {
                        if ($dir->isDir() && !$dir->isDot()) {
                            $icon_directories[] = $dir->getFilename();
                        }
                    }

                    foreach ($icon_directories as $icon_set_slug) {
                        $dir = $svgs_path . '/' . $icon_set_slug . '/';

                        if (is_dir($dir)) {
                            // Insert icon set information into wp_breakdance_icon_sets table
                            $wpdb->insert(
                                'wp_breakdance_icon_sets',
                                array(
                                    'slug' => $iconSetName . ' - ' . ucfirst($icon_set_slug),
                                    'name' => $iconSetName . ' - ' . ucfirst($icon_set_slug)
                                )
                            );
                            $icons = glob($dir . '*.svg');
                            foreach ($icons as $icon) {
                                $icon_name = basename($icon, '.svg');
                                $svg_code = file_get_contents($icon);

                                // Prepare data for insertion
                                $data = array(
                                    'icon_set_slug' => $iconSetName . ' - ' . ucfirst($icon_set_slug),
                                    'name' => $icon_name,
                                    'slug' => sanitize_title($icon_name),
                                    'svg_code' => $svg_code
                                );

                                // Insert data into the table
                                $wpdb->insert('wp_breakdance_icons', $data);
                            }
                        } else {
                            echo "<div>Directory $icon_set_slug does not exist.</div>";
                        }
                    }
                    echo '<div>SVG icons have been inserted into the database.</div>';
                } else {
                    echo '<div>SVGS directory not found in the zip file.</div>';
                }
            } else {
                echo '<div>Failed to open the zip file.</div>';
            }
        } else {
            echo '<div>Please upload a valid zip file.</div>';
        }
        // Remove everything (files and directories) from the extract directory
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extract_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $fileinfo) {
            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($fileinfo->getRealPath());
        }

        // Finally remove extract directory itself if needed
        @rmdir($extract_path);
    }

    // Display upload form with an additional field for the icon set name
    echo '<h2>Zip Importer</h2>';
    echo '<form method="post" enctype="multipart/form-data">';
    echo 'Icon Set Name: <input type="text" name="icon_set_name" required/><br/>';
    echo '<input type="file" name="zip_file" required/>';
    echo '<input type="submit" value="Upload and Check" />';
    echo '</form>';
}


?>

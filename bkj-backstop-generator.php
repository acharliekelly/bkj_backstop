<?php
/*
Plugin Name: BKJ Backstop Generator 2.0.4
Plugin URI: http://www.bkjproductions.com/
Description: Generates a new Backstop environment (backstop.json and package.json) for each Client Record
Version: 2.0.4
Author: BKJ Productions, LLC
Author URI: https://chatgpt.com/share/66f1e5b9-5918-8010-b986-5c12ddfa42bc
Version History:
2.0.4	Review folder creation
2.0.1 added folder stuff

*/

$bkj_backgen_version = '2.0.4';

//https://chatgpt.com/share/66f1e5b9-5918-8010-b986-5c12ddfa42bc
// https://chatgpt.com/share/66f1e5b9-5918-8010-b986-5c12ddfa42bc

// Add admin menu and settings page
function bkj_backgen_add_admin_menu() {
    add_options_page('Backstop Plugin', 'BKJ Backstop Generator', 'manage_options', 'backstop', 'bkj_backgen_settings_page');
}
add_action('admin_menu', 'bkj_backgen_add_admin_menu');

// Display settings page
function bkj_backgen_settings_page() {
	global $bkj_backgen_version;
    ?>
<style>
z#process-status { height: calc(100vh - 350px);
min-height: 300px;
display: block;
overflow: hidden;
overflow-y: auto;
width: 90%;}
</style>
    <div class="wrap">
        <h1>Backstop Generator <span style="font-size: 65%; font-weight: normal; display: inline-block; float: right;">Version <?php echo $bkj_backgen_version; ?></span></h1>
	    <p>This tool will loop through all Client Records and generate folders for Backstop.</p>
        <form method="post" action="options.php">
            <?php
            settings_fields('bkj_backgen_settings_group');
            do_settings_sections('bkj_backgen_settings_group');
            ?>
            <label for="destination">Destination (local path): </label>
            <input readonly  type="text" id="destination" name="bkj_backgen_destination" value="backstop" />
            <br><br>
           
        </form>
        <br>
        <button id="process-button" class="button">Process</button>
        <div id="process-status"></div>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('#process-button').click(function() {
                    if (confirm('Are you sure you want to process the clients? This will delete the last run (which is probably OK).')) {
                        $('#process-status').html('Processing...');
                        $.post(ajaxurl, { action: 'bkj_backgen_process_clients' }, function(response) {
                            $('#process-status').html(response);
                        });
                    }
                });
            });
        </script>
    </div>
    <?php
}

// Register settings
function bkj_backgen_register_settings() {
    register_setting('bkj_backgen_settings_group', 'bkj_backgen_destination');
}
add_action('admin_init', 'bkj_backgen_register_settings');
// Process clients with feedback and error handling
function bkj_backgen_process_clients() {
 	$destination = 'backstop';
	$destination = ABSPATH . trim($destination, '/');
	bkj_backgen_delete_old_backstop_zips(dirname($destination));
	$delay = 10000; // 10 second delay
	$threshold = "0.3";
	   // Check if the destination folder exists, and delete it if it does
    if (file_exists($destination)) {
        bkj_backgen_recursive_delete($destination);
        echo 'Deleted the existing destination folder: ' . esc_html($destination) . '<br>';
    }
	
    // Check if the destination folder exists, if not, create it
    if (empty($destination) || !file_exists($destination)) {
        if (!mkdir($destination, 0755, true)) {
            echo 'Failed to create or access the destination folder: ' . esc_html($destination);
            wp_die();
        }
        echo 'Created the destination folder: ' . esc_html($destination) . '<br>';
    }

    // Get all 'client' posts
    $clients = get_posts(array(
        'post_type' => 'client',
        'numberposts' => -1
    ));

    if (empty($clients)) {
        echo 'No clients found to process.';
        wp_die();
    }

	echo "Saving files to $destination.<BR>";
	// Get the plugin directory path for the template folder
	$plugin_dir = plugin_dir_path(__FILE__) . 'template';
	$backstop_json_template = file_get_contents($plugin_dir . '/backstop.json');
	$package_json_template = file_get_contents($plugin_dir . '/package.json');
	$additional_urls_template =     ',' . "\n" . '{"label": "<label>", "url": "<url-goes-here>", "delay": "<delay>","threshold": "<threshold>"}';
	$folders_needed = 'bitmaps_reference,bitmaps_test,html_report'; 
	$folders_needed = explode(',', $folders_needed);
	$client_k = 0;
	// Loop through each client post
	foreach ($clients as $client) {
		$client_k++;
		$no_urls_found = '';
		$client_slug = $client->post_name;
		$client_name = $client->post_title;
		$simple_slug = remove_last_item($client_slug);
		$urls = get_post_meta($client->ID, 'URLs to check', true);
		// if no URLs to check, then get the Website field:
		$website = get_post_meta($client->ID, 'Website', true);
		if (empty($urls)) {
			$no_urls_found = true;
			$urls = $website;
		}

		// Handle missing urls_to_check field
		if (empty($urls)) {
			echo 'Skipping client: ' . esc_html($client_name) . ' (No URLs found)<br>';
			continue; // Skip to next client
		}

		
		// Split URLs by any kind of newline (\r\n, \n, \r) and trim each line
		$url_list = preg_split('/\r\n|\r|\n/', trim($urls));

		// Remove any empty lines and make sure each URL is trimmed
		$url_list = array_filter($url_list, 'trim');

		$howmany_found = count($url_list);
		
		if (empty($url_list)) {
			echo 'Skipping client: ' . esc_html($client_name) . ' (Empty URL list)<br>';
			continue;
		}
		// make folder for client
		$client_dir = "$destination/$simple_slug";
		mkdir($client_dir, 0755, true);
        $data_dir = "$client_dir/backstop_data";
        mkdir($data_dir, 0755, true);
        
		echo 'Created directory for client: ' . esc_html($client_slug) . '<br>';

        		//$client_dir = "$destination/$simple_slug/backstop_data";
		//mkdir($client_dir, 0755, true);

		/*foreach ($folders_needed as $folder) {
			$client_dir = "$destination/$simple_slug/backstop_data/$folder";
			mkdir($client_dir, 0755, true);
			echo " &nbsp;&nbsp;&nbsp; Created subdirectory for client: $folder<br>";
		}
		*/

	/*	$client_dir = $destination . '/' . $client_slug;
		echo 'Seeking directory for ' . esc_html($client_dir) . '<br>';
		if (!file_exists($client_dir)) {
			mkdir($client_dir, 0755, true);
			echo 'Created directory for client: ' . esc_html($client_slug) . '<br>';
		}
*/

		// Process backstop.json

		$k = 1;
		$url_slug = get_last_part_of_url($url_list[0]);
		$backstop_json = str_replace('<url-goes-here>', $url_list[0], $backstop_json_template);
		$backstop_json = str_replace('<slug>', $simple_slug , $backstop_json);
		$backstop_json = str_replace('<label>', "$client_slug-$url_slug" . $k, $backstop_json);
		$backstop_json = str_replace('<delay>', $delay, $backstop_json);
        $backstop_json = str_replace('<threshold>', $threshold, $backstop_json);
		$additional_urls = '';
		if (count($url_list) > 1) {
			array_shift($url_list);
			foreach ($url_list as $url) {
				$url = trim($url);
				if (!$url) {continue;}
				$k++;
				$url_slug = get_last_part_of_url($url);
				$additional_url = str_replace('<url-goes-here>',$url, $additional_urls_template);
				$additional_url = str_replace('<slug>',$simple_slug, $additional_url);
				$additional_url = str_replace('<delay>',$delay, $additional_url);
				$additional_url = str_replace('<threshold>', $threshold, $additional_url);
				$additional_urls .= str_replace('<label>', "$client_slug-$url_slug", $additional_url);	
			}
		}
		$backstop_json = str_replace('<additional_urls>', $additional_urls, $backstop_json);

		// if file is named uniquely:
		//file_put_contents("$destination/$simple_slug/$simple_slug-backstop.json", $backstop_json);
		file_put_contents("$destination/$simple_slug/backstop.json", $backstop_json);
		echo "Processed backstop.json for client: " . esc_html($client_name);
		if ($no_urls_found) {echo " <em>(Default website URL only)</em>";}
		echo " Found $howmany_found<BR>";
		


		$package_json = str_replace(array('<name>', '<domain>'), array($client_name, $client_slug), $package_json_template);
		file_put_contents("$destination/$simple_slug/package.json", $package_json);
		echo 'Processed package.json for client: ' . esc_html($client_name) . '<br>';

	}

    // Zip the folder once all clients have been processed
    $zip_file = $destination . '-' . date('Y-m-d-H-i-s') . '.zip';
    if (bkj_backgen_zip_folder($destination, $zip_file)) {
        echo '<p>Zipped the destination folder.<br>';
        echo '<p><a href="' . esc_url(home_url('/') . basename($zip_file)) . '" class="button-primary">Download Zip</a></p>';
    } else {
        echo 'Failed to zip the destination folder.<br>';
    }

    wp_die();
}
add_action('wp_ajax_bkj_backgen_process_clients', 'bkj_backgen_process_clients');


// Zip folder function
function bkj_backgen_zip_folder($source, $destination) {
    if (!extension_loaded('zip') || !file_exists($source)) {
        return false;
    }

    $zip = new ZipArchive();
    if (!$zip->open($destination, ZIPARCHIVE::CREATE)) {
        return false;
    }

    $source = realpath($source);
    if (is_dir($source)) {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source), RecursiveIteratorIterator::LEAVES_ONLY);
        foreach ($files as $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = substr($file_path, strlen($source) + 1);
                $zip->addFile($file_path, $relative_path);
            }
        }
    }

    return $zip->close();
}

// Copy files recursively, excluding some
function bkj_backgen_recursive_copy($src, $dst, $skip_files = array()) {
    $dir = opendir($src);
    @mkdir($dst); // Create the destination directory if it doesn't exist

    while (false !== ($file = readdir($dir))) {
        if ($file == '.' || $file == '..') {
            continue; // Skip current and parent directory references
        }

        $src_path = $src . DIRECTORY_SEPARATOR . $file;
        $dst_path = $dst . DIRECTORY_SEPARATOR . $file;

        if (is_dir($src_path)) {
            // Ensure the directory is created in the destination even if it's empty
            if (!file_exists($dst_path)) {
                mkdir($dst_path, 0755, true);
                echo 'Created directory: ' . esc_html($dst_path) . '<br>';
            }
            bkj_backgen_recursive_copy($src_path, $dst_path, $skip_files); // Recursive call for subdirectories
        } else {
            // Only copy files that are not in the skip list
            if (!in_array($file, $skip_files)) {
                copy($src_path, $dst_path);
                echo 'Copied file: ' . esc_html($src_path) . ' to ' . esc_html($dst_path) . '<br>';
            }
        }
    }
    closedir($dir);
}

// Function to recursively delete a folder and its contents
function bkj_backgen_recursive_delete($dir) {
    if (!is_dir($dir)) return;

    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;

        $item_path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($item_path)) {
            bkj_backgen_recursive_delete($item_path); // Recursively delete subfolders
        } else {
            unlink($item_path); // Delete files
        }
    }
    rmdir($dir); // Finally, remove the directory itself
}


// Function to delete old ZIP files in the destination folder
function bkj_backgen_delete_old_backstop_zips($destination_parent) {
    // Look for ZIP files in the parent folder that match the backstop naming convention
    $zip_files = glob($destination_parent . '/backstop-*.zip');
    
    // Delete each matching ZIP file
    foreach ($zip_files as $zip_file) {
        if (file_exists($zip_file)) {
            unlink($zip_file);
            echo 'Deleted old ZIP file: ' . esc_html($zip_file) . '<br>';
        }
    }
}

function get_last_part_of_url($url) {
    // Parse the URL and get the path component
    $path = parse_url($url, PHP_URL_PATH);

    // Trim slashes from the end of the path and explode it into segments
    $segments = explode('/', trim($path, '/'));

    // Return the last segment
    return end($segments);
}

function remove_last_item($string) {
    // Split the string into an array using dash as the delimiter
    $parts = explode('-', $string);

    // Remove the last element from the array
    array_pop($parts);

    // Join the remaining elements back into a string
    return implode('-', $parts);
}

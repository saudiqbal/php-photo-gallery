<?php
// Remove trailing slashes (if present), and add one manually.
// Note: This avoids a problem where some servers might add a trailing slash, and others not..
define('BASE_PATH', rtrim(realpath(dirname(__FILE__)), "/") . '/');

require BASE_PATH . 'settings.php'; // Note. Include a file in same directory without slash in front of it!
require BASE_PATH . '_lib_/translator_class.php';
$translator = new translator($settings['lang']);
if(session_status() == PHP_SESSION_NONE){
    session_start();
    //session_cache_limiter("private_no_expire");
}
// <<<<<<<<<<<<<<<<<<<<
// Validate the _GET category input for security and error handling
// >>>>>>>>>>>>>>>>>>>>
$HTML_navigation = '<li><a href="/">'.$translator->string('Home').'</a></li>';

if (isset($_GET['category'])) {
  $HTML_navigation .= '<li><a href="index.php">'.$translator->string('Categories').'</a></li>';
  if (preg_match("/^[a-zæøåÆØÅ-]+$/i", $_GET['category'])) {
    $requested_category = $_GET['category'];
    if (isset($ignored_categories_and_files["$requested_category"])) {
      header("HTTP/1.0 500 Internal Server Error");
      echo '<!doctype html><html><head></head><body><h1>'.$translator->string('Error').'</h1><p>'.$translator->string('This is not a file or category...').'</p></body></html>';
      exit();
    }
    // <<<<<<<<<<<<<<<<<<<<
    // Fetch the files in the category, and include them in an HTML ul list
    // >>>>>>>>>>>>>>>>>>>>
    $files = list_files($settings, $ignored_categories_and_files);
	$totalfiles = count($files);
    if ($totalfiles >= 1) {
        $HTML_cup = '<ul id="images">';
$nb_elem_per_page = 10;
$adjacents = 3;
$page = isset($_GET['page'])?intval($_GET['page']-1):0;
$pgpage = isset($_GET['page'])?$_GET['page']:1;

$number_of_pages = intval($totalfiles/$nb_elem_per_page)+2;

        foreach ((array_slice($files, $page*$nb_elem_per_page, $nb_elem_per_page)) as &$file_name) {
            if (isset($_SESSION["password"])) {
                $delete_control = '<a href="admin.php?delete='.$requested_category .'/'. $file_name.'" class="delete"><img src="delete.png" alt="delete" style="width:30px;height:30px;"></a>';
                $category_preview_control = '<a href="admin.php?category='.$requested_category .'&set_preview_image='.$file_name.'" class="preview"><img src="preview.png" alt="set preview image" style="width:30px;height:30px;"></a>';
            } else {$delete_control='';$category_preview_control='';}
            $thumb_file_location = 'thumbnails/' . $requested_category . '/thumb-' . rawurlencode($file_name);
            $source_file_location = $requested_category . '/' . $file_name;
            $HTML_cup .= '<li><a href="viewer.php?category='.$requested_category.'&filename='.$file_name.'"><img src="'.$thumb_file_location.'" alt="'.$file_name.'"></a>'.$delete_control.$category_preview_control.'</li>';

        }
        $HTML_cup .= '</ul>';
		$pagination = pagination($totalfiles,$nb_elem_per_page,$pgpage,"index.php?category=".$requested_category ."&page=",$adjacents);
    } else {
        $HTML_cup = '<p>'.$translator->string('There are no files in:').' <b>' . space_or_dash('-', $requested_category) . '</b></p>';
    }
  } else {
    header("HTTP/1.0 500 Internal Server Error");
    echo '<!doctype html><html><head></head><body><h1>Error</h1><p>Invalid category</p></body></html>';
    exit();
  }
} else { // If no category was requested
    // <<<<<<<<<<<<<<<<<<<<
    // Fetch categories, and include them in a HTML ul list
    // >>>>>>>>>>>>>>>>>>>>
  $requested_category = $translator->string('Categories');
  $categories = list_directories($ignored_categories_and_files);
  if (count($categories) >= 1) {
    $HTML_cup = '<ul id="categories">';
    foreach ($categories as &$category_name) {
        if (isset($_SESSION["password"])) {
          $delete_control = '<a href="admin.php?delete='.$category_name.'" class="delete"><img src="delete.png" alt="delete" style="width:30px;height:30px;"></a>';
        } else {$delete_control='';}
        $category_preview_images = category_previews($category_name, $ignored_categories_and_files, $category_json_file);
        // echo 'cats:'.$category_preview_images; // Testing category views
        $HTML_cup .= '<li><div class="preview_images">'.$category_preview_images.'</div><div class="category"><a href="index.php?category='.$category_name.'" class=""><span>'.space_or_dash('-', $category_name).'</span></a></div>'.$delete_control.'</li>';
    }
    $HTML_cup .= '</ul>';
  } else {
    $HTML_cup = '<p>'.$translator->string('There are no categories yet...').'</p>';
  }
}
$HTML_navigation = '<ol class="flexbox">'.$HTML_navigation.'</ol>';

// ====================
// Functions
// ====================
function space_or_dash($replace_this='-', $in_this) {
  if ($replace_this=='-') {
    return preg_replace('/([-]+)/', ' ', $in_this);
  } elseif ($replace_this==' ') {
    return preg_replace('/([ ]+)/', '-', $in_this);
  }
}
function list_files($settings, $ignored_categories_and_files) {
  $directory = BASE_PATH . $_GET['category'];
  $thumbs_directory = BASE_PATH . 'thumbnails/' . $_GET['category'];
  $item_arr = array_diff(scandir($directory), array('..', '.'));
  foreach ($item_arr as $key => $value) {
      if ((is_dir($directory . '/' . $value)) || (isset($ignored_categories_and_files["$value"]))) {
      unset($item_arr["$key"]);
    } else {
      $path_to_file = $thumbs_directory . '/thumb-' . $value;
      if (file_exists($path_to_file) !== true) {
        createThumbnail($value, $directory, $thumbs_directory, 400, 400);
      }
    }
  }
  return $item_arr;
}
function category_previews($category, $ignored_categories_and_files, $category_json_file) {
    $thumbs_directory = BASE_PATH . 'thumbnails/' . $category;
    $previews_html = '';
    
    if (file_exists($thumbs_directory)) {

      if (file_exists($thumbs_directory . '/' . $category_json_file)) {
        $category_data = json_decode(file_get_contents($thumbs_directory . '/'. $category_json_file), true);
        
        $previews_html = '<div style="background:url(thumbnails/'.$category.'/'.rawurlencode($category_data['preview_image']).');" class="category_preview_img"></div>';
        
      } else {
        // Automatically try to select preview image if none was choosen
        $item_arr = array_diff(scandir($thumbs_directory), array('..', '.'));
        foreach ($item_arr as $key => $value) {
        // if ((is_dir($thumbs_directory . '/' . $value)) || (isset($ignored_categories_and_files["$value"]))) {
          // unset($item_arr["$key"]);
        // } else {
          $previews_html = '<div style="background:url(thumbnails/'.$category.'/'.rawurlencode($item_arr["$key"]).');" class="category_preview_img"></div>'; // add a dot in front of = to return all images
        //}
        }
        $category_data = json_encode(array('preview_image' => $item_arr["$key"]));
        file_put_contents($thumbs_directory . '/' . $category_json_file, $category_data);
      }
    }
    return $previews_html;
}
function list_directories($ignored_categories_and_files) {
    $item_arr = array_diff(scandir(BASE_PATH), array('..', '.'));
    foreach ($item_arr as $key => $value) {
        if ((is_dir(BASE_PATH . '/' . $value)==false) || (isset($ignored_categories_and_files["$value"]))) {unset($item_arr["$key"]);}
    }
    return $item_arr;
}

function createThumbnail($filename, $source_directory, $thumbs_directory, $max_width, $max_height) {
    $path_to_source_file = $source_directory . '/' . $filename;
    $path_to_thumb_file = $thumbs_directory . '/thumb-' . $filename;
    $source_filetype = exif_imagetype($path_to_source_file);
    if(file_exists($thumbs_directory) !== true) {
        if (!mkdir($thumbs_directory, 0777, true)) {
          echo $translator->string('Error: The thumbnails directory could not be created.');exit();
        } else {
          chmod($thumbs_directory, 0777); // On some hosts, we need to change permissions of the directory using chmod
                                          // after creating the directory
        }
    }
    // Create the thumbnail ----->>>>
    list($orig_width, $orig_height) = getimagesize($path_to_source_file);
    $width=$orig_width;$height=$orig_height;
    
    if ($height > $max_height) { // taller
      $width = ($max_height / $height) * $width;
      $height = $max_height;
    }
    if ($width > $max_width) { // wider
      $height = ($max_width / $width) * $height;
      $width = $max_width;
    }
    $image_p = imagecreatetruecolor($width, $height);
    
    switch ($source_filetype) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($path_to_source_file);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0,
                $width, $height, $orig_width, $orig_height);
            imagejpeg($image_p, $path_to_thumb_file);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($path_to_source_file);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0,
                $width, $height, $orig_width, $orig_height);
            imagepng($image_p, $path_to_thumb_file);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($path_to_source_file);
            imagecopyresampled($image_p, $image, 0, 0, 0, 0,
                $width, $height, $orig_width, $orig_height);
            imagegif($image_p, $path_to_thumb_file);
            break;
        default:
            echo $translator->string('Unknown filetype. Supported filetypes are: JPG, PNG og GIF.');exit();
    }
}
/*
	Plugin Name: *Digg Style Paginator
	Plugin URI: http://www.mis-algoritmos.com/2006/11/23/paginacion-al-estilo-digg-y-sabrosus/
	Description: Adds a <strong>digg style pagination</strong>.
	Version: 0.1 Beta
*/
function pagination($total_pages,$limit,$page,$file,$adjacents){
		if($page)
				$start = ($page - 1) * $limit; 			//first item to display on this page
			else
				$start = 0;								//if no page var is given, set start to 0

		/* Setup page vars for display. */
		if ($page == 0) $page = 1;					//if no page var is given, default to 1.
		$prev = $page - 1;							//anterior page is page - 1
		$siguiente = $page + 1;							//siguiente page is page + 1
		$lastpage = ceil($total_pages/$limit);		//lastpage is = total pages / items per page, rounded up.
		$lpm1 = $lastpage - 1;					//last page minus 1
		if($page > $lastpage)
		{
			echo "SQL Injection detected!";
			exit();
		}
		$link_previous = "&#x276E; Previous";
		$link_next = "Next &#x276F;";

		$p = false;
		if(strpos($file,"?")>0)
			$p = true;

		//ob_start();
		if($lastpage > 1){
				//anterior button
				if($page > 1)
								if($p)
									echo "<span class=\"pagination-prev\"><a href=\"$file$prev\" class=\"pagination-button left\">$link_previous</a></span>";
									else
									echo "<span class=\"pagination-prev\"><a href=\"$file$prev\" class=\"pagination-button left\">$link_previous</a></span>";
					else
						echo "<span class=\"buttonDisabled leftDisabled\">$link_previous</span>";
				//pages
				if ($lastpage < 7 + ($adjacents * 2)){//not enough pages to bother breaking it up
						for ($counter = 1; $counter <= $lastpage; $counter++){
								if ($counter == $page)
										echo "<span class=\"pagination-button middleCurrent\">$counter</span>";
									else
												if($p)
												echo "<a href=\"$file$counter\" class=\"pagination-button middle\">$counter</a>";
												else
												echo "<a href=\"$file?page=$counter\" class=\"pagination-button middle\">$counter</a>";
							}
					}
				elseif($lastpage > 5 + ($adjacents * 2)){//enough pages to hide some
						//close to beginning; only hide later pages
						if($page < 1 + ($adjacents * 2)){
								for ($counter = 1; $counter < 4 + ($adjacents * 2); $counter++){
										if ($counter == $page)
												echo "<span class=\"pagination-button middleCurrent\">$counter</span>";
											else
														if($p)
														echo "<a href=\"$file$counter\" class=\"pagination-button middle\">$counter</a>";
														else
														echo "<a href=\"$file?page=$counter\" class=\"pagination-button middle\">$counter</a>";
									}
								echo "";
										if($p){
										echo "<a href=\"$file$lpm1\" class=\"pagination-button middle\">$lpm1</a>";
										echo "<a href=\"$file$lastpage\" class=\"pagination-button middle\">$lastpage</a>";
										}else{
										echo "<a href=\"$file?page=$lpm1\" class=\"pagination-button middle\">$lpm1</a>";
										echo "<a href=\"$file?page=$lastpage\" class=\"pagination-button middle\">$lastpage</a>";
										}

							}
						//in middle; hide some front and some back
						elseif($lastpage - ($adjacents * 2) > $page && $page > ($adjacents * 2)){
										if($p){
										echo "<a href=\"{$file}1\" class=\"pagination-button middle\">1</a>";
										echo "<a href=\"{$file}2\" class=\"pagination-button middle\">2</a>";
										}else{
										echo "<a href=\"$file?page=1\" class=\"pagination-button middle\">1</a>";
										echo "<a href=\"$file?page=2\" class=\"pagination-button middle\">2</a>";
										}
								echo "";
								for ($counter = $page - $adjacents; $counter <= $page + $adjacents; $counter++)
									if ($counter == $page)
											echo "<span class=\"pagination-button middleCurrent\">$counter</span>";
										else
													if($p)
													echo "<a href=\"$file$counter\" class=\"pagination-button middle\">$counter</a>";
													else
													echo "<a href=\"$file?page=$counter\" class=\"pagination-button middle\">$counter</a>";
								echo "";
										if($p){
										echo "<a href=\"$file$lpm1\" class=\"pagination-button middle\">$lpm1</a>";
										echo "<a href=\"$file$lastpage\" class=\"pagination-button middle\">$lastpage</a>";
										}else{
										echo "<a href=\"$file?page=$lpm1\" class=\"pagination-button middle\">$lpm1</a>";
										echo "<a href=\"$file?page=$lastpage\" class=\"pagination-button middle\">$lastpage</a>";
										}
							}
						//close to end; only hide early pages
						else{
										if($p){
										echo "<a href=\"{$file}1\" class=\"pagination-button middle\">1</a>";
										echo "<a href=\"{$file}2\" class=\"pagination-button middle\">2</a>";
										}else{
										echo "<a href=\"$file?page=1\" class=\"pagination-button middle\">1</a>";
										echo "<a href=\"$file?page=2\" class=\"pagination-button middle\">2</a>";
										}
								echo "";
								for ($counter = $lastpage - (2 + ($adjacents * 2)); $counter <= $lastpage; $counter++)
									if ($counter == $page)
											echo "<span class=\"pagination-button middleCurrent\">$counter</span>";
										else
													if($p)
													echo "<a href=\"$file$counter\" class=\"pagination-button middle\">$counter</a>";
													else
													echo "<a href=\"$file?page=$counter\" class=\"pagination-button middle\">$counter</a>";
							}
					}
				if ($page < $counter - 1)
								if($p)
								echo "<span class=\"pagination-next\"><a href=\"$file$siguiente\" class=\"pagination-button right\">$link_next</a></span>";
								else
								echo "<span class=\"pagination-next\"><a href=\"$file?page=$siguiente\" class=\"pagination-button rightDisabled\">$link_next</a></span>";
					else
						echo "<span class=\"buttonDisabled rightDisabled pagination-next\">$link_next</span>";
			}
	}
// Pagination Ends

require BASE_PATH . 'templates/'.$template.'/category_template.php';

z<?php
/**
 * This takes an image that contains multiple images and extracts them out as individual files
 */

/**
 * Configuration
 * indent: % of indent from edges
 * increment: % of image to increment searching for images
 * background_color: what rgb to expect as the main background
 * background_okay: % color distance of what is considered background_color
 * min_dimension: The minimum width/height to expect for an image
 */
$config = [
	'indent' => 5,
	'increment' => 1,
	'background_color' => 0xffffff,
	'background_okay' => 7.5,
	'min_dimension' => 50,
];

/* repeat code */
function getPct($clr, $bg) {
	$dist = 0;
	if ($clr > $bg) {
		$diff = $clr - $bg;
	} else {
		$diff = $bg - $clr;
	}
	$dist = $bg - $diff;
	$pct = 100 - (($dist / $bg) * 100);
	return $pct;
}
	

/* Parameter */
if (!isset($argv[1])) {
	die('please provide a filename as a parameter' . "\n");
};

/* Must be image */
$file = $argv[1];
$filename = basename($file);
$image_data = getimagesize($file);
if ($image_data === false) {
	die($filename . ' is not a supported image' . "\n");
}
/* Make $file_name and $file_ext from $filename */
$pi = pathinfo($filename);
$file_name = isset($pi['filename']) ? $pi['filename'] : 'undefined';
$file_ext = isset($pi['extension']) ? $pi['extension'] : 'undef';

$width = $image_data[0];
$height = $image_data[1];

echo $filename . ':' . $width . 'x' . $height . "\n";

/* Based upon config variables get start/end set up */
$start_width = intval($width * ($config['indent'] / 100));
$end_width = $width - $start_width;
$width_increment = intval($width * ($config['increment'] / 100));
$start_height = intval($height * ($config['indent'] / 100));
$end_height = $height - $start_height;
$height_increment = intval($height * ($config['increment'] / 100));

echo '$start_width=' . $start_width . "\n";
echo '$end_width=' . $end_width . "\n";
echo '$width_increment=' . $width_increment . "\n";
echo '$start_height=' . $start_height . "\n";
echo '$end_height=' . $end_height . "\n";
echo '$height_increment=' . $height_increment . "\n";

/* Load up the image */
$image = imagecreatefromstring(file_get_contents($file));

/* Set up an array of x,y,width,height that we can pull images from */
$images = []; 

/* Loop through this image */
for ($h = $start_height; $h <= $end_height; $h += $height_increment) {
	for ($w = $start_width; $w <= $end_width; $w += $width_increment) {
		$pixel = imagecolorat($image, $w, $h);
		/* Calculate some "distance" to see if this is valid background_color */
		$pct = getPct($pixel, $config['background_color']);
		if ($pct > $config['background_okay']) { /* Not a background color */
			/* We need to take this "point", navigate to the 4 corners and add it to $images */
			/* Check "top" first */
			for ($top = $h; $top > 0; $top--) {
				$pixel = imagecolorat($image, $w, $top);
				$pct = getPct($pixel, $config['background_color']);
				if ($pct < $config['background_okay']) {
					break;
				}
			}
			/* Check "left" second */
			for ($left = $w; $left > 0; $left--) {
				$pixel = imagecolorat($image, $left, $h);
				$pct = getPct($pixel, $config['background_color']);
				if ($pct < $config['background_okay']) {
					break;
				}
			}
			/* Get "right" third */
			for ($right = $w; $right < $width; $right++) {
				$pixel = imagecolorat($image, $right, $h);
				$pct = getPct($pixel, $config['background_color']);
				if ($pct < $config['background_okay']) {
					break;
				}
			}
			/* Get "bottom" fourth */
			for ($bottom = $h; $bottom < $height; $bottom++) {
				$pixel = imagecolorat($image, $w, $bottom);
				$pct = getPct($pixel, $config['background_color']);
				if ($pct < $config['background_okay']) {
					break;
				}
			}
			/* Add to array */
			$images[] = [
				'top' => $top,
				'left' => $left,
				'right' => $right,
				'bottom' => $bottom
			];
		}
	}
}
echo "total: " . count($images) . "\n";

/* remove small images */
$smallimages = [];
foreach ($images as $i) {
	$imgwidth = $i['right'] - $i['left'];
	$imgheight = $i['bottom'] - $i['top'];
	if (($imgwidth >= $config['min_dimension']) && ($imgheight >= $config['min_dimension'])) {
		$smallimages[] = $i;
	}
}
$images = $smallimages;
echo "after min_dimension: " . count($images) . "\n";

/* Remove duplicates */
$dupimages = [];
foreach ($images as $i) {
	$match = false;
	foreach ($dupimages as $d) {
		if (($i['top'] == $d['top']) && ($i['left'] == $d['left']) && ($i['right'] == $d['right']) && ($i['bottom'] == $d['bottom'])) {
			$match = true;
		}
	}
	if (!$match) {
		$dupimages[] = $i;
	}
}
$images = $dupimages;
echo "after duplicates: " . count($images) . "\n";

/* Remove images within images */
$inimages = [];
foreach ($images as $i) {
	$inside = false;
	foreach ($images as $c) { /* Compare against the entire list */
		if (($i['top'] == $c['top']) && ($i['left'] == $c['left']) && ($i['right'] == $c['right']) && ($i['bottom'] == $c['bottom'])) {
			continue;
		}
		/* contains */
		if (($i['top'] >= $c['top']) && ($i['left'] >= $c['left']) && ($i['right'] <= $c['right']) && ($i['bottom'] <= $c['bottom'])) {
			$inside = true;
		}
	}
	if (!$inside) {
		$inimages[] = $i;
	}
}
$images = $inimages;
echo "after insides: " . count($images) . "\n";

/* Crap the images to files */
$img_num = 0;
foreach ($inimages as $i)
{
	$imgwidth = $i['right'] - $i['left'];
	$imgheight = $i['bottom'] - $i['top'];
	$new_image = imagecreatetruecolor($imgwidth, $imgheight);
	imagecopy($new_image, $image, 0, 0, $i['left'], $i['top'], $imgwidth, $imgheight);
	/* imagejpeg($new_image, $img_num . '-' . $filename); */
	imagejpeg($new_image, $file_name . '-' . $img_num . '.' . $file_ext);
	$img_num++;
}

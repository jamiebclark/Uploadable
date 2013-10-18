<?php
/* IMAGE FUNCTIONS 
	Created 1/31/2011
	Functions used for extending the basic PHP image functions
*/
class Image {

/**
 * Gets a reference to the Image object instance
 *
 * @return Image Instance of the Image.
 * @access public
 * @static
 */
	function &getInstance() {
		static $instance = array();

		if (!$instance) {
			$instance[0] = new Image();
		}
		return $instance[0];
	}

	function imageOutput($type, $resource, $filename = null, $quality = 100, $filters = null) {
		if (in_array($type, array('png', 'image/png'))) {
			if (!empty($quality)) {
				$quality = 10 - round($quality / 100);
				return imagepng($resource, $filename, $quality, $filters);
			}
		} else if (in_array($type, array('gif', 'image/gif'))) {
			return imagegif($resource, $filename);
		} else {
			return imagejpeg($resource, $filename, $quality);
		}
	}
	
	function createFromFile($filename) {
		ini_set("memory_limit", "256M");
		$self =& Image::getInstance();

	//Generates an image resource based on a variety of image types
		if (!is_file($filename)) {
			return false;
		}
		if (($size = getimagesize($filename)) === false) {
			return false;
		}		$img = false;
		switch($size['mime']) {
			case 'image/jpg':
			case 'image/jpeg':
			case 'image/pjpeg':
				$img = imagecreatefromjpeg($filename);
			break;
			case 'image/gif':
				$img = imagecreatefromgif($filename);
				$bg = imagecolorallocate($img, 255,255,255);
				imagecolortransparent($img, $bg);
			break;
			case 'image/png':
				$img = imagecreatefrompng($filename);
				$bg = imagecolorallocate($img, 255,255,255);
				imagecolortransparent($img, $bg);
				
				// turning off alpha blending (to ensure alpha channel information 
				// is preserved, rather than removed (blending with the rest of the 
				// image in the form of black))
				imagealphablending($img, false);
				
				// turning on alpha channel information saving (to ensure the full range 
				// of transparency is preserved)
				imagesavealpha($img, true);
			break;
			case 'image/bmp':
				$img = $self->createFromBmp($filename);
			break;
		}

		//$transparentColor = imagecolorallocate($img, 255, 0, 0);
		//imagecolortransparent($img, $transparentColor);
		//imagefilledrectangle($img2, 0, 0, $nw, $nh, $transparentColor);

		return $img;
	}
	
	function fileNameExtension($fileName) {
		$fileName = explode('.', $fileName);
		if (count($fileName) == 1) {
			return false;
		} else {
			return strtolower(array_pop($fileName));
		}
	}
	
	/**
	 * Determines if a given image exists in the Cake image directory
	 **/
	function isCakeImage($image) {
		return is_file( IMAGES . str_replace('/', DS, $image));
	}
	
	function fileExtension($file) {
		$self =& Image::getInstance();
		if (!is_file($file)) {
			return false;
		}
		$size = getimagesize($file);
		return $self->mimeExtension($size['mime']);
	}
	
	function mimeExtension($mime) {
		$ext = false;
		switch($mime) {
			case 'image/jpg':
			case 'image/jpeg':
				$ext = 'jpg';
			break;
			case 'image/gif':
				$ext = 'gif';
			break;
			case 'image/png':
				$ext = 'png';
			break;
			case 'image/bmp':
				$ext = 'bmp';
			break;
		}
		return $ext;
	}

	/*********************************************/
	/* Fonction: ImageCreateFromBMP              */
	/* Author:   DHKold                          */
	/* Contact:  admin@dhkold.com                */
	/* Date:     The 15th of June 2005           */
	/* Version:  2.0B                            */
	function createFromBmp($filename) {
		//Ouverture du fichier en mode binaire
		if (! $f1 = fopen($filename,"rb")) return FALSE;
		//1 : Chargement des ent?tes FICHIER
		$FILE = unpack("vfile_type/Vfile_size/Vreserved/Vbitmap_offset", fread($f1,14));
		if ($FILE['file_type'] != 19778) return FALSE;

		//2 : Chargement des ent?tes BMP
		$BMP = unpack('Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
					'/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
					'/Vvert_resolution/Vcolors_used/Vcolors_important', fread($f1,40));
		$BMP['colors'] = pow(2,$BMP['bits_per_pixel']);
		if ($BMP['size_bitmap'] == 0) $BMP['size_bitmap'] = $FILE['file_size'] - $FILE['bitmap_offset'];
		$BMP['bytes_per_pixel'] = $BMP['bits_per_pixel']/8;
		$BMP['bytes_per_pixel2'] = ceil($BMP['bytes_per_pixel']);
		$BMP['decal'] = ($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] -= floor($BMP['width']*$BMP['bytes_per_pixel']/4);
		$BMP['decal'] = 4-(4*$BMP['decal']);
		if ($BMP['decal'] == 4) $BMP['decal'] = 0;

		//3 : Chargement des couleurs de la palette
		$PALETTE = array();
		if ($BMP['colors'] < 16777216) {
		 $PALETTE = unpack('V'.$BMP['colors'], fread($f1,$BMP['colors']*4));
		}

		//4 : Cr?ation de l'image
		$IMG = fread($f1,$BMP['size_bitmap']);
		$VIDE = chr(0);

		$res = imagecreatetruecolor($BMP['width'],$BMP['height']);
		$P = 0;
		$Y = $BMP['height']-1;
		while ($Y >= 0) {
			$X=0;
			while ($X < $BMP['width']) {
				if ($BMP['bits_per_pixel'] == 24)
					$COLOR = unpack("V",substr($IMG,$P,3).$VIDE);
				elseif ($BMP['bits_per_pixel'] == 16) { 
					$COLOR = unpack("n",substr($IMG,$P,2));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				} elseif ($BMP['bits_per_pixel'] == 8) { 
					$COLOR = unpack("n",$VIDE.substr($IMG,$P,1));
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				} elseif ($BMP['bits_per_pixel'] == 4) {
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if (($P*2)%2 == 0) $COLOR[1] = ($COLOR[1] >> 4) ; else $COLOR[1] = ($COLOR[1] & 0x0F);
						$COLOR[1] = $PALETTE[$COLOR[1]+1];
				} elseif ($BMP['bits_per_pixel'] == 1) {
					$COLOR = unpack("n",$VIDE.substr($IMG,floor($P),1));
					if (($P*8)%8 == 0) $COLOR[1] =	$COLOR[1] 	>>7;
					elseif (($P*8)%8 == 1) $COLOR[1] = ($COLOR[1] & 0x40)>>6;
					elseif (($P*8)%8 == 2) $COLOR[1] = ($COLOR[1] & 0x20)>>5;
					elseif (($P*8)%8 == 3) $COLOR[1] = ($COLOR[1] & 0x10)>>4;
					elseif (($P*8)%8 == 4) $COLOR[1] = ($COLOR[1] & 0x8)>>3;
					elseif (($P*8)%8 == 5) $COLOR[1] = ($COLOR[1] & 0x4)>>2;
					elseif (($P*8)%8 == 6) $COLOR[1] = ($COLOR[1] & 0x2)>>1;
					elseif (($P*8)%8 == 7) $COLOR[1] = ($COLOR[1] & 0x1);
					$COLOR[1] = $PALETTE[$COLOR[1]+1];
				} else
					return FALSE;
				imagesetpixel($res,$X,$Y,$COLOR[1]);
				$X++;
				$P += $BMP['bytes_per_pixel'];
			}
			$Y--;
			$P+=$BMP['decal'];
		}

	 //Fermeture du fichier
		fclose($f1);
		return $res;
	}

	/**
	 * Forces an image to a new set of dimensions
	 * @param resource $img PHP Image Resource
	 * @param int $nw New width
	 * @param int $nh New height
	 * @param boolean $soft If soft is on, it will fit the image so part of the image is cut, leaving blank background showing in places
	 *
	 **/
	function constrainCrop($img, $nw, $nh, $soft = false) {
		$self =& Image::getInstance();
		$ow = imagesx($img);
		$oh = imagesy($img);
		
		//Original Ratio
		$r = $ow / $oh;
		
		//New Ratio
		$nr = $nw / $nh;
		
		$img2 = imagecreatetruecolor($nw, $nh);
		
		//debug("Ratio: $ow / $oh = $r");
		//debug("New Ratio: $nw / $nh = $nr");
		$heightCheck = $nw / $r >= $nh;
		if (($heightCheck && !$soft) || (!$heightCheck && $soft)) {
			//Fit width, crop the height
			$w = $ow;
			$h = ceil($w / $nr);
			$x = 0;
			$y = floor($oh / 2 - $h / 2); //Rounds so not to leave a blank row of pixels on the edge
			//Finds new adjusted height
			$ah = $nw / $r;
			$buffer = ($nh - $ah) / 2;
			$s1 = array(array(0,0), array($nw, $buffer));
			$s2 = array(array(0, $nh - $buffer), array($nw, $nh));
		} else {
			//Fit height, crop the width
			$h = $oh;
			$w = floor($h * $nr);
			$x = floor($ow /2 - $w / 2);	//Rounds so not to leave a blank column of pixels on the edge
			$y = 0;
			
			//Finds the new adjusted width
			$aw = $nh * $r;
			$buffer = ($nw - $aw) / 2;
			$s1 = array(array(0,0),array($buffer, $nh));
			$s2 = array(array($nw - $buffer,0), array($nw, $nh));

		}
		$self->copyResampled($img2, $img, 0, 0, $x, $y, $nw, $nh, $w, $h); 
		$bg = imagecolorallocate($img2, 255, 255, 255);
		if (0) {
			debug($s1);
			debug($s2);
			debug($s1[0][0].', '. $s1[0][1]. ' - ' . $s1[1][0] . ', ' . $s1[1][1]);
			debug($s2[0][0].', '. $s2[0][1]. ' - ' . $s2[1][0] . ', ' . $s2[1][1]);
		}
		if ($soft) {
			imagefilledrectangle($img2, floor($s1[0][0]), floor($s1[0][1]), ceil($s1[1][0]), ceil($s1[1][1]), $bg);
			imagefilledrectangle($img2, floor($s2[0][0]), floor($s2[0][1]), ceil($s2[1][0]), ceil($s2[1][1]), $bg);
		}
		return $img2;
	}

	function copyFileScaledCss($filename, $srcScale=1, $top=0, $left=0, $dstW=null, $dstH=null, $bgColor=null) {
		$self =& Image::getInstance();
		return $self->copyFileScaled($filename, $srcScale, $top * -1, $left * -1, $dstW, $dstH, $bgColor);
	}

	function copyFileScaled($filename, $srcScale=1, $srcX=0, $srcY=0, $dstW=null, $dstH=null, $bgColor=null) {
		if (!is_file($filename)) {
			return false;
		}
		$self =& Image::getInstance();

		list($fileW, $fileH) = getimagesize($filename);
		if (empty($dstW)) {
			$dstW = $fileW;
		}
		if (empty($dstH)) {
			$dstH = $fileH;
		}
		
		$imgDst = imagecreatetruecolor($dstW, $dstH);
		if (is_array($bgColor)) {
			list($r,$g,$b) = $bgColor;
			$bgColor = imagecolorallocate($imgDst, $r, $g, $b);
		} else if (is_string($bgColor)) {
			$bgColor = $self->colorAllocateStr($imgDst, $bgColor);
		} else if (empty($bgColor)) {
			$bgColor = imagecolorallocate($imgDst,0xF8,0xF8,0xF8);
		}
		
		$filename = str_replace(array('\\', '/'), DS, $filename);
		$imgSrc = imagecreatefromjpeg($filename);
		if (!$imgSrc) {
			$fh = fopen($filename, 'rb');
			$str = '';
			while ($fh !== false && !feof($fh)) {
				$str .= fread($fh, 1024);
			}
			$imgSrc = @imagecreatefromstring($str);
			if (!$imgSrc) {
				throw new Exception("Could not create source image resource from: $filename.");
			}
		}
		
		$srcW = $dstW / $srcScale;
		$srcH = $dstH / $srcScale;
		$srcXConvert = $srcX / $srcScale;
		$srcYConvert = $srcY / $srcScale;
		//debug(array($filename, 0, 0, $srcXConvert, $srcYConvert, $dstW, $dstH, $srcW, $srcH));
		$self->copyResampled($imgDst, $imgSrc, 0, 0, $srcXConvert, $srcYConvert, $dstW, $dstH, $srcW, $srcH);
		//imagecopyresampled($imgDst, $imgSrc, 0, 0, $srcXConvert, $srcYConvert, $dstW, $dstH, $srcW, $srcH);
		
		//Find Blank Spots
		$bounds = array(
			'x1' => $srcX * -1,
			'y1' => $srcY * -1,
			'x2' => $srcX * -1 + $fileW * $srcScale,
			'y2' => $srcY * -1 + $fileH * $srcScale,
		);
		if ($bounds['y2'] < $dstH) {
			imagefilledrectangle($imgDst,0,$bounds['y2'],$dstW,$dstH,$bgColor);
		}
		if ($bounds['y1'] > 0) {
			imagefilledrectangle($imgDst,0,0,$dstW,$bounds['y1'],$bgColor);
		}
		if ($bounds['x2'] < $dstW) {
			imagefilledrectangle($imgDst,$bounds['x2'],0,$dstW,$dstH,$bgColor);
		}
		if ($bounds['x1'] > 0) {
			imagefilledrectangle($imgDst,0,0,$bounds['x1'],$dstH,$bgColor);
		}
		return $imgDst;
	}

	/**
	 * Shrinks an image resource so that it falls within a specific height or width
	 *
	 * @param resource $img : a PHP image resource
	 * @param int $maxWidth : Maximum width the returned image can be
	 * @param int $maxHeight : Maximum height the returned image can be
	 *
	 * @return PHP image resource
	 */
	function constrain($img, $maxW = null, $maxH = null) {
		$self =& Image::getInstance();
		
		if (empty($maxW) && empty($maxH)) {
			return $img;
		}
		
		//Check for valid image resource
		if (($ow = @imagesx($img)) === false) {
			return false;
		}
		$oh = imagesy($img);
		list($w,$h) = array($ow,$oh);
		
		if ($w == 0 || $h == 0) {
			return false;
		}
		
		$ratio = $w/$h;
		if (!empty($maxW) && $w > $maxW) {
			$w = $maxW;
			$h = $maxW / $ratio;
		}
		if (!empty($maxH) && $h > $maxH) {
			$h = $maxH;
			$w = $ratio * $maxH;
		}
		if ($w <= 0 || $h <= 0) {
			return false;
		}
		if (!($img2 = imagecreatetruecolor($w,$h))) {
			print "COULD NOT CREATE IMAGE WITH DIMENSIONS: $w, $h<br/>";
		}
		$self->copyResampled($img2,$img,0,0,0,0,$w,$h,$ow,$oh);
		return $img2;
	}

	/**
	 * Extends PHP's imagecopyresampled function by also preserving any alpha values
	 */
	function copyResampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h) {
		$trnprt_indx = imagecolortransparent($src_image);
		// If we have a specific transparent color
		if ($trnprt_indx >= 0 && $trnprt_indx < imagecolorstotal($src_image)) {
			// Get the original image's transparent color's RGB values
			$trnprt_color    = imagecolorsforindex($src_image, $trnprt_indx);
			// Allocate the same color in the new image resource
			$trnprt_indx    = imagecolorallocate($dst_image, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
			// Completely fill the background of the new image with allocated color.
			imagefill($dst_image, 0, 0, $trnprt_indx);
			// Set the background color for new image to transparent
			imagecolortransparent($dst_image, $trnprt_indx);
		}
		//debug("$dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h");
		return imagecopyresampled($dst_image, $src_image, $dst_x, $dst_y, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h);
	}

	/************
	simple function that calculates the *exact* bounding box (single pixel precision).
	The function returns an associative array with these keys:
	left, top:  coordinates you will pass to imagettftext
	width, height: dimension of the image you have to create
	*************/
	function calculateTextBox($text, $fontFile, $fontSize, $fontAngle = 0) {
		$rect = imagettfbbox($fontSize,$fontAngle,$fontFile,$text);
		$minX = min(array($rect[0],$rect[2],$rect[4],$rect[6]));
		$maxX = max(array($rect[0],$rect[2],$rect[4],$rect[6]));
		$minY = min(array($rect[1],$rect[3],$rect[5],$rect[7]));
		$maxY = max(array($rect[1],$rect[3],$rect[5],$rect[7]));

		return array(
			 "left"   => abs($minX) - 1,
			 "top"    => abs($minY) - 1,
			 "width"  => $maxX - $minX,
			 "height" => $maxY - $minY,
			 "box"    => $rect
		);
	}
	
	/**
	 * Utilizing the hex2RGB function, allocate a hex string to an image resource
	 * @param resource $img : a PHP image resource
	 * @param string $color_hex_str : hexadecimal color value
	 */
	function colorAllocateStr($img, $color_hex_str) {
		$self =& Image::getInstance();
		if(($colors = $self->hex2Rgb($color_hex_str)) === FALSE) {
			return false;
		}
		return imagecolorallocate($img, $colors['red'], $colors['green'], $colors['blue']);
	}
		
	/**
	 * Convert a hexa decimal color code to its RGB equivalent
	 *
	 * @param string $hexStr (hexadecimal color value)
	 * @param boolean $returnAsString (if set true, returns the value separated by the separator character. Otherwise returns associative array)
	 * @param string $seperator (to separate RGB values. Applicable only if second parameter is true.)
	 * @return array or string (depending on second parameter. Returns False if invalid hex color value)
	 */                                                                                                
	function hex2Rgb($hexStr, $returnAsString = false, $seperator = ',') {
		$hexStr = preg_replace("/[^0-9A-Fa-f]/", '', $hexStr); // Gets a proper hex string
		$rgbArray = array();
		if (strlen($hexStr) == 6) { //If a proper hex code, convert using bitwise operation. No overhead... faster
			$colorVal = hexdec($hexStr);
			$rgbArray['red'] = 0xFF & ($colorVal >> 0x10);
			$rgbArray['green'] = 0xFF & ($colorVal >> 0x8);
			$rgbArray['blue'] = 0xFF & $colorVal;
		} elseif (strlen($hexStr) == 3) { //if shorthand notation, need some string manipulations
			$rgbArray['red'] = hexdec(str_repeat(substr($hexStr, 0, 1), 2));
			$rgbArray['green'] = hexdec(str_repeat(substr($hexStr, 1, 1), 2));
			$rgbArray['blue'] = hexdec(str_repeat(substr($hexStr, 2, 1), 2));
		} else {
			return false; //Invalid hex color code
		}
		return $returnAsString ? implode($seperator, $rgbArray) : $rgbArray; // returns the rgb string or the associative array
	}
}
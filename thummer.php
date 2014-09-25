<?php
class Thummer {

	const MIN_LENGTH = 50;
	const MAX_LENGTH = 500;
	const BASE_SOURCE_DIR = '/webapp/docroot/content/image';
	const BASE_TARGET_DIR = '/webapp/docroot/content/imagethumb';
	const REQUEST_PREFIX_URL_PATH = '/content/imagethumb';
	const SHARPEN_THUMBNAIL = true;
	const JPEG_IMAGE_QUALITY = 75;
	const PNG_SAVE_TRANSPARENCY = false;
	const FAIL_IMAGE_URL_PATH = '/content/thumbfail.jpg';
	const FAIL_IMAGE_LOG = false;


	public function __construct() {

		// get requested thumbnail from URI
		$requestURI = trim($_SERVER['REQUEST_URI']);
		$requestedThumb = $this->getRequestedThumb($requestURI);
		if ($requestedThumb === false) {
			// unable to determine requested thumbnail from URL
			$this->send404header();
			return;
		}

		// fetch source image details
		$sourceImageDetail = $this->getSourceImageDetail($requestedThumb[2]);
		if ($sourceImageDetail === false) {
			// unable to locate source image
			$this->send404header();
			return;
		}

		if ($sourceImageDetail === -1) {
			// source image invalid - redirect to fail image
			$this->redirectURL(self::FAIL_IMAGE_URL_PATH);
			$this->logFailImage($requestedThumb[2]);

			return;
		}

		// source image all good
		$this->generateThumbnail($requestedThumb,$sourceImageDetail);

		// redirect back to initial URL to display generated thumbnail image
		$this->redirectURL($requestURI);
	}

	private function getRequestedThumb($requestPath) {

		// check for URL prefix - remove if found
		$requestPath = (strpos($requestPath,self::REQUEST_PREFIX_URL_PATH) === 0)
			? substr($requestPath,strlen(self::REQUEST_PREFIX_URL_PATH))
			: $requestPath;

		// extract target thumbnail dimensions & source image
		if (!preg_match(
			'{^/([0-9]{1,4})x([0-9]{1,4})(/.+)$}',
			$requestPath,$requestMatch
		)) return false;

		// ensure width/height are within allowed bounds
		$width = intval($requestMatch[1]);
		$height = intval($requestMatch[2]);
		if (($width < self::MIN_LENGTH) || ($width > self::MAX_LENGTH)) return false;
		if (($height < self::MIN_LENGTH) || ($height > self::MAX_LENGTH)) return false;

		return array(
			$width,$height,
			// remove parent path components if request is trying to be sneaky
			str_replace(
				array('../','./'),'',
				$requestMatch[3]
			)
		);
	}

	private function getSourceImageDetail($source) {

		// image file exists?
		$srcPath = self::BASE_SOURCE_DIR . $source;
		if (!is_file($srcPath)) return false;

		// valid web image? return width/height/type
		set_error_handler(array($this,'errorWarningSink'));
		$detail = getimagesize($srcPath);
		restore_error_handler();

		if (
			($detail !== false) &&
			(($detail[2] == IMAGETYPE_GIF) || ($detail[2] == IMAGETYPE_JPEG) || ($detail[2] == IMAGETYPE_PNG))
		) return array($detail[0],$detail[1],$detail[2]);

		// not a valid image(type)
		return -1;
	}

	private function generateThumbnail(array $requestedThumb,array $sourceImageDetail) {

		// calculate source image copy dimensions, fixed to target requested thumbnail aspect ratio
		list($targetWidth,$targetHeight,$targetImagePathSuffix) = $requestedThumb;
		list($sourceWidth,$sourceHeight,$sourceType) = $sourceImageDetail;

		$targetAspectRatio = $targetWidth / $targetHeight;
		$copyWidth = intval($sourceHeight * $targetAspectRatio);
		$copyHeight = $sourceHeight;

		if ($copyWidth > $sourceWidth) {
			// resize copy height fixed to target aspect
			$copyWidth = $sourceWidth;
			$copyHeight = intval($sourceWidth / $targetAspectRatio);
		}

		// create source/target GD images and resize/resample
		$imageSrc = $this->createSourceGDImage($sourceType,self::BASE_SOURCE_DIR . $targetImagePathSuffix);
		$imageDst = imagecreatetruecolor($targetWidth,$targetHeight);

		if (($sourceType == IMAGETYPE_PNG) && self::PNG_SAVE_TRANSPARENCY) {
			// save PNG transparency in target thumbnail
			imagealphablending($imageDst,false);
			imagesavealpha($imageDst,true);
		}

		imagecopyresampled(
			$imageDst,$imageSrc,0,0,
			$this->calcThumbnailSourceCopyPoint($sourceWidth,$copyWidth),$this->calcThumbnailSourceCopyPoint($sourceHeight,$copyHeight),
			$targetWidth,$targetHeight,
			$copyWidth,$copyHeight
		);

		// sharpen thumbnail
		$this->sharpenThumbnail($imageDst);

		// construct full path to target image on disk and temp filename
		$targetImagePathFull = sprintf('%s/%dx%d%s',self::BASE_TARGET_DIR,$targetWidth,$targetHeight,$targetImagePathSuffix);
		$targetImagePathFullTemp = $targetImagePathFull . '.' . md5(uniqid());

		// if target image path doesn't exist, create it now
		if (!is_dir(dirname($targetImagePathFull))) {
			// setting a custom error handler to catch a possible warning if the directory already exists
			// this will happen if two PHP requests decide to create the new directory at the same time (race condition)
			set_error_handler(array($this,'errorWarningSink'));
			mkdir(dirname($targetImagePathFull),0777,true);
			restore_error_handler();
		}

		// save image to temp file
		switch ($sourceType) {
			case IMAGETYPE_GIF:
				imagegif($imageDst,$targetImagePathFullTemp);
				break;

			case IMAGETYPE_JPEG:
				imagejpeg($imageDst,$targetImagePathFullTemp,self::JPEG_IMAGE_QUALITY);
				break;

			default: // PNG image
				imagepng($imageDst,$targetImagePathFullTemp);
		}

		// move temp image file into place, avoiding race conditions between thummer requests and set modify timestamp to source image
		rename($targetImagePathFullTemp,$targetImagePathFull);
		touch($targetImagePathFull,filemtime(self::BASE_SOURCE_DIR . $targetImagePathSuffix));

		// destroy GD image instances
		imagedestroy($imageSrc);
		imagedestroy($imageDst);
	}

	private function createSourceGDImage($type,$path) {

		if ($type == IMAGETYPE_GIF) return imagecreatefromgif($path);
		if ($type == IMAGETYPE_JPEG) return imagecreatefromjpeg($path);
		return imagecreatefrompng($path);
	}

	private function calcThumbnailSourceCopyPoint($sourceLength,$copyLength) {

		$point = intval(($sourceLength - $copyLength) / 2);
		return max($point,0);
	}

	private function sharpenThumbnail($image) {

		if (!self::SHARPEN_THUMBNAIL) return;

		// build matrix and divisor
		$matrix = array(
			array(-1.2,-1,-1.2),
			array(-1,20,-1),
			array(-1.2,-1,-1.2)
		);

		// apply to image
		imageconvolution(
			$image,$matrix,
			11.2,0 // note: array_sum(array_map('array_sum',$matrix)) = 11.2;
		);
	}

	private function errorWarningSink() {

		// sink it
	}

	private function logFailImage($source) {

		if (self::FAIL_IMAGE_LOG === false) return;

		// write the requested file path to the error log
		$fp = fopen(self::FAIL_IMAGE_LOG,'a');
		fwrite($fp,self::BASE_SOURCE_DIR . $source . "\n");
		fclose($fp);
	}

	private function send404header() {

		header('HTTP/1.0 404 Not Found');
	}

	private function redirectURL($targetPath) {

		header(
			sprintf(
				'Location: http%s://%s%s',
				((isset($_SERVER['SERVER_PORT'])) && ($_SERVER['SERVER_PORT'] == 443)) ? 's' : '',
				$_SERVER['HTTP_HOST'],
				$targetPath
			),
			true,301
		);
	}
}


new Thummer();

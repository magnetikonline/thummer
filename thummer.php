<?php
class Thummer {

	const MIN_LENGTH = 50;
	const MAX_LENGTH = 500;
	const BASE_SOURCE_DIR = '/webapp/docroot/content/image';
	const BASE_TARGET_DIR = '/webapp/docroot/content/imagethumb';
	const REQUEST_PREFIX_URL_PATH = '/content/imagethumb';
	const JPEG_IMAGE_QUALITY = 75;
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

		// source image is all good - generate thumbnail
		$this->generateThumbnail($requestedThumb,$sourceImageDetail);

		// redirect back to initial URL to display generated thumbnail image
		$this->redirectURL($requestURI);
	}

	private function getRequestedThumb($requestPath) {

		// check for URL prefix - bail if not found
		if (strpos($requestPath,self::REQUEST_PREFIX_URL_PATH) !== 0) return false;
		$requestPath = substr($requestPath,strlen(self::REQUEST_PREFIX_URL_PATH));

		// extract target thumbnail dimensions & source image
		if (!preg_match('{^/(\d{1,3})x(\d{1,3})(/.+)$}',trim($requestPath),$requestMatch)) return false;

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
		$detail = @getimagesize($srcPath);
		if (
			(is_array($detail)) &&
			(($detail[2] == IMAGETYPE_GIF) || ($detail[2] == IMAGETYPE_JPEG) || ($detail[2] == IMAGETYPE_PNG))
		) return array($detail[0],$detail[1],$detail[2]);

		// not a valid image(type)
		return -1;
	}

	private function generateThumbnail(array $requestedThumb,array $sourceImageDetail) {

		// calculate source image copy dimensions, fixed to target requested thumbnail aspect ratio
		list($targetWidth,$targetHeight,$targetImagePathSuffix) = $requestedThumb;
		list($sourceWidth,$sourceHeight,$sourceType) = $sourceImageDetail;

		$targetAspectRatio = $targetHeight / $targetWidth;
		$copyWidth = $sourceWidth;
		$copyHeight = intval($sourceWidth * $targetAspectRatio);

		if ($copyHeight > $sourceHeight) {
			// resize copy width fixed to target aspect
			$copyWidth = intval($sourceHeight / $targetAspectRatio);
			$copyHeight = $sourceHeight;
		}

		// create source/target GD images and resize/resample
		$imageSrc = $this->createSourceGDImage($sourceType,self::BASE_SOURCE_DIR . $targetImagePathSuffix);
		$imageDst = imagecreatetruecolor($targetWidth,$targetHeight);
		imagecopyresampled(
			$imageDst,$imageSrc,0,0,
			$this->calcThumbnailSourceCopyPoint($sourceWidth,$copyWidth),$this->calcThumbnailSourceCopyPoint($sourceHeight,$copyHeight),
			$targetWidth,$targetHeight,
			$copyWidth,$copyHeight
		);

		// construct full path to target image on disk, temporary filename and then if required create target image path
		$targetImagePathFull = sprintf('%s/%dx%d%s',self::BASE_TARGET_DIR,$targetWidth,$targetHeight,$targetImagePathSuffix);
		$targetImagePathFullTmp = $targetImagePathFull . '.' . md5(uniqid());
		if (!is_dir(dirname($targetImagePathFull))) mkdir(dirname($targetImagePathFull),0777,true);

		// save image to temporary filename
		switch ($sourceType) {
			case IMAGETYPE_GIF:
				imagegif($imageDst,$targetImagePathFullTmp);
				break;

			case IMAGETYPE_JPEG:
				imagejpeg($imageDst,$targetImagePathFullTmp,self::JPEG_IMAGE_QUALITY);
				break;

			default:
				imagepng($imageDst,$targetImagePathFullTmp);
		}

		// move into place avoiding possible concurrency issues between thummer requests upon the same source image/dimensions
		rename($targetImagePathFullTmp,$targetImagePathFull);

		// set thumbnail timestamp to same as source image
		touch($targetImagePathFull,filemtime(self::BASE_SOURCE_DIR . $targetImagePathSuffix));

		// destroy gd images
		imagedestroy($imageSrc);
		imagedestroy($imageDst);
	}

	private function createSourceGDImage($type,$path) {

		if ($type == IMAGETYPE_GIF) return imagecreatefromgif($path);
		if ($type == IMAGETYPE_JPEG) return imagecreatefromjpeg($path);
		return imagecreatefrompng($path);
	}

	private function calcThumbnailSourceCopyPoint($sourceLength,$copyLength) {

		$point = intval(($sourceLength / 2) - ($copyLength / 2));
		return max($point,0);
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
			true,302
		);
	}
}


new Thummer();

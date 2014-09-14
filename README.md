# thummer
Rhymes with hummer.

This is an easy as pie web image thumbnail generator with width & height options given by request URL, plus thumbnail file caching back to original URL path - avoiding any server CPU overhead for repeated requests of the same thumbnail. Will handle JPEG, PNG and GIF images.

- [Requirements](#requirements)
- [Usage](#usage)
- [Install](#install)
	- [Configure thummer.php](#configure-thummerphp)
	- [Setup URL rewrite rules](#setup-url-rewrite-rules)
	- [All done](#all-done)
- [But what if my source images change?](#but-what-if-my-source-images-change)

## Requirements
- PHP 5.2+ (tested against PHP 5.5.10)
- [PHP GD extension](http://php.net/manual/en/book.image.php) (should be compiled into most PHP installs)
- Nginx, Apache (or equivalent) URL rewrite support

## Usage
So you have a directory of images accessible on your web server:

	http://mywebsite.com/content/image/apples.jpg
	http://mywebsite.com/content/image/oranges.png
	http://mywebsite.com/content/image/peach.gif

To request *100x50*, *200x300* and *180x90* thumbnails respectively, you do the following:

	http://mywebsite.com/content/imagethumb/100x50/apples.jpg
	http://mywebsite.com/content/imagethumb/200x300/oranges.png
	http://mywebsite.com/content/imagethumb/180x90/peach.gif

As a bonus, thummer will write the result of these thumbnails back to `/content/imagethumb/WxH/filename.ext` on your web server, so via the magic of URL rewrites the next request will simply return the **static** image back to the browser, saving precious CPU cycles.

## Install

### Configure thummer.php
Drop [`thummer.php`](thummer.php) into your web app and update class constants as follows:

Constant|Description
----|----
`MIN_LENGTH`|Minimum width/height of a requested thumbnail, everything less will respond 404.
`MAX_LENGTH`|Maximum width/height of a requested thumbnail, everything larger will respond 404.
`BASE_SOURCE_DIR`|The base directory where images to thumbnail live, without trailing slash.
`BASE_TARGET_DIR`|Where thumbnailed images will be saved, placed into a directory structure matching the requested source image. Target directory must be in line with your desired thumbnail request URL path (e.g. *http://mywebsite.com/content/imagethumb/...* --&gt; */[docroot]/content/imagethumb*). Ensure directory is writeable by your webserver/PHP processes. Without trailing slash.
`REQUEST_PREFIX_URL_PATH`|Prefix for thumbnail request URLs, before the **/WxH/** component. Without trailing slash.
`SHARPEN_THUMBNAIL`|If set **true** resized images will have a sharpen process applied before save. Implementation taken [from here](http://php.net/manual/en/function.imageconvolution.php#104006). This will more than likely result in extra CPU overhead, so you may wish to disable this option.
`JPEG_IMAGE_QUALITY`|Thumbnail save quality for JPEG image type. Between 0-100.
`PNG_SAVE_TRANSPARENCY`|If set **true**, PNG thumbnails will be saved with source image transparency preserved.
`FAIL_IMAGE_URL_PATH`|If thummer can't successfully read the source image, redirect user to the given fail image path.
`FAIL_IMAGE_LOG`|If thummer can't successfully read the source image, log image request here. Ensure file is writeable by webserver/PHP processes. Set **false** to disable.

### Setup URL rewrite rules
Refer to the supplied [`rewrite.nginx.conf`](rewrite.nginx.conf) & [`rewrite.apache.conf`](rewrite.apache.conf) for examples. For Apache, these rules can be placed into either a `.htaccess` file or (better yet) the web servers `/etc/apache2/apache2.conf`.

**Note:** it's strongly recommended in a production environment to adjust the rewrite rules, targeting the specific set of widths/heights you require, rather than arbitrary lengths to avoid a possible flooding of thumbnail images to disk of limitless dimensions. Examples for this are provided as comments in both `rewrite.nginx.conf` & `rewrite.apache.conf`.

### All done
Assuming everything is configured correctly the following should now occur:
- Request is made for a thumbnail image (e.g. http://mywebsite.com/content/imagethumb/WxH/filename.ext)
- URL rewrite rules check if static image already exists on disk
- **a)** It does, so web server simply returns thumbnail without involving `thummer.php`
- **b)** Static thumbnail image does not yet exist
	- Web server rewrites URL to `thummer.php`.
	- Thummer reads source image, generates thumbnail at requested dimensions and saves image file back to server.
	- Finally `thummer.php` redirects URL back to re-request the static thumbnail image.
	- Future thumbnail requests now fetch the static thumbnail.

## But what if my source images change?
Since thummer relies on the fact that repeat requests to the same thumbnail won't involve `thummer.php` to save CPU cycles, updates to source image files won't automatically reflected in generated thumbnails. For many use cases this may not be an issue to worry about vs. the benefit.

If this will be an issue, refer to the example [`thummercleanup.sh`](thummercleanup.sh) bash script, which can compare generated thumbnails against the source image to ensure:
- The source image still exists.
- Timestamps for the thumbnail vs. source are identical (**note:** `thummer.php` timestamps generated thumbnails to that of the source file for exactly this purpose).

If either of these conditions are not met, the script will delete the thumbnail in question. This then allows `thummer.php` to re-create on next request of the thumbnail. This script once configured could then be run as a regular cron job.

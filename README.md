# thummer
Rhymes with hummer.

This is an easy as pie web image thumbnail generator with width & height options given by request URL, plus thumbnail file caching back to original URL path - avoiding any server CPU overhead for repeated requests of the same thumbnail. Will handle JPEG, PNG and GIF images.

## Requires
- PHP 5.2+ (tested with PHP 5.4)
- [PHP GD extension](http://php.net/manual/en/book.image.php) (should be compiled into most PHP installs)
- Nginx or Apache URL rewrite support

## Usage
So you have a directory of images accessible on your web server like so:

	http://mywebsite.com/content/image/apples.jpg
	http://mywebsite.com/content/image/oranges.png
	http://mywebsite.com/content/image/peach.gif

To request *100x50*, *200x300* and *180x90* thumbnails respectively, you do the following:

	http://mywebsite.com/content/imagethumb/100x50/apples.jpg
	http://mywebsite.com/content/imagethumb/200x300/oranges.png
	http://mywebsite.com/content/imagethumb/180x90/peach.gif

As a bonus thummer would write the result of these thumbnails back to `/content/imagethumb/WxH/filename.ext` on your web server, so via the magic of URL rewrites the next request will simply return the **static** image back to the browser, saving precious CPU cycles.

## Installing

### Configure thummer.php
Drop `thummer.php` into your web app and update the class constants as follows:

<table>
	<tr>
		<td>MIN_LENGTH</td>
		<td>Minimum width/height of a requested thumbnail, everything less will respond 404.</td>
	</tr>
	<tr>
		<td>MAX_LENGTH</td>
		<td>Maximum width/height of a requested thumbnail, everything less will respond 404.</td>
	</tr>
	<tr>
		<td>BASE_SOURCE_DIR</td>
		<td>The base directory where images to thumbnail live, without trailing slash.</td>
	</tr>
	<tr>
		<td>BASE_TARGET_DIR</td>
		<td>Where thumbnailed images will be saved, placed into a directory structure matching the requested source image. Target directory must be in line with your desired thumbnail request URL path (e.g. <em>http://mywebsite.com/content/imagethumb/...</em> --> <em>[docroot]/content/imagethumb</em>). Ensure directory is writeable by your webserver/PHP processes. Without trailing slash.</td>
	</tr>
	<tr>
		<td>REQUEST_PREFIX_URL_PATH</td>
		<td>Prefix for thumbnail request URLs, before <strong>/WxH/</strong> component. Without trailing slash.</td>
	</tr>
	<tr>
		<td>JPEG_IMAGE_QUALITY</td>
		<td>Thumbnail save quality for JPEG image type. Between 0-100.</td>
	</tr>
	<tr>
		<td>FAIL_IMAGE_URL_PATH</td>
		<td>If thummer can't successfully read the source image, redirect user to the given fail image path.</td>
	</tr>
	<tr>
		<td>FAIL_IMAGE_URL_PATH</td>
		<td>If thummer can't successfully read the source image, log image request here. Ensure file is writeable by webserver/PHP processes. Set <strong>false</strong> to disable.</td>
	</tr>
</table>

### Setup URL rewrite rules
Refer to the supplied `rewrite.nginx.conf` & `rewrite.apache.conf` for examples. Of course for Apache, these rules could be placed into a `.htaccess` file.

### All done
Assuming everything is configured correctly the following should now occur:

- Request is made to a thumbnail image path (e.g. http://mywebsite.com/content/imagethumb/WxH/filename.ext)
- URL rewrite rules check if static image exists on disk
- It does so web server simply returns thumbnail without involving `thummer.php`
- **...or** static thumbnail image does not yet exist
	- Server rewrites URL to `thummer.php`
	- Thummer reads source image successfully, generates thumbnail and saves back to server
	- Now redirects URL back to re-request the static thumbnail image
	- Future thumbnail requests now fetch static thumbnail

## But what if my source images change?
Since thummer relies on the fact that repeat requests to the same thumbnail won't involve `thummer.php` to save CPU cycles, updates to the source image file won't automatically reflected in generated thumbnails. For many use cases this may not be an issue to worry about vs. the benefit.

If this will be an issue refer to the example `thummercleanup.sh` bash script, which will check generated thumbnails against the source image that:

- The source image actually still exists
- Timestamps for the thumbnail vs. source are identical (`thummer.php` timestamps thumbnails back to the source file for this reason)

If either of these conditions are not met, the script will delete the thumbnail in question allowing `thummer.php` to re-create on next request of the thumbnail. This script once configured could then be run as a regular cron job.

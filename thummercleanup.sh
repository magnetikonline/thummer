#!/bin/bash

BASE_SOURCE_DIR="/webapp/docroot/content/image"
BASE_TARGET_DIR="/webapp/docroot/content/imagethumb";


# fetch all thumbnail images
for imageThumbnail in `find "$BASE_TARGET_DIR" -type f \( -name "*.gif" -or -name "*.jpg" -or -name "*.jpeg" -or -name "*.png" \)`
do
	# strip thumbnail path and WxH component
	imageSource=${imageThumbnail#$BASE_TARGET_DIR/}
	if [[ "$imageSource" =~ ^[0-9]{1,3}x[0-9]{1,3}(/.+)$ ]]; then
		imageSource=$BASE_SOURCE_DIR${BASH_REMATCH[1]}

		if [ -f "$imageSource" ]; then
			if [ `stat -c %Y "$imageThumbnail"` != `stat -c %Y "$imageSource"` ]; then
				# thumbnail modification time does not equal that of source
				rm -f "$imageThumbnail"
			fi
		else
			# source image no longer exists
			rm -f "$imageThumbnail"
		fi
	fi
done

#!/bin/bash

BASE_SOURCE_DIR="/webapp/docroot/content/image"
BASE_TARGET_DIR="/webapp/docroot/content/imagethumb";


# fetch all thumbnail images
for IMAGE_THUMBNAIL in `find "$BASE_TARGET_DIR" -type f`
do
	# strip thumbnail path and WxH component
	IMAGE_SOURCE=${IMAGE_THUMBNAIL#$BASE_TARGET_DIR/}
	if [[ "$IMAGE_SOURCE" =~ ^[0-9]{1,3}x[0-9]{1,3}(/.+)$ ]]; then
		IMAGE_SOURCE=$BASE_SOURCE_DIR${BASH_REMATCH[1]}

		if [ -f "$IMAGE_SOURCE" ]; then
			if [ `stat -c %Y "$IMAGE_THUMBNAIL"` != `stat -c %Y "$IMAGE_SOURCE"` ]; then
				# thumbnail modification time does not equal that of source
				rm -f "$IMAGE_THUMBNAIL"
			fi
		else
			# source image no longer exists
			rm -f "$IMAGE_THUMBNAIL"
		fi
	fi
done

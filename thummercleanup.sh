#!/bin/bash

BASESOURCEDIR="/webapp/docroot/content/image"
BASETARGETDIR="/webapp/docroot/content/imagethumb";


# fetch all thumbnail images
for IMAGETHUMBNAIL in `find "$BASETARGETDIR" -type f \( -name "*.gif" -or -name "*.jpg" -or -name "*.jpeg" -or -name "*.png" \)`
do
	# strip thumbnail path and WxH component
	IMAGESOURCE=${IMAGETHUMBNAIL#$BASETARGETDIR/}
	if [[ "$IMAGESOURCE" =~ ^[0-9]{1,3}x[0-9]{1,3}(/.+)$ ]]; then
		IMAGESOURCE=$BASESOURCEDIR${BASH_REMATCH[1]}

		if [ -f "$IMAGESOURCE" ]; then
			if [ `stat -c %Y "$IMAGETHUMBNAIL"` != `stat -c %Y "$IMAGESOURCE"` ]; then
				# thumbnail modification time does not equal that of source
				rm -f "$IMAGETHUMBNAIL"
			fi
		else
			# source image no longer exists
			rm -f "$IMAGETHUMBNAIL"
		fi
	fi
done

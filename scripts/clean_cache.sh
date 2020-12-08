#!/bin/bash

cd "$1"

START=2013

MONTH=0
YEAR=$START

CURRENT=`date "+%Y%m"`

log() {
	message="$1"
	date=`date "+[%Y-%m-%d %H:%M:%S]"`
	echo "$date $message"
}

log "Starting clean_cache.sh"

FAIL=0
while true; do
	MONTH=$((MONTH+1))
	if [ "$MONTH" -eq 13 ]; then
		MONTH=1
		YEAR=$((YEAR+1))
	fi

	FULL_MONTH=""
	if [ "$MONTH" -lt 10 ]; then
		FULL_MONTH="0"
	fi
	FULL_MONTH="${FULL_MONTH}${MONTH}"

	FILENAME_PART="${YEAR}${FULL_MONTH}"

	if [ "$FILENAME_PART" == "$CURRENT" ]; then
		break
	fi

	if [ "`ls|grep -c _${FILENAME_PART}`" -gt "0" ]; then
		mkdir -p ${YEAR}/${FULL_MONTH} || FAIL=1

		if [ "$FAIL" -eq "1" ]; then
			log "Error creating directory ${YEAR}/${FULL_MONTH}"
			break
		fi
	
		log "Archiving ${FILENAME_PART}..."
		find . -maxdepth 1 -name "*_${FILENAME_PART}*" -exec mv {} ${YEAR}/${FULL_MONTH} \; || FAIL=1
		if [ "$FAIL" -eq "1" ]; then
			log "Error during archiving"
			break
		fi

		cd ${YEAR}
		tar cjf ${FULL_MONTH}.tar.bz2 ${FULL_MONTH} || FAIL=1
		if [ "$FAIL" -eq "1" ]; then
			log "Error archiving ${FULL_MONTH} to ${FULL_MONTH}.tar.bz2"
			break
		fi
		rm -rf ${FULL_MONTH} || FAIL=1
		if [ "$FAIL" -eq "1" ]; then
			log "Error deleting ${FULL_MONTH}"
			break
		fi
		cd ..
	fi
done

if [ "$FAIL" -eq "0" ]; then
	cd "$1"
	touch last_cache_cleanup
fi

log "Finished clean_cache.sh"

exit $FAIL

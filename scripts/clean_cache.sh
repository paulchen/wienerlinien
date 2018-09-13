#!/bin/bash

cd "$1"

START=2013

MONTH=0
YEAR=$START

CURRENT=`date "+%Y%m"`

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

	FOUND=0
	for f in `ls *_${FILENAME_PART}* 2>/dev/null`; do
		if [ -e "$f" ]; then
			FOUND=1
		fi
		break
	done

	if [ "$FOUND" -eq "1" ]; then
		mkdir -p ${YEAR}/${FULL_MONTH} || FAIL=1

		if [ "$FAIL" -eq "1" ]; then
			echo "Error creating directory ${YEAR}/${FULL_MONTH}"
			break
		fi
	
		echo Archiving ${FILENAME_PART}...
		while true; do
			COPIED=0
			for f in `ls *_${FILENAME_PART}* 2>/dev/null`; do
				if [ -e "$f" ]; then
					COPIED=1
					mv $f ${YEAR}/${FULL_MONTH} || FAIL=1
					if [ "$FAIL" -eq "1" ]; then
						echo "Error moving $f to ${YEAR}/${FULL_MONTH}"
						break
					fi
				fi
			done
			if [ "$COPIED" -eq "0" ]; then
				break
			fi
		done

		cd ${YEAR}
		tar cjf ${FULL_MONTH}.tar.bz2 ${FULL_MONTH} || FAIL=1
		if [ "$FAIL" -eq "1" ]; then
			echo Error archiving ${FULL_MONTH} to ${FULL_MONTH}.tar.bz2
			break
		fi
		rm -rf ${FULL_MONTH} || FAIL=1
		if [ "$FAIL" -eq "1" ]; then
			echo Error deleting ${FULL_MONTH}
			break
		fi
		cd ..
	fi
done

if [ "$FAIL" -eq "0" ]; then
	cd "$1"
	touch last_cache_cleanup
fi

exit $FAIL

#!/bin/bash

LOGFILE=/var/log/wienerlinien_cache.log
TMP_LOG=/tmp/wienerlinien.log

rm -f "$TMP_LOG"

DIRECTORY=`dirname "$0"`
cd "$DIRECTORY"

FAIL=0
./clean_cache.sh $* > "$TMP_LOG" 2>&1 || FAIL=1

cat "$TMP_LOG" >> "$LOGFILE"

if [ "$FAIL" -eq "1" ]; then
	cat "$TMP_LOG"
fi

rm -f "$TMP_LOG"

exit $FAIL


#!/bin/bash

RUN_STATUS_DIR=/tmp/vagrant_sync
PID_FILE="$RUN_STATUS_DIR"/vagrant_sync.pid
STARTUP_LOG_FILE="$RUN_STATUS_DIR".log
LOG_FILE="$RUN_STATUS_DIR"/vagrant_sync.log
EXIT_CODE_FILE="$RUN_STATUS_DIR"/vagrant_sync.exit_code
LAST_REMOTE_FILE_MANIFEST="$RUN_STATUS_DIR"/file_manifest.last_remote.txt
LAST_LOCAL_FILE_MANIFEST="$RUN_STATUS_DIR"/file_manifest.last_local.txt
NEXT_REMOTE_FILE_MANIFEST="$RUN_STATUS_DIR"/file_manifest.next_remote.txt
NEXT_LOCAL_FILE_MANIFEST="$RUN_STATUS_DIR"/file_manifest.next_local.txt
REMOTE_DIR=/vagrant
LOCAL_DIR=/vagrant_local

COUNTER=0
SINGNAL_RECEIVED=0
SHOW_HELP=0
HELP_RETURN_CODE=0
KILL_MODE=0
LAUNCH_DEAMON=$(echo 1 - 0$DEAMON_MODE | bc)
UNEXPECTED_ARG_COUNT="-ne 0"
ARGS_TO_PASS=(  ) 

function outputFileManifest {
	ls -ad1l --time-style=+%s $*  | \
		sed -e 's/^\([-a-z]\{10\}\)[ \t]\+\([0-9]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([0-9]\+\)[ \t]\+\([0-9]\+\)[ \t]\+\([^ \t].*\)$/\7 \5 \6 \3 \4 \1/' | \
		sort
} 

function dumpDirManifestToFile {
	find $1 -not -name .git -not -path '*/.git/*' -exec ls -ad1l --time-style=+%s "{}" + | \
		sed -e 's/^\([-a-z]\{10\}\)[ \t]\+\([0-9]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([0-9]\+\)[ \t]\+\([0-9]\+\)[ \t]\+\([^ \t].*\)$/\7 \5 \6 \3 \4 \1/' | \
		sort > $2
} 

function simplifyFileManifest {
	sed -e 's/^\([^ \t].*[^ \t]\)[ \t]\+\([0-9]\+\)[ \t]\+\([0-9]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([^ \t]\+\)[ \t]\+\([-a-z]\{10\}\)$/\1/' < $1
} 

function propigateFileChanges {
	if [ "$SINGNAL_RECEIVED" -eq "0" ] ; then
		dumpDirManifestToFile $1 $3 2> /dev/null
	
		if [ "$SINGNAL_RECEIVED" -eq "0" ] ; then
			changeCount=0
			touch $5"_newlyDeleted"
			touch $5"_newlyAdded"
			# simplifyFileManifest $2 > $5"__lastFiles.del_tmp"
			# simplifyFileManifest $3 > $5"__nextFiles.del_tmp"
			# diff -u $5"__lastFiles.del_tmp" $5"__nextFiles.del_tmp" > $5"__changedFiles.del_tmp"
			# sort -r < $5"__changedFiles.del_tmp" > $5"__changedFilesSorted.del_tmp"
			# grep '^-/' < $5"__changedFilesSorted.del_tmp" > $5"__changedFilesFiltered.del_tmp"
			# sed -e "s,^-$1/,," < $5"__changedFilesFiltered.del_tmp" > $5"__changedFilesCleanedUp.del_tmp"
			# if [ -s $5"__changedFilesCleanedUp.del_tmp" ] ; then
			# 	echo $(date "+%c")" aborting with debug information" >> $LOG_FILE
			# 	exit
			# fi
			for file in $(diff -u <(simplifyFileManifest $2) <(simplifyFileManifest $3) | sort -r | grep '^-/' | sed -e "s,^-$1/,," 2> /dev/null ) ; do
				rm -rf $4/$file
				echo $file >> $5"_newlyDeleted"
				echo $(date "+%c")" deleting $4/$file" >> $LOG_FILE
				changeCount=$(echo $changeCount + 1 | bc)
			done
			# diff -u $2 $3 > $5"__changedFiles.add_tmp"
			# grep '^\+'$1/ < $5"__changedFiles.add_tmp" > $5"__changedFilesFiltered.add_tmp"
			# simplifyFileManifest $5"__changedFilesFiltered.add_tmp" > $5"__changedFilesSimplified.add_tmp"
			# sed -e "s,^\+$1/,," -e 's/ -> .*$//' < $5"__changedFilesSimplified.add_tmp" > $5"__changedFilesCleanedUp.add_tmp"
			# if [ -s $5"__changedFilesCleanedUp.add_tmp" ] ; then
			# 	echo $(date "+%c")" aborting with debug information" >> $LOG_FILE
			# 	exit
			# fi
			for file in $(simplifyFileManifest <(diff -u $2 $3 | grep '^\+'$1/) | sed -e "s,^\+$1/,," -e 's/ -> .*$//') ; do
				rsync --recursive --times --perms --links $1/$file $(echo $4/$file | sed -e 's,[^/]*/*$,,' ) >> $LOG_FILE 2>> $LOG_FILE
				outputFileManifest $4/$file >> $5"_newlyAdded"
				echo $(date "+%c")" creating/updating $4/$file" >> $LOG_FILE
				changeCount=$(echo $changeCount + 1 | bc)
			done
			(grep -Ev '^'$4/'('$((simplifyFileManifest $5"_newlyAdded" ; cat $5"_newlyDeleted") | sort -u | perl -ne 'chop; if (/\S/) { print join("", map {(/^[_A-Za-z0-9]$/) ? $_ : sprintf("\\x%02x",ord($_))} (split //)) . "\n"}' | tr ' ' '|')')[ \t]+[0-9]+[ \t]+[0-9]+[ \t]+[^ \t]+[ \t]+[^ \t]+[ \t]+[-a-z]{10}$' $5 ; cat $5"_newlyAdded") | sort > $5"_newlyFiltered"
			if diff -u $5 $5"_newlyFiltered" | grep . > /dev/null ; then
			#	echo $(date "+%c")" aborting with debug information" >> $LOG_FILE
			#	exit
				;
			elif [ $changeCount -gt 0 ] ; then
				echo $(date "+%c")" INTERNAL ERROR... aborting, missing expected change to "$5"_newlyFiltered" >> $LOG_FILE
			#	echo ran: '('simplifyFileManifest $5"_newlyAdded" ; cat $5"_newlyDeleted"')'| sort -u | perl -ne \''chop; if (/\S/) { print join("", map {(/^[_A-Za-z0-9]$/) ? $_ : sprintf("\\x%02x",ord($_))} (split //)) . "\n"}'\' | tr "' '" "'|'"
			#	echo with output: $((simplifyFileManifest $5"_newlyAdded" ; cat $5"_newlyDeleted") | sort -u | perl -ne 'chop; if (/\S/) { print join("", map {(/^[_A-Za-z0-9]$/) ? $_ : sprintf("\\x%02x",ord($_))} (split //)) . "\n"}' | tr ' ' '|')
			#	echo ran: '('grep -Ev "'^'$4/'('"$((simplifyFileManifest $5"_newlyAdded" ; cat $5"_newlyDeleted") | sort -u | perl -ne 'chop; if (/\S/) { print join("", map {(/^[_A-Za-z0-9]$/) ? $_ : sprintf("\\x%02x",ord($_))} (split //)) . "\n"}' | tr ' ' '|')\'')[ \t]+[0-9]+[ \t]+[0-9]+[ \t]+[^ \t]+[ \t]+[^ \t]+[ \t]+[-a-z]{10}$'\' $5 ';' cat $5"_newlyAdded"') |' sort
			#	echo with results in $5"_newlyFiltered"
				exit
			fi
			mv $5"_newlyFiltered" $5
			rm -f $5"_newlyAdded" $5"_newlyDeleted"
			mv $3 $2
		fi
	fi
} 

if [[ $# -gt 0 ]]; then 
	case "z$1" in
	  "z-k" | "z--kill" ) \
	  	KILL_MODE=1
	  	UNEXPECTED_ARG_COUNT="-ne 1";;
	   "z-h" | "z--help" ) \
	  	SHOW_HELP=1
	  	UNEXPECTED_ARG_COUNT="-ne 1";;
	  "z--" ) \
	  	ARGS_TO_PASS=${@:2}
	  	UNEXPECTED_ARG_COUNT="-lt 2";;
	  "z-f" | "z--foreground" ) \
	  	LAUNCH_DEAMON=0
	  	LOG_FILE="/proc/$$/fd/1"
		if [[ $# -gt 1 ]] && [[ "z$2" == "z--" ]]; then \
	  		ARGS_TO_PASS=${@:3}
			UNEXPECTED_ARG_COUNT="-lt 3"
		else
			UNEXPECTED_ARG_COUNT="-ne 1"
		fi;;
	  *) UNEXPECTED_ARG_COUNT=" != 'INVALID'";;
	esac
fi

if [ $# $UNEXPECTED_ARG_COUNT ]; then 
	echo "Unrecognized arguments: $*"
	SHOW_HELP=1
	HELP_RETURN_CODE=1
fi

if [ "$SHOW_HELP" -ne "0" ]; then
	echo " "
	echo "$0  --  keep vagrant shared directory shared to local mirror"
	echo " "
	echo "   -h | --help        Display this help message"
	echo "   -k | --kill        Terminate an instance of $0 already running"
	echo "   -f | --foreground  Run in foreground. (Do not run as a deamon)"
	echo "   --                 All arguments after this passed through to csync2"
	echo " "
	exit $HELP_RETURN_CODE
fi

if [ "$KILL_MODE" -ne "0" ]; then
	if [ -r "$PID_FILE" ]
	then
		if ps $(cat $PID_FILE) > /dev/null 2> /dev/null
		then
			echo "Killing $0 with pid $(cat $PID_FILE)"
			kill -15 $(cat $PID_FILE)
			exit 0;
		else
			echo "$0 with pid $(cat $PID_FILE) didn't clean up after itself."
			rm -rf $RUN_STATUS_DIR
			exit 2;
		fi
	else 
		echo "Failed to kill $0. It doesn't appear to be running."
		exit 1
	fi
fi

if [ "$LAUNCH_DEAMON" -ne "0" ]; then
	if [ ${#ARGS_TO_PASS[@]} -gt 0 ] ; then \
		DEAMON_MODE=1 $0 -- $ARGS_TO_PASS < /dev/null 2> /dev/null > $STARTUP_LOG_FILE &
	else
		DEAMON_MODE=1 $0 < /dev/null 2> /dev/null > $STARTUP_LOG_FILE &
	fi
	sleep 3
	cat $STARTUP_LOG_FILE
	RESULT_CODE=$(cat $EXIT_CODE_FILE)
	rm -f $STARTUP_LOG_FILE $EXIT_CODE_FILE
	exit $RESULT_CODE;
fi

until mkdir $RUN_STATUS_DIR 2> /dev/null > /dev/null; do
	sleep 2
	if [ -r "$PID_FILE" ]
	then
		if ps $(cat $PID_FILE) > /dev/null 2> /dev/null
		then
			echo "$0 already running with pid $(cat $PID_FILE). Exiting."
			echo 1 > $EXIT_CODE_FILE
			exit 1
		else
			echo "Cleaning up stale files after previous execution."
			rm -rf $RUN_STATUS_DIR
		fi
	fi
	if [  $COUNTER -gt 10 ]
	then
		echo "$0 failed waiting to create directory $RUN_STATUS_DIR"
		echo 2 > $EXIT_CODE_FILE
		exit 2
	fi
	let COUNTER=COUNTER+1
done
echo $$ > $PID_FILE
echo 0 > $EXIT_CODE_FILE
cd /
chmod 777 $RUN_STATUS_DIR


if [ "0$DEAMON_MODE" -eq "01" ]; then
	trap "echo ignoring signal  >> $LOG_FILE" SIGHUP SIGINT
else
	trap "SINGNAL_RECEIVED=1 ; SLEEP_TIME=0" SIGHUP SIGINT
fi
 
trap "SINGNAL_RECEIVED=1 ; SLEEP_TIME=0" SIGTERM


echo $(date "+%c")" starting $0 daemon..." >> $LOG_FILE

rsync --recursive --times --perms --links $REMOTE_DIR/* $LOCAL_DIR >> $LOG_FILE 2>> $LOG_FILE
rm -f \
	$NEXT_REMOTE_FILE_MANIFEST                 $NEXT_LOCAL_FILE_MANIFEST \
	$LAST_REMOTE_FILE_MANIFEST"_newlyDeleted"  $LAST_LOCAL_FILE_MANIFEST"_newlyDeleted" \
	$LAST_REMOTE_FILE_MANIFEST"_newlyAdded"    $LAST_LOCAL_FILE_MANIFEST"_newlyAdded" \
	$LAST_REMOTE_FILE_MANIFEST"_newlyFiltered" $LAST_LOCAL_FILE_MANIFEST"_newlyFiltered"
dumpDirManifestToFile $REMOTE_DIR $LAST_REMOTE_FILE_MANIFEST
dumpDirManifestToFile $LOCAL_DIR $LAST_LOCAL_FILE_MANIFEST

echo $(date "+%c")" startup finished." >> $LOG_FILE

while [ "$SINGNAL_RECEIVED" -eq "0" ]; do
	touch "$RUN_STATUS_DIR"/requesting_resync_pids.txt
	cp "$RUN_STATUS_DIR"/requesting_resync_pids.txt "$RUN_STATUS_DIR"/awaiting_resync_pids.txt
	rm -f "$RUN_STATUS_DIR"/requesting_resync_pids.txt
	propigateFileChanges $REMOTE_DIR $LAST_REMOTE_FILE_MANIFEST $NEXT_REMOTE_FILE_MANIFEST $LOCAL_DIR $LAST_LOCAL_FILE_MANIFEST
	propigateFileChanges $LOCAL_DIR $LAST_LOCAL_FILE_MANIFEST $NEXT_LOCAL_FILE_MANIFEST $REMOTE_DIR $LAST_REMOTE_FILE_MANIFEST 
	
	rm -f "$RUN_STATUS_DIR"/awaiting_resync_pids.txt
	
	SLEEP_TIME=$(uptime | sed -e 's/^.*load average[^0-9.]*/scale=0\n(/' -e 's/,.*$/*10)+1/' | bc | sed -e 's/\..*$//') 2> /dev/null

	while [ "$SLEEP_TIME" -gt "0" ]; do
		if [ -s "$RUN_STATUS_DIR"/requesting_resync_pids.txt ]; then
			SLEEP_TIME=0
			echo $(date '+%c')' received request to initiate resync...' >> $LOG_FILE
		else
			sleep 1
			SLEEP_TIME=$(echo $SLEEP_TIME - 1 | bc)
		fi
	done
done

echo $(date "+%c")" shutting down $0 daemon" >> $LOG_FILE

rm -rf $RUN_STATUS_DIR
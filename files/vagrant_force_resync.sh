#!/bin/bash

RUN_STATUS_DIR=/tmp/vagrant_sync
PID_FILE="$RUN_STATUS_DIR"/vagrant_sync.pid

SINGNAL_RECEIVED=0
SHOW_HELP=0
HELP_RETURN_CODE=0
UNEXPECTED_ARG_COUNT="-ne 0"

if [[ $# -gt 0 ]]; then 
	case "z$1" in
	   "z-h" | "z--help" ) \
	  	SHOW_HELP=1
	  	UNEXPECTED_ARG_COUNT="-ne 1";;
	  *) UNEXPECTED_ARG_COUNT=" != 'INVALID'";;
	esac
fi

if [ $# $UNEXPECTED_ARG_COUNT ]; then 
	echo "Unrecognized arguments: $*"
	SHOW_HELP=1
	HELP_RETURN_CODE=1
fi

if [ -r "$PID_FILE" ] && ps $(cat $PID_FILE) > /dev/null 2> /dev/null
then
	echo $$ >> "$RUN_STATUS_DIR"/requesting_resync_pids.txt
	chmod 666 "$RUN_STATUS_DIR"/requesting_resync_pids.txt 2> /dev/null
	echo -n Requesting up-to-date sync from /vagrant...
else
	echo "The vagrant_sync daemon must be running to use this command."
	SHOW_HELP=1
	HELP_RETURN_CODE=1
fi

if [ "$SHOW_HELP" -ne "0" ]; then
	echo " "
	echo "$0  --  wait for vagrant_sync to resync shared vagrant folder"
	echo " "
	echo "   -h | --help        Display this help message"
	echo " "
	exit $HELP_RETURN_CODE
fi

while cat $RUN_STATUS_DIR/requesting_resync_pids.txt $RUN_STATUS_DIR/awaiting_resync_pids.txt 2> /dev/null | grep '^'$$'$' > /dev/null ; do
	sleep 1
done

if [ -r "$PID_FILE" ] && ps $(cat $PID_FILE) > /dev/null 2> /dev/null
then
	echo " done."
else
	echo "The vagrant_sync daemon while awaiting resync."
	exit 1
fi


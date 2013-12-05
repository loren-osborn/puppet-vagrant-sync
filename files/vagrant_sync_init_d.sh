#! /bin/sh

### BEGIN INIT INFO
# Provides:          vagrant_sync
# Required-Start:    $remote_fs $syslog
# Required-Stop:     $remote_fs $syslog
# Should-Start:      $named
# Default-Start:     2 3 4 5
# Default-Stop:      
# Short-Description: Custom script to run csync2
# Description:       Custom script to run csync2
### END INIT INFO

set -e

# /etc/init.d/vagrant_sync: start and stop the bash vagrant_sync daemon

DAEMON=/usr/local/sbin/vagrant_sync
VAGRANT_SYNC_ENABLE=false
VAGRANT_SYNC_OPTS=''
VAGRANT_SYNC_DEFAULTS_FILE=/etc/default/vagrant_sync
VAGRANT_SYNC_PID_FILE=/tmp/vagrant_sync/vagrant_sync.pid
VAGRANT_SYNC_PID_DIR=/tmp/vagrant_sync

test -x $DAEMON || exit 0

. /lib/lsb/init-functions

if [ -s $VAGRANT_SYNC_DEFAULTS_FILE ]; then
    . $VAGRANT_SYNC_DEFAULTS_FILE
    case "x$VAGRANT_SYNC_ENABLE" in
	xtrue|xfalse)	;;
	*)		log_failure_msg "Value of VAGRANT_SYNC_ENABLE in $VAGRANT_SYNC_DEFAULTS_FILE must be either 'true' or 'false';"
			log_failure_msg "not starting vagrant_sync bash daemon."
			exit 1
			;;
    esac
fi

export PATH="${PATH:+$PATH:}/usr/sbin:/sbin:/usr/local/bin"

vagrant_sync_start() {
    if start-stop-daemon --start --background \
        --pidfile $VAGRANT_SYNC_PID_FILE \
        --exec $DAEMON \
        -- $VAGRANT_SYNC_OPTS
    then
        rc=0
        sleep 1
        if ! kill -0 $(cat $VAGRANT_SYNC_PID_FILE) >/dev/null 2>&1; then
            log_failure_msg "vagrant_sync bash daemon failed to start"
            rc=1
        fi
    else
        rc=1
    fi
    if [ $rc -eq 0 ]; then
        log_end_msg 0
    else
        log_end_msg 1
        rm -rf $VAGRANT_SYNC_PID_DIR
    fi
} # vagrant_sync_start


case "$1" in
  start)
	if "$VAGRANT_SYNC_ENABLE"; then
	    log_daemon_msg "Starting vagrant_sync bash daemon" "vagrant_sync"
	    if [ -s $VAGRANT_SYNC_PID_FILE ] && kill -0 $(cat $VAGRANT_SYNC_PID_FILE) >/dev/null 2>&1; then
			log_progress_msg "apparently already running"
			log_end_msg 0
			exit 0
	    fi
		vagrant_sync_start
	else
		[ "$VERBOSE" != no ] && log_warning_msg "vagrant_sync bash daemon not enabled in $VAGRANT_SYNC_DEFAULTS_FILE, not starting..."
	fi
	;;
  stop)
	log_daemon_msg "Stopping vagrant_sync bash daemon" "vagrant_sync"
	KILL_RESULT=$($DAEMON -k)
	[ "$VERBOSE" != no ] && log_warning_msg "$KILL_RESULT"
	start-stop-daemon --stop --oknodo --pidfile $VAGRANT_SYNC_PID_FILE
	log_end_msg $?
	rm -rf $VAGRANT_SYNC_PID_DIR
	;;

  reload|force-reload)
	log_warning_msg "Reloading vagrant_sync bash daemon: not needed, as it"
	log_warning_msg "doesn't have it's own config file."
	;;

  restart)
	set +e
	if $VAGRANT_SYNC_ENABLE; then
	    log_daemon_msg "Restarting vagrant_sync bash daemon" "vagrant_sync"
	    if [ -s $VAGRANT_SYNC_PID_FILE ] && kill -0 $(cat $VAGRANT_SYNC_PID_FILE) >/dev/null 2>&1; then
			KILL_RESULT=$($DAEMON -k)
			[ "$VERBOSE" != no ] && log_warning_msg "$KILL_RESULT"
			start-stop-daemon --stop --oknodo --pidfile $VAGRANT_SYNC_PID_FILE || true
			sleep 1
	    else
		log_warning_msg "vagrant_sync bash daemon not running, attempting to start."
	    rm -rf $VAGRANT_SYNC_PID_DIR
	    fi
            vagrant_sync_start
        else
            [ "$VERBOSE" != no ] && log_warning_msg "vagrant_sync bash daemon not enabled in $VAGRANT_SYNC_DEFAULTS_FILE, not starting..."
	fi
	;;

  status)
	status_of_proc -p $VAGRANT_SYNC_PID_FILE "$DAEMON" vagrant_sync
	exit $?	# notreached due to set -e
	;;
  *)
	echo "Usage: /etc/init.d/vagrant_sync {start|stop|reload|force-reload|restart|status}"
	exit 1
esac

exit 0

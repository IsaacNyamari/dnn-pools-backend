#!/bin/sh
# Started by cron every minute on the server. Does nothing unless a system administrator pressed
# "Deploy latest" on the dashboard. It takes no input from the website: it only ever pulls the
# main branch and runs scripts/cpanel-deploy.sh. See DEPLOYMENT.md.
#
# Cron command:  /bin/sh /home/USER/dnn-pools-backend/scripts/cpanel-deploy-runner.sh >/dev/null 2>&1
#
# Everything is inside main(), called on the last line, so that the shell has read the whole
# script before "git pull" replaces this file.

DIR=storage/app/deploy

now() {
    date -u +%Y-%m-%dT%H:%M:%SZ
}

# write_status STATE MESSAGE. Both come from this script, never from outside.
write_status() {
    {
        echo "state=$1"
        echo "message=$2"
        echo "started_at=$STARTED"
        if [ "$1" != running ]; then
            echo "finished_at=$(now)"
        fi
        echo "from=$FROM"
        echo "to=$(git rev-parse --short HEAD 2>/dev/null)"
    } > "$DIR/status.tmp" && mv -f "$DIR/status.tmp" "$DIR/status"
}

finish() {
    write_status "$1" "$2"
    FINISHED=true
    [ "$1" = success ] && exit 0
    exit 1
}

# Runs on every way out once the lock is held.
cleanup() {
    if [ "$FINISHED" != true ]; then
        write_status failed "The deployment stopped before it finished."
    fi
    rm -f "$DIR/processing"
    rmdir "$DIR/lock" 2>/dev/null
}

main() {
    cd "$(dirname "$0")/.." || exit 1
    umask 077
    mkdir -p "$DIR" || exit 1

    # Tells the dashboard that cron works.
    touch "$DIR/heartbeat"

    # A lock left by a run that was killed, for example by a server restart.
    if [ -d "$DIR/lock" ] && [ -n "$(find "$DIR/lock" -maxdepth 0 -mmin +60 2>/dev/null)" ]; then
        rmdir "$DIR/lock" 2>/dev/null
    fi

    [ -f "$DIR/request" ] || exit 0

    # mkdir is atomic: if another run holds the lock, leave the request for later.
    mkdir "$DIR/lock" 2>/dev/null || exit 0
    FINISHED=false
    STARTED=$(now)
    trap cleanup EXIT
    trap 'exit 1' HUP INT TERM

    mv -f "$DIR/request" "$DIR/processing" || exit 1

    # Cron has a very small environment.
    if [ -z "$HOME" ]; then
        HOME=$(cd ~ && pwd)
        export HOME
    fi
    PATH="$PATH:/usr/local/bin:/usr/local/cpanel/3rdparty/bin"
    export PATH
    GIT_TERMINAL_PROMPT=0
    export GIT_TERMINAL_PROMPT

    FROM=$(git rev-parse --short HEAD 2>/dev/null)
    write_status running "Deploying."
    : > "$DIR/last.log"
    exec >> "$DIR/last.log" 2>&1

    command -v git >/dev/null 2>&1 || finish failed "Git was not found. Nothing was changed."

    if [ "$(git rev-parse --abbrev-ref HEAD)" != main ]; then
        finish failed "The server's copy is not on the main branch. Nothing was changed."
    fi

    # Untracked files are fine: git refuses the pull itself if one is in the way.
    if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
        git status --short --untracked-files=no
        finish failed "Files were changed on the server. Nothing was changed."
    fi

    echo "== Pulling from GitHub"
    git pull --ff-only origin main || finish failed "Could not pull from GitHub. Nothing was changed."

    echo "== Deploying"
    sh scripts/cpanel-deploy.sh || finish failed "The code was updated, but a later step failed. The site may be in maintenance mode."

    finish success "The latest code from GitHub is live."
}

main "$@"; exit

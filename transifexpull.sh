#!/bin/sh

# In 'translator' mode files will contain empty translated texts
# where translation is not available, we'll remove these later

# --force is necessary to avoid timestamp issues
# https://bugs.launchpad.net/ironic/+bug/1298645/comments/4

PWD=`dirname "$0"`
RES=${1:-'*'}
TX=`which tx`
TXARGS=""

if [ "$RES" != "*" ]; then
    TXARGS="-r kolab.$RES"
fi

$TX --debug pull --force -a --mode translator $TXARGS

do_count()
{
    LABELS=`grep -e '^\$labels' "$1" | wc -l`
    MSGS=`grep -e '^\$messages' "$1" | wc -l`
    CNT=$((LABELS+MSGS))

    echo $CNT
}

do_clean()
{
    # do not cleanup en_US files
    echo "$1" | grep -v en_US > /dev/null || return

    # remove untranslated/empty texts
    perl -pi -e "s/^\\\$labels\[[^]]+\]\s+=\s+['\"]{2};\n//" $1
    perl -pi -e "s/^\\\$messages\[[^]]+\]\s+=\s+['\"]{2};\n//" $1
    # remove variable initialization
    perl -pi -e "s/^\\\$(labels|messages)\s*=\s*array\(\);\n//" $1
    # remove (one-line) comments
    perl -pi -e "s/^\\/\\/.*//" $1
    # remove empty lines (but not in file header)
    perl -ne 'print if ($. < 2 || length($_) > 1)' $1 > $1.tmp
    mv $1.tmp $1
}

# clean up translation files
for plugin in $PWD/plugins/$RES; do
    if [ -s $plugin/localization/en_US.inc ]; then
        EN_CNT=`do_count $plugin/localization/en_US.inc`

        for file in $plugin/localization/*.inc; do
            do_clean $file
            CNT=`do_count $file`
            PERCENT=$((CNT*100/$EN_CNT))
            echo "$file     [$PERCENT%]"

            # git-add localizations with more than 0%
            if [ "$PERCENT" != "0" ]; then
                git add $file
            else
                rm $file
            fi
        done
    fi
done

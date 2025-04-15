#!/bin/bash

# /etc/zabbix/bin/php-fpm-status-collector.sh | jq -c > /tmp/php-fpm-status-collector.json

[ ! -x /usr/bin/jq ] && apt-get install jq -y

COMMA=""
echo "["

ls -la /var/www/ | grep '\->' | awk '{print $9" "$11}' | while read SITE_STRING;
do

  DOMAIN=`echo "$SITE_STRING" | awk '{print $1}'`
  SYSNAME=`echo "$SITE_STRING" | awk -F '/' '{print $6}'`
  SOCKNAME=/var/lib/php*-fpm/$SYSNAME.sock

  if test -S $SOCKNAME; then
    PFPN_JSON=`SCRIPT_NAME=/pfpm-status SCRIPT_FILENAME=/pfpm-status QUERY_STRING="full&json&domain=$DOMAIN" REQUEST_METHOD=GET cgi-fcgi -bind -connect $SOCKNAME | grep pool`
    echo "$COMMA{"
    echo "\"domain\":\"$DOMAIN\","
    echo "\"data\":$PFPN_JSON"
    echo "}"
  fi

  COMMA=","

done

echo "]"
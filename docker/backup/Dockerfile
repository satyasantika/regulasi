# Container cadangan harian MariaDB Regulasi FKIP Unsil (cron 02:00 WIB). Lihat docs/DEPLOY.md §Cadangan.
FROM alpine:3.20
RUN apk add --no-cache mariadb-client gzip coreutils tzdata findutils \
 && cp /usr/share/zoneinfo/Asia/Jakarta /etc/localtime && echo "Asia/Jakarta" > /etc/timezone
COPY --chmod=755 docker/backup/backup.sh /usr/local/bin/backup.sh
COPY --chmod=755 docker/backup/restore.sh /usr/local/bin/restore.sh
RUN echo '0 2 * * * /usr/local/bin/backup.sh >> /proc/1/fd/1 2>> /proc/1/fd/2' > /etc/crontabs/root
CMD ["crond", "-f", "-l", "8"]

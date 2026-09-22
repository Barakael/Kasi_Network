FROM freeradius/freeradius-server:3.2.10-alpine

# rlm_sql_mysql needs libmariadb at runtime; the stock image does not ship it.
USER root
RUN apk add --no-cache mariadb-connector-c \
    && printf '%s\n' '[client]' 'ssl=0' 'ssl-mode=DISABLED' 'skip-ssl' > /etc/my.cnf

# Kasi's RADIUS behaviour lives entirely in version-controlled config so servers
# are never hand-edited. Site, policy and modules are copied onto the stock
# /opt/etc/raddb tree. queries.conf is left as the image default so FreeRADIUS
# keeps ${acct_table1} nested inside the sql module.
COPY config/dictionary.mikrotik /opt/etc/raddb/dictionary.mikrotik
COPY config/sites-available/kasi /opt/etc/raddb/sites-available/kasi
COPY config/policy.d/kasi /opt/etc/raddb/policy.d/kasi
COPY config/mods-available/ /opt/etc/raddb/mods-available/
COPY docker-entrypoint.sh /usr/local/bin/kasi-radius-entrypoint
RUN chmod +x /usr/local/bin/kasi-radius-entrypoint

# The stock image ships default/inner-tunnel sites we do not use. Leaving them
# enabled would let a request fall through to a site that ignores Kasi's
# tenant-scoping and MAC-binding checks.
RUN rm -f /opt/etc/raddb/sites-enabled/default \
          /opt/etc/raddb/sites-enabled/inner-tunnel \
          /opt/etc/raddb/mods-enabled/eap \
    && ln -sf /opt/etc/raddb/sites-available/kasi /opt/etc/raddb/sites-enabled/kasi \
    && ln -sf /opt/etc/raddb/mods-available/sql /opt/etc/raddb/mods-enabled/sql \
    && ln -sf /opt/etc/raddb/mods-available/sqlcounter /opt/etc/raddb/mods-enabled/sqlcounter \
    && echo '$INCLUDE dictionary.mikrotik' >> /opt/etc/raddb/dictionary \
    && sed -i \
         -e 's/start_servers = 5/start_servers = 32/' \
         -e 's/max_servers = 32/max_servers = 256/' \
          /opt/etc/raddb/radiusd.conf

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["kasi-radius-entrypoint"]
CMD ["radiusd", "-f", "-l", "stdout"]

FROM freeradius/freeradius-server:3.2-alpine

# Kasi's RADIUS behaviour lives entirely in version-controlled config so servers
# are never hand-edited. Everything under config/ is overlaid onto the stock
# raddb tree, replacing the default site and enabling the SQL + sqlcounter
# modules that back voucher quotas.
COPY config/ /etc/raddb/
COPY docker-entrypoint.sh /usr/local/bin/kasi-radius-entrypoint
RUN chmod +x /usr/local/bin/kasi-radius-entrypoint

# The stock image ships default/inner-tunnel sites we do not use. Leaving them
# enabled would let a request fall through to a site that ignores Kasi's
# tenant-scoping and MAC-binding checks.
RUN rm -f /etc/raddb/sites-enabled/default \
          /etc/raddb/sites-enabled/inner-tunnel \
    && ln -sf /etc/raddb/sites-available/kasi /etc/raddb/sites-enabled/kasi \
    && ln -sf /etc/raddb/mods-available/sql /etc/raddb/mods-enabled/sql \
    && ln -sf /etc/raddb/mods-available/sqlcounter /etc/raddb/mods-enabled/sqlcounter \
    && echo '$INCLUDE dictionary.mikrotik' >> /etc/raddb/dictionary \
    && sed -i \
         -e 's/start_servers = 5/start_servers = 32/' \
         -e 's/max_servers = 32/max_servers = 256/' \
         /etc/raddb/radiusd.conf \
    && chown -R freerad:freerad /etc/raddb

EXPOSE 1812/udp 1813/udp

ENTRYPOINT ["kasi-radius-entrypoint"]
CMD ["radiusd", "-f", "-l", "stdout"]

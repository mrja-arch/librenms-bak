FROM quay.io/frrouting/frr:10.2.1

RUN apk add --no-cache lldpd net-snmp net-snmp-tools

COPY entrypoint.sh /usr/local/bin/campus-agg-entrypoint
RUN chmod +x /usr/local/bin/campus-agg-entrypoint

ENTRYPOINT ["/usr/local/bin/campus-agg-entrypoint"]

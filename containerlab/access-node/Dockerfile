FROM quay.io/frrouting/frr:10.2.1

RUN apk add --no-cache bash bridge iproute2 lldpd net-snmp

COPY entrypoint.sh /usr/local/bin/campus-access
RUN chmod +x /usr/local/bin/campus-access

ENTRYPOINT ["/usr/local/bin/campus-access"]

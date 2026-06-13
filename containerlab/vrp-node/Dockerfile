ARG CE_IMAGE=windddkz/huawei_vrp:ce12800-8.180
FROM ${CE_IMAGE}

ARG STARTUP_CONFIG
COPY ${STARTUP_CONFIG} /opt/campus/startup-config.cfg
COPY vrp-node/entrypoint.sh /usr/local/bin/campus-vrp-entrypoint
RUN chmod +x /usr/local/bin/campus-vrp-entrypoint

ENTRYPOINT ["/usr/local/bin/campus-vrp-entrypoint"]

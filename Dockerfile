FROM debian:buster-slim as builder
RUN apt-get --yes update && \
	apt-get --yes install --no-install-recommends libpoppler-cpp-dev libepub-dev libsqlite3-dev g++ make
COPY backend /src
RUN ls /src
WORKDIR /src
RUN make

FROM debian:buster-slim
ARG SIGIL_VERSION=0.4.0
ARG KINDLEGEN_VERSION=2_9
SHELL ["/bin/bash", "-o", "pipefail", "-c"]
# BACKEND
RUN apt-get --yes update && \
	# Install dependencies
	apt-get --yes install --no-install-recommends \
	apache2 \
	ca-certificates \
	curl \
	libapache2-mod-php7.3 \
	libepub0 \
	libpoppler-cpp0v5 \
 	libsqlite3-0 \
	php-gd \
	php-sqlite3 \
	php-zip \
	wget \
	&& \
	# Install sigil
	curl -L "https://github.com/gliderlabs/sigil/releases/download/v${SIGIL_VERSION}/sigil_${SIGIL_VERSION}_$(uname -sm|tr \  _).tgz" \
    | tar -zxC /usr/local/bin && \
    # Install kindlegen
	wget "http://kindlegen.s3.amazonaws.com/kindlegen_linux_2.6_i386_v${KINDLEGEN_VERSION}.tar.gz" -O \
		"/tmp/kindlegen_linux_2.6_i386_v${KINDLEGEN_VERSION}.tar.gz" && \
	tar -xzf "/tmp/kindlegen_linux_2.6_i386_v${KINDLEGEN_VERSION}.tar.gz" -C /tmp && \
	mv /tmp/kindlegen /usr/bin && \
	rm "/tmp/kindlegen_linux_2.6_i386_v${KINDLEGEN_VERSION}.tar.gz" && \
	# Cleanup
	apt-get --yes remove curl wget && apt-get --yes clean && rm -rf /var/lib/apt/lists/* /tmp/* && \
	a2dismod mpm_event && \
	a2enmod php7.3 rewrite

COPY --from=builder /src/elibsrv /usr/bin/

COPY etc/elibsrv.conf.tmpl /etc/
COPY frontend /var/www/html
COPY frontend/apache.conf /etc/apache2/sites-available/000-default.conf
COPY entrypoint.sh /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
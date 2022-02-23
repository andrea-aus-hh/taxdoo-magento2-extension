# ./Dockerfile

FROM alexcheng/magento2

LABEL authors="Andrea Lazzaretti"

RUN cd /var/www/html \
	&& curl -L -O https://github.com/andrea-aus-hh/taxdoo-magento2-extension/archive/refs/tags/taxdoo.tar.gz \
	&& mkdir -p app/code/Taxdoo/VAT \
	&& tar xf taxdoo.tar.gz --strip-components=1 -C app/code/Taxdoo/VAT \
	&& rm -f taxdoo.tar.gz \
RUN chown -R www-data:www-data *



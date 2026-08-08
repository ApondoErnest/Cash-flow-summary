# syntax=docker/dockerfile:1

ARG APP_IMAGE=cashflow-summary-app:latest

FROM ${APP_IMAGE} AS app

FROM nginx:1.27-alpine

COPY --from=app /var/www/html/public /var/www/html/public
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

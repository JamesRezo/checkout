FROM alpine

COPY checkout.php /checkout
RUN chmod +x /checkout
